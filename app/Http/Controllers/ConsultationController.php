<?php

namespace App\Http\Controllers;

use App\Models\AllergyConflictCheck;
use App\Models\AuditLog;
use App\Models\ClinicalNote;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitOutcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsultationController extends Controller
{
    public function index()
    {
        // Doctor's Queue
        $visits = Visit::with(['patient', 'department', 'latestTriage'])
            ->where('status', 'waiting_doctor')
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('consultations.index', compact('visits'));
    }

    public function create(Visit $visit)
    {
        // Update visit status to 'in_consultation' if not already, unless it's away at the lab
        if (! in_array($visit->status, ['in_consultation', 'waiting_lab'], true)) {
            $visit->updateStatus('in_consultation');
        }

        $visit->load(['patient.visits.diagnoses', 'patient.identityLogs', 'vitals', 'triageAssessments', 'labOrders.items.labTest']);

        $pastDiagnoses = Diagnosis::whereHas('consultation.visit', function ($q) use ($visit) {
            $q->where('patient_id', $visit->patient_id);
        })->with('consultation.visit')->latest()->get();

        $icd10Codes = Icd10Code::all();
        $drugs = Drug::where('is_active', true)->get();
        $labTests = LabTest::where('is_active', true)->orderBy('category')->get();
        $departments = Department::where('is_active', true)->where('id', '!=', $visit->department_id)->orderBy('name')->get();

        return view('consultations.create', compact('visit', 'pastDiagnoses', 'icd10Codes', 'drugs', 'labTests', 'departments'));
    }

    public function orderLabs(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'lab_tests' => 'required|array|min:1',
            'lab_tests.*' => 'exists:lab_tests,id',
            'priority' => 'required|in:routine,urgent,stat',
            'clinical_indication' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $visit, $request) {
            $consultation = Consultation::where('visit_id', $visit->id)->latest()->first();

            $labOrder = LabOrder::create([
                'visit_id' => $visit->id,
                'consultation_id' => $consultation?->id,
                'ordered_by' => auth()->id(),
                'priority' => $validated['priority'],
                'clinical_indication' => $validated['clinical_indication'] ?? null,
                'status' => 'ordered',
            ]);

            foreach ($validated['lab_tests'] as $testId) {
                $test = LabTest::find($testId);

                LabOrderItem::create([
                    'lab_order_id' => $labOrder->id,
                    'lab_test_id' => $testId,
                    'specimen_type' => $test->specimen_type,
                    'reference_range' => $test->reference_range,
                ]);
            }

            $visit->updateStatus('waiting_lab');

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'lab_order_created',
                'description' => "Lab order #{$labOrder->id} created for visit {$visit->visit_number}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['visit_id' => $visit->id, 'lab_order_id' => $labOrder->id],
            ]);
        });

        return redirect()->route('consultations.index')
            ->with('success', "Lab tests ordered for {$visit->patient->full_name}. Sent to the laboratory.");
    }

    public function store(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'clinical_notes' => 'required|string',
            'diagnoses' => 'required|array|min:1',
            'diagnoses.*.code' => 'required|exists:icd10_codes,code',
            'diagnoses.*.description' => 'required|string',
            'diagnoses.*.is_primary' => 'boolean',
            'prescription_items' => 'nullable|array',
            'prescription_items.*.drug_id' => 'required|exists:drugs,id',
            'prescription_items.*.dose' => 'required|string',
            'prescription_items.*.frequency' => 'required|string',
            'prescription_items.*.duration' => 'required|integer|min:1',
            'prescription_items.*.quantity' => 'required|integer|min:1',
            'allergy_justifications' => 'nullable|array',
            'outcome' => 'required|in:discharge,admit,refer,dama,deceased',
            'follow_up_date' => 'nullable|date|after:today',
            'referral_destination' => 'nullable|string',
            'outcome_notes' => 'nullable|string',
            'referral_type' => 'required_if:outcome,refer|nullable|in:internal,external',
            'referral_to_department_id' => 'required_if:referral_type,internal|nullable|exists:departments,id',
            'referral_external_facility_name' => 'required_if:referral_type,external|nullable|string',
            'referral_external_facility_contact' => 'nullable|string',
            'referral_urgency' => 'nullable|in:routine,urgent,emergency',
        ]);

        DB::transaction(function () use ($validated, $visit, $request) {
            // 1. Create Consultation
            $consultation = Consultation::create([
                'visit_id' => $visit->id,
                'doctor_id' => auth()->id(),
                'status' => 'finalized',
                'finished_at' => now(),
            ]);

            // 1b. Charge the consultation fee
            $consultationService = Service::where('code', 'CONS-GEN')->first();
            if ($consultationService) {
                Invoice::addCharge(
                    $visit,
                    $consultationService->name,
                    'Consultation',
                    (float) $consultationService->price,
                    $consultationService->is_nhis_covered,
                );
            }

            // 2. Save Notes
            ClinicalNote::create([
                'consultation_id' => $consultation->id,
                'content' => $validated['clinical_notes'],
            ]);

            // 3. Save Diagnoses
            foreach ($validated['diagnoses'] as $diag) {
                Diagnosis::create([
                    'consultation_id' => $consultation->id,
                    'icd10_code' => $diag['code'],
                    'description' => $diag['description'],
                    'is_primary' => $diag['is_primary'] ?? false,
                ]);
            }

            // 4. Handle Prescription
            if ($request->has('prescription_items')) {
                $prescription = Prescription::create([
                    'visit_id' => $visit->id,
                    'doctor_id' => auth()->id(),
                    'status' => 'active',
                ]);

                foreach ($validated['prescription_items'] as $item) {
                    $drug = Drug::find($item['drug_id']);

                    PrescriptionItem::create([
                        'prescription_id' => $prescription->id,
                        'drug_id' => $item['drug_id'],
                        'dose' => $item['dose'],
                        'route' => 'Oral', // Default for now
                        'frequency' => $item['frequency'],
                        'duration_days' => $item['duration'],
                        'quantity_to_dispense' => $item['quantity'],
                        'nhis_covered_snapshot' => $drug->is_nhis_covered,
                    ]);

                    // Check for allergies and log if justification provided
                    if ($request->has("allergy_justifications.{$item['drug_id']}")) {
                        AllergyConflictCheck::create([
                            'prescription_id' => $prescription->id,
                            'allergy_found' => 'User Overridden',
                            'drug_prescribed' => $drug->name,
                            'doctor_justification' => $validated['allergy_justifications'][$item['drug_id']] ?? 'No reason provided',
                            'doctor_id' => auth()->id(),
                        ]);
                    }
                }
            }

            // 5. Record Outcome
            VisitOutcome::create([
                'visit_id' => $visit->id,
                'outcome' => $validated['outcome'],
                'follow_up_date' => $validated['follow_up_date'] ?? null,
                'referral_destination' => $validated['referral_destination'] ?? null,
                'outcome_notes' => $validated['outcome_notes'] ?? null,
                'doctor_id' => auth()->id(),
            ]);

            // 5b. Create a structured referral record
            if ($validated['outcome'] === 'refer') {
                Referral::create([
                    'visit_id' => $visit->id,
                    'type' => $validated['referral_type'],
                    'from_department_id' => $visit->department_id,
                    'to_department_id' => $validated['referral_type'] === 'internal' ? $validated['referral_to_department_id'] : null,
                    'external_facility_name' => $validated['referral_external_facility_name'] ?? null,
                    'external_facility_contact' => $validated['referral_external_facility_contact'] ?? null,
                    'reason' => $validated['outcome_notes'] ?? 'See clinical notes.',
                    'urgency' => $validated['referral_urgency'] ?? 'routine',
                    'status' => 'pending',
                    'referred_by' => auth()->id(),
                ]);
            }

            // 6. Update Visit Status
            $newStatus = match ($validated['outcome']) {
                'discharge' => $request->has('prescription_items') ? 'waiting_pharmacy' : 'completed',
                'admit' => 'awaiting_admission',
                'refer', 'dama', 'deceased' => 'completed',
            };

            $visit->update(['closed_at' => now()]);
            $visit->updateStatus($newStatus);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'consultation_finalized',
                'description' => "Consultation and Prescription finalized for visit {$visit->visit_number}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['visit_id' => $visit->id, 'outcome' => $validated['outcome']],
            ]);
        });

        return redirect()->route('consultations.index')
            ->with('success', "Consultation for {$visit->patient->full_name} finalized.");
    }
}
