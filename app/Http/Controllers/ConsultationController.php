<?php

namespace App\Http\Controllers;

use App\Models\AllergyConflictCheck;
use App\Models\AuditLog;
use App\Models\ClinicalNote;
use App\Models\Consultation;
use App\Models\DamaRecord;
use App\Models\DeathRecord;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\PatientChronicMedication;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitOutcome;
use App\Services\AllergyEngine;
use App\Services\PrescriptionSafetyEngine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationController extends Controller
{
    public function __construct(
        protected AllergyEngine $allergyEngine,
        protected PrescriptionSafetyEngine $safetyEngine,
    ) {}

    public function index()
    {
        // The doctor's queue. A case that has been opened or saved as a draft stays
        // on the list: otherwise merely opening one, or refreshing the page, makes
        // it vanish and the only way back is to know the URL.
        $visits = Visit::with(['patient', 'department', 'latestTriage', 'latestVitals', 'latestConsultation.lockedBy'])
            ->whereIn('status', ['waiting_doctor', 'in_consultation'])
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('consultations.index', compact('visits'));
    }

    public function create(Visit $visit)
    {
        $consultation = $visit->consultations()->latest('id')->first();

        if ($consultation?->isLockedByAnother(auth()->id())) {
            $holder = $consultation->lockedBy?->name ?? 'another clinician';

            return redirect()->route('consultations.index')
                ->with('error', "This case is currently being edited by {$holder}.")
                ->with('lock_conflict_consultation_id', $consultation->id);
        }

        if (! $consultation) {
            $consultation = Consultation::create([
                'visit_id' => $visit->id,
                'doctor_id' => auth()->id(),
                'status' => Consultation::STATUS_ASSIGNED,
                'locked_by' => auth()->id(),
                'locked_at' => now(),
            ]);
        }

        // A signed note is append-only: it is opened read-only for amendment, so it
        // takes no editing lock and must not pull a closed visit back into the queue.
        if ($consultation->isEditable()) {
            $consultation->claimLock(auth()->id());

            if ($consultation->status !== Consultation::STATUS_DRAFT) {
                $consultation->transitionTo(Consultation::STATUS_IN_PROGRESS);
            }

            // Keep the case out of the lab's hands if it is away for investigations.
            if (! in_array($visit->status, ['in_consultation', 'waiting_lab'], true)) {
                $visit->updateStatus('in_consultation');
            }
        }

        $visit->load([
            'patient.activeAllergies',
            'patient.activeChronicMedications.drug',
            'patient.identityLogs',
            'vitals',
            'triageAssessments',
            'labOrders.items.labTest',
        ]);

        $pastDiagnoses = Diagnosis::whereHas('consultation.visit', function ($q) use ($visit) {
            $q->where('patient_id', $visit->patient_id);
        })
            ->whereHas('consultation', fn ($q) => $q->where('id', '!=', $consultation->id))
            ->with('consultation.visit')
            ->latest()
            ->get();

        $icd10Codes = Icd10Code::orderBy('category')->orderBy('code')->get();
        $drugs = Drug::where('is_active', true)->orderBy('name')->get();
        $labTests = LabTest::where('is_active', true)->orderBy('category')->get();
        $departments = Department::where('is_active', true)->where('id', '!=', $visit->department_id)->orderBy('name')->get();

        $consultation->load(['diagnoses', 'notes', 'amendments.user', 'signedBy']);
        $existingItems = $this->existingPrescriptionItems($visit);

        // Precomputed on the server so the warnings the clinician sees while
        // prescribing are the same ones that will gate the save.
        $allergyConflicts = collect($this->allergyEngine->check($visit->patient, $drugs))->keyBy('drug_id');

        return view('consultations.create', compact(
            'visit',
            'consultation',
            'pastDiagnoses',
            'icd10Codes',
            'drugs',
            'labTests',
            'departments',
            'existingItems',
            'allergyConflicts'
        ));
    }

    /**
     * Keep a held editing lock warm.
     */
    public function heartbeat(Consultation $consultation): JsonResponse
    {
        if ($consultation->refreshLock(auth()->id())) {
            return response()->json([
                'status' => 'success',
                'expires_in' => Consultation::LOCK_TIMEOUT_MINUTES * 60,
            ]);
        }

        return response()->json([
            'status' => 'locked',
            'locked_by' => $consultation->lockedBy?->name ?? 'another clinician',
        ], 403);
    }

    /**
     * Server-side draft storage. Called on a timer while the clinician types so
     * an interrupted consultation is never lost.
     */
    public function autosave(Request $request, Consultation $consultation): JsonResponse
    {
        if ($consultation->isSigned()) {
            return response()->json([
                'status' => 'signed',
                'message' => 'This consultation is signed. Use an amendment to change it.',
            ], 409);
        }

        if ($consultation->isLockedByAnother(auth()->id())) {
            return response()->json([
                'status' => 'locked',
                'locked_by' => $consultation->lockedBy?->name ?? 'another clinician',
            ], 409);
        }

        $validated = $request->validate([
            'clinical_notes' => 'nullable|string',
            'diagnoses' => 'nullable|array',
            'diagnoses.*.code' => 'nullable|string',
            'diagnoses.*.description' => 'nullable|string',
            'diagnoses.*.type' => 'nullable|string|in:primary,secondary,comorbidity,differential',
            'diagnoses.*.certainty' => 'nullable|string|in:confirmed,probable,possible,rule-out',
            'prescription_items' => 'nullable|array',
            'prescription_items.*.drug_id' => 'nullable|integer|exists:drugs,id',
            'prescription_items.*.chronic_medication_id' => 'nullable|integer|exists:patient_chronic_medications,id',
            'prescription_items.*.dose' => 'nullable|string',
            'prescription_items.*.frequency' => 'nullable|string',
            'prescription_items.*.duration' => 'nullable|integer|min:1',
            'prescription_items.*.quantity' => 'nullable|integer|min:1',
            'prescription_items.*.route' => 'nullable|string',
            'prescription_items.*.instructions' => 'nullable|string',
            'pregnancy_status' => 'nullable|string|in:none,pregnant,unknown',
            'lmp' => 'nullable|date',
            'edd' => 'nullable|date',
            'gestational_age' => 'nullable|string',
            'lactation_status' => 'nullable|string|in:none,breastfeeding,unknown',
        ]);

        // Only rows the clinician has finished filling in can be stored.
        $usableItems = collect($validated['prescription_items'] ?? [])
            ->filter(fn ($item) => ! empty($item['drug_id']) && ! empty($item['dose'])
                && ! empty($item['frequency']) && ! empty($item['duration']))
            ->values()
            ->all();

        $visit = $consultation->visit;
        $chronicMedications = $this->resolveChronicMedications($visit, $usableItems);

        DB::transaction(function () use ($consultation, $validated, $visit, $usableItems, $chronicMedications) {
            $consultation->claimLock(auth()->id());
            $consultation->update([
                'autosaved_at' => now(),
                'pregnancy_status' => $validated['pregnancy_status'] ?? $consultation->pregnancy_status,
                'lmp' => $validated['lmp'] ?? $consultation->lmp,
                'edd' => $validated['edd'] ?? $consultation->edd,
                'gestational_age' => $validated['gestational_age'] ?? $consultation->gestational_age,
                'lactation_status' => $validated['lactation_status'] ?? $consultation->lactation_status,
            ]);

            $this->saveClinicalNote($consultation, $validated['clinical_notes'] ?? null);

            // Autosave only persists diagnoses that are complete enough to store.
            $usable = collect($validated['diagnoses'] ?? [])
                ->filter(fn ($diag) => ! empty($diag['code']) && ! empty($diag['description']))
                ->all();

            if ($usable !== []) {
                $this->replaceDiagnoses($consultation, $usable);
            }

            // An in-progress plan is only replaced once at least one row is complete,
            // so a half-typed medication never wipes what was already saved.
            if ($usableItems !== []) {
                $this->syncPrescription($visit, $usableItems, $chronicMedications, true);
            }

            if ($consultation->status !== Consultation::STATUS_DRAFT) {
                $consultation->transitionTo(Consultation::STATUS_DRAFT, 'autosaved');
            }
        });

        return response()->json([
            'status' => 'saved',
            'saved_at' => $consultation->fresh()->autosaved_at?->toIso8601String(),
            'version' => $consultation->version,
        ]);
    }

    /**
     * Break-glass: take an editing lock held by another clinician. Deliberately
     * requires a written reason and is always audited.
     */
    public function breakGlass(Request $request, Consultation $consultation)
    {
        if (! auth()->user()->hasPermission('override_consultation_lock')) {
            abort(403, 'You are not permitted to override an editing lock.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        if (! $consultation->isLockedByAnother(auth()->id())) {
            return redirect()->route('consultations.create', $consultation->visit_id)
                ->with('success', 'The case was already free to edit.');
        }

        $previousHolder = $consultation->lockedBy?->name ?? 'unknown';

        $consultation->claimLock(auth()->id());

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'consultation_lock_overridden',
            'description' => "Editing lock on consultation #{$consultation->id} taken from {$previousHolder}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'consultation_id' => $consultation->id,
                'visit_id' => $consultation->visit_id,
                'previous_holder' => $previousHolder,
                'reason' => $validated['reason'],
            ],
        ]);

        return redirect()->route('consultations.create', $consultation->visit_id)
            ->with('success', "Editing lock taken from {$previousHolder}. This has been recorded.");
    }

    public function orderLabs(Request $request, Visit $visit)
    {
        if (! auth()->user()->hasPermission('order_lab_tests')) {
            abort(403, 'You are not permitted to order laboratory investigations.');
        }

        $validated = $request->validate([
            'lab_tests' => 'required|array|min:1',
            'lab_tests.*' => 'exists:lab_tests,id',
            'priority' => 'required|in:routine,urgent,stat',
            'clinical_indication' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $visit, $request) {
            $consultation = $visit->consultations()->latest('id')->first();

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
        $isDraft = $request->has('save_as_draft');

        $validated = $request->validate($this->rules($isDraft));

        $consultation = $visit->consultations()->latest('id')->first();

        if ($consultation?->isSigned()) {
            return redirect()->route('consultations.create', $visit)
                ->with('error', 'This consultation is already signed. Record an amendment instead.');
        }

        if ($consultation?->isLockedByAnother(auth()->id())) {
            $holder = $consultation->lockedBy?->name ?? 'another clinician';

            return back()->withInput()
                ->withErrors(['lock' => "This case is currently being edited by {$holder}. Your changes were not saved."]);
        }

        // A draft may contain rows the clinician has only started. Keep the ones
        // complete enough to store and quietly ignore the rest.
        $diagnoses = $this->storableDiagnoses($validated['diagnoses'] ?? [], $isDraft);
        $prescriptionItems = $this->storablePrescriptionItems($validated['prescription_items'] ?? [], $isDraft);

        $chronicMedications = $this->resolveChronicMedications($visit, $prescriptionItems);
        $conflicts = $this->allergyConflicts($visit, $prescriptionItems);
        $findings = $this->safetyEngine->check($visit->patient, $prescriptionItems, $consultation);

        if (! $isDraft) {
            $this->guardPrimaryDiagnosis($diagnoses);
            $this->guardSafetyFindings(
                $findings,
                $request->input('allergy_justifications', []),
                $request->input('safety_justifications', [])
            );
        }

        DB::transaction(function () use ($validated, $visit, $request, $isDraft, $consultation, $conflicts, $findings, $chronicMedications, $diagnoses, $prescriptionItems) {
            $consultation = $consultation ?: Consultation::create([
                'visit_id' => $visit->id,
                'doctor_id' => auth()->id(),
                'status' => Consultation::STATUS_ASSIGNED,
            ]);

            $consultation->update([
                'doctor_id' => $consultation->doctor_id ?: auth()->id(),
                'pregnancy_status' => $validated['pregnancy_status'] ?? $consultation->pregnancy_status,
                'lmp' => $validated['lmp'] ?? $consultation->lmp,
                'edd' => $validated['edd'] ?? $consultation->edd,
                'gestational_age' => $validated['gestational_age'] ?? $consultation->gestational_age,
                'lactation_status' => $validated['lactation_status'] ?? $consultation->lactation_status,
                'renal_impairment' => $validated['renal_impairment'] ?? $consultation->renal_impairment,
                'hepatic_impairment' => $validated['hepatic_impairment'] ?? $consultation->hepatic_impairment,
                'egfr' => $validated['egfr'] ?? $consultation->egfr,
                'weight_kg' => $validated['weight_kg'] ?? $consultation->weight_kg,
            ]);

            if ($consultation->status === Consultation::STATUS_ASSIGNED) {
                $consultation->transitionTo(Consultation::STATUS_IN_PROGRESS);
            }

            $this->saveClinicalNote($consultation, $validated['clinical_notes'] ?? null);

            if ($diagnoses !== []) {
                $this->replaceDiagnoses($consultation, $diagnoses);
            }

            $prescription = $this->syncPrescription($visit, $prescriptionItems, $chronicMedications, $isDraft);

            if ($prescription !== null) {
                $this->recordAllergyChecks($prescription, $conflicts, $request->input('allergy_justifications', []));
                $this->recordSafetyOverrides($findings, $request, $visit, $consultation);
            }

            if ($isDraft) {
                $consultation->transitionTo(Consultation::STATUS_DRAFT);
                $consultation->releaseLock();

                if ($visit->status !== 'in_consultation') {
                    $visit->updateStatus('in_consultation');
                }

                return;
            }

            $consultation->transitionTo(Consultation::STATUS_READY_FOR_SIGN_OFF);
            $consultation->sign(auth()->id());

            $this->chargeConsultationFee($visit);

            VisitOutcome::create([
                'visit_id' => $visit->id,
                'outcome' => $validated['outcome'],
                'follow_up_date' => $validated['follow_up_date'] ?? null,
                'referral_destination' => $validated['referral_destination'] ?? null,
                'outcome_notes' => $validated['outcome_notes'] ?? null,
                'doctor_id' => auth()->id(),
            ]);

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

            $this->recordFormalDisposition($visit, $validated, $request);

            $newStatus = match ($validated['outcome']) {
                'discharge' => $prescription !== null ? 'waiting_pharmacy' : 'completed',
                'admit' => 'awaiting_admission',
                default => 'completed',
            };

            $visit->update(['closed_at' => now()]);
            $visit->updateStatus($newStatus);

            $consultation->transitionTo(Consultation::STATUS_FINALIZED);
            $consultation->releaseLock();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'consultation_finalized',
                'description' => "Consultation and prescription finalized for visit {$visit->visit_number}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'visit_id' => $visit->id,
                    'consultation_id' => $consultation->id,
                    'outcome' => $validated['outcome'],
                ],
            ]);
        });

        return redirect()->route('consultations.index')
            ->with('success', $isDraft ? 'Draft saved successfully.' : 'Consultation signed and finalized.');
    }

    /**
     * Amend a signed consultation. The original text is preserved in the
     * amendment trail and the note's version is raised.
     */
    public function amend(Request $request, Consultation $consultation)
    {
        if (! $consultation->isSigned()) {
            return back()->withErrors(['amendment' => 'Only a signed consultation can be amended.']);
        }

        if (auth()->id() !== $consultation->signed_by && ! auth()->user()->hasPermission('amend_clinical_notes')) {
            abort(403, 'You are not permitted to amend this consultation.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
            'clinical_notes' => 'nullable|string',
            'diagnoses' => 'nullable|array|min:1',
            'diagnoses.*.code' => 'required_with:diagnoses|exists:icd10_codes,code',
            'diagnoses.*.description' => 'required_with:diagnoses|string',
            'diagnoses.*.type' => 'nullable|string|in:primary,secondary,comorbidity,differential',
            'diagnoses.*.certainty' => 'nullable|string|in:confirmed,probable,possible,rule-out',
            'diagnoses.*.is_primary' => 'nullable|boolean',
        ]);

        if (! empty($validated['diagnoses'])) {
            $this->guardPrimaryDiagnosis($validated['diagnoses']);
        }

        $changes = [];

        DB::transaction(function () use ($consultation, $validated, &$changes) {
            $note = $consultation->notes()->first();
            $oldNote = $note?->content;
            $newNote = $validated['clinical_notes'] ?? null;

            if ($newNote !== null && trim($newNote) !== trim((string) $oldNote)) {
                $changes['clinical_notes'] = [$oldNote, $newNote];
                $this->saveClinicalNote($consultation, $newNote);
            }

            if (! empty($validated['diagnoses'])) {
                $oldSummary = $this->diagnosisSummary($consultation);
                $this->replaceDiagnoses($consultation, $validated['diagnoses']);
                $newSummary = $this->diagnosisSummary($consultation->fresh());

                if ($oldSummary !== $newSummary) {
                    $changes['diagnoses'] = [$oldSummary, $newSummary];
                }
            }

            if ($changes !== []) {
                $consultation->applyAmendment(auth()->id(), $changes, $validated['reason']);
            }
        });

        if ($changes === []) {
            return back()->with('error', 'Nothing was changed, so no amendment was recorded.');
        }

        return redirect()->route('consultations.create', $consultation->visit_id)
            ->with('success', 'Amendment recorded. The note is now at version '.$consultation->fresh()->version.'.');
    }

    /**
     * Record the formal paperwork behind a DAMA or a death in care. Both carry
     * legal weight, so they are stored as their own records rather than as a
     * note on the visit outcome.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function recordFormalDisposition(Visit $visit, array $validated, Request $request): void
    {
        if ($validated['outcome'] === 'dama') {
            DamaRecord::updateOrCreate(
                ['visit_id' => $visit->id],
                [
                    'reason_given' => $validated['dama_reason_given'] ?? null,
                    'risks_explained' => $validated['dama_risks_explained'],
                    'patient_has_capacity' => $request->boolean('dama_patient_has_capacity', true),
                    'patient_signature_obtained' => $request->boolean('dama_patient_signature_obtained'),
                    'witness_name' => $validated['dama_witness_name'],
                    'witness_relationship' => $validated['dama_witness_relationship'] ?? null,
                    'counselled_by' => auth()->id(),
                    'recorded_by' => auth()->id(),
                    'left_at' => now(),
                ]
            );

            $this->releaseBedIfAdmitted($visit);

            return;
        }

        if ($validated['outcome'] !== 'deceased') {
            return;
        }

        $manner = $validated['death_manner'];

        DeathRecord::updateOrCreate(
            ['visit_id' => $visit->id],
            [
                'patient_id' => $visit->patient_id,
                'died_at' => $validated['death_died_at'],
                'place_of_death' => $validated['death_place'] ?? null,
                'immediate_cause' => $validated['death_immediate_cause'],
                'underlying_cause' => $validated['death_underlying_cause'] ?? null,
                'contributing_factors' => $validated['death_contributing_factors'] ?? null,
                'manner' => $manner,
                // An unnatural death is not the treating team's to certify alone.
                'referred_to_coroner' => in_array($manner, DeathRecord::CORONER_MANNERS, true),
                'autopsy_requested' => $request->boolean('death_autopsy_requested'),
                'certificate_number' => $validated['death_certificate_number'] ?? null,
                'certified_by' => auth()->id(),
                'recorded_by' => auth()->id(),
            ]
        );

        $this->releaseBedIfAdmitted($visit);
    }

    /**
     * Close an open admission and free the bed. A patient who has died or left
     * against advice must not keep occupying a bed on the board.
     */
    protected function releaseBedIfAdmitted(Visit $visit): void
    {
        $admission = $visit->admission()->where('status', 'admitted')->first();

        if ($admission === null) {
            return;
        }

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => now(),
            'discharged_by' => auth()->id(),
        ]);

        $admission->bed?->update(['status' => 'cleaning']);
    }

    /**
     * Bill the consultation fee once the encounter is signed. Drafts are not
     * charged, so a clinician can save progress without creating a bill.
     */
    protected function chargeConsultationFee(Visit $visit): void
    {
        $service = Service::where('code', 'CONS-GEN')->first();

        if ($service === null) {
            return;
        }

        Invoice::addCharge(
            $visit,
            $service->name,
            'Consultation',
            (float) $service->price,
            $service->is_nhis_covered,
        );
    }

    /**
     * Validation rules for saving a consultation.
     *
     * @return array<string, string>
     */
    protected function rules(bool $isDraft): array
    {
        return [
            // A draft is a work in progress: nothing is compulsory. Completeness is
            // only demanded at sign-off, where the note becomes a clinical record.
            'clinical_notes' => $isDraft ? 'nullable|string' : 'required|string',
            'diagnoses' => $isDraft ? 'nullable|array' : 'required|array|min:1',
            'diagnoses.*.code' => $isDraft ? 'nullable|exists:icd10_codes,code' : 'required|exists:icd10_codes,code',
            'diagnoses.*.description' => $isDraft ? 'nullable|string' : 'required|string',
            'diagnoses.*.is_primary' => 'nullable|boolean',
            'diagnoses.*.type' => 'nullable|string|in:primary,secondary,comorbidity,differential',
            'diagnoses.*.certainty' => 'nullable|string|in:confirmed,probable,possible,rule-out',
            'prescription_items' => 'nullable|array',
            'prescription_items.*.drug_id' => $isDraft ? 'nullable|exists:drugs,id' : 'required|exists:drugs,id',
            'prescription_items.*.chronic_medication_id' => 'nullable|integer|exists:patient_chronic_medications,id',
            'prescription_items.*.dose' => $isDraft ? 'nullable|string' : 'required|string',
            'prescription_items.*.frequency' => $isDraft ? 'nullable|string' : 'required|string',
            'prescription_items.*.duration' => $isDraft ? 'nullable|integer|min:1' : 'required|integer|min:1',
            'prescription_items.*.quantity' => $isDraft ? 'nullable|integer|min:1' : 'required|integer|min:1',
            'prescription_items.*.route' => 'nullable|string',
            'prescription_items.*.instructions' => 'nullable|string',
            'pregnancy_status' => 'nullable|string|in:none,pregnant,unknown',
            'lmp' => 'nullable|date',
            'edd' => 'nullable|date',
            'gestational_age' => 'nullable|string',
            'lactation_status' => 'nullable|string|in:none,breastfeeding,unknown',
            'allergy_justifications' => 'nullable|array',
            'allergy_justifications.*' => 'nullable|string',
            'safety_justifications' => 'nullable|array',
            'safety_justifications.*' => 'nullable|string',
            'renal_impairment' => 'nullable|string|in:none,mild,moderate,severe',
            'hepatic_impairment' => 'nullable|string|in:none,mild,moderate,severe',
            'egfr' => 'nullable|numeric|min:0|max:250',
            'weight_kg' => 'nullable|numeric|min:0.5|max:400',
            'outcome' => $isDraft ? 'nullable|in:discharge,admit,refer,dama,deceased' : 'required|in:discharge,admit,refer,dama,deceased',
            'follow_up_date' => 'nullable|date|after:today',
            'referral_destination' => 'nullable|string',
            'outcome_notes' => 'nullable|string',
            'referral_type' => 'required_if:outcome,refer|nullable|in:internal,external',
            'referral_to_department_id' => 'required_if:referral_type,internal|nullable|exists:departments,id',
            'referral_external_facility_name' => 'required_if:referral_type,external|nullable|string',
            'referral_external_facility_contact' => 'nullable|string',
            'referral_urgency' => 'nullable|in:routine,urgent,emergency',

            // Discharge Against Medical Advice
            'dama_reason_given' => 'nullable|string',
            'dama_risks_explained' => 'required_if:outcome,dama|nullable|string|min:10',
            'dama_witness_name' => 'required_if:outcome,dama|nullable|string|max:255',
            'dama_witness_relationship' => 'nullable|string|max:255',
            'dama_patient_has_capacity' => 'nullable|boolean',
            'dama_patient_signature_obtained' => 'nullable|boolean',

            // Death in care
            'death_died_at' => 'required_if:outcome,deceased|nullable|date|before_or_equal:now',
            'death_place' => 'nullable|string|max:255',
            'death_immediate_cause' => 'required_if:outcome,deceased|nullable|string|max:255',
            'death_underlying_cause' => 'nullable|string|max:255',
            'death_contributing_factors' => 'nullable|string',
            'death_manner' => 'required_if:outcome,deceased|nullable|in:natural,accident,suicide,homicide,undetermined',
            'death_autopsy_requested' => 'nullable|boolean',
            'death_certificate_number' => 'nullable|string|max:255',
        ];
    }

    /**
     * A consultation cannot be signed without exactly one primary diagnosis.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     *
     * @throws ValidationException
     */
    protected function guardPrimaryDiagnosis(array $diagnoses): void
    {
        $primaryCount = collect($diagnoses)
            ->filter(fn ($diag) => $this->resolveDiagnosisType($diag) === Diagnosis::TYPE_PRIMARY)
            ->count();

        if ($primaryCount === 1) {
            return;
        }

        throw ValidationException::withMessages([
            'diagnoses' => $primaryCount === 0
                ? 'Exactly one primary diagnosis is required to sign this consultation.'
                : 'Only one diagnosis may be marked as the primary diagnosis.',
        ]);
    }

    /**
     * Serious safety findings do not hard-stop prescribing outright, but they
     * cannot be passed without a written clinical justification.
     *
     * Allergy justifications stay keyed by drug id, which is what the prescribing
     * form has always submitted; the other categories are keyed by finding.
     *
     * @param  list<array<string, mixed>>  $findings
     * @param  array<int|string, string|null>  $allergyJustifications
     * @param  array<string, string|null>  $safetyJustifications
     *
     * @throws ValidationException
     */
    protected function guardSafetyFindings(array $findings, array $allergyJustifications, array $safetyJustifications): void
    {
        $errors = [];

        foreach ($this->safetyEngine->blocking($findings) as $finding) {
            if ($this->findingIsJustified($finding, $allergyJustifications, $safetyJustifications)) {
                continue;
            }

            $field = $finding['category'] === 'allergy'
                ? 'allergy_justifications.'.$finding['drug_id']
                : 'safety_justifications.'.$finding['key'];

            $errors[$field] = sprintf(
                '%s: %s. %s Record a justification of at least %d characters to proceed.',
                strtoupper($finding['category']),
                $finding['title'],
                $finding['detail'],
                PrescriptionSafetyEngine::JUSTIFICATION_MIN_LENGTH
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Whether the clinician has written an adequate justification for a finding.
     *
     * @param  array<string, mixed>  $finding
     * @param  array<int|string, string|null>  $allergyJustifications
     * @param  array<string, string|null>  $safetyJustifications
     */
    protected function findingIsJustified(array $finding, array $allergyJustifications, array $safetyJustifications): bool
    {
        if ($finding['category'] === 'allergy') {
            return $this->safetyEngine->isJustified($allergyJustifications[$finding['drug_id']] ?? null);
        }

        return $this->safetyEngine->isJustified($safetyJustifications[$finding['key']] ?? null);
    }

    /**
     * Keep a record of every non-allergy safety warning the clinician prescribed
     * through. Allergy overrides already have their own table.
     *
     * @param  list<array<string, mixed>>  $findings
     */
    protected function recordSafetyOverrides(array $findings, Request $request, Visit $visit, ?Consultation $consultation): void
    {
        $safetyJustifications = $request->input('safety_justifications', []);

        foreach ($findings as $finding) {
            if ($finding['category'] === 'allergy') {
                continue;
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'prescription_safety_warning',
                'description' => substr($finding['title'], 0, 250),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'visit_id' => $visit->id,
                    'consultation_id' => $consultation?->id,
                    'category' => $finding['category'],
                    'severity' => $finding['severity'],
                    'drug_id' => $finding['drug_id'],
                    'detail' => $finding['detail'],
                    'required_justification' => $finding['requires_justification'],
                    'justification' => $safetyJustifications[$finding['key']] ?? null,
                ],
            ]);
        }
    }

    /**
     * Allergy conflicts for the drugs on this prescription.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function allergyConflicts(Visit $visit, array $items): array
    {
        $drugIds = collect($items)->pluck('drug_id')->filter()->unique();

        if ($drugIds->isEmpty()) {
            return [];
        }

        return $this->allergyEngine->check($visit->patient, Drug::whereIn('id', $drugIds)->get());
    }

    /**
     * Chronic medications referenced by the submitted items, keyed by id.
     *
     * Ownership is checked here so a medication belonging to another patient can
     * never be attached to this prescription.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, PatientChronicMedication>
     *
     * @throws ValidationException
     */
    protected function resolveChronicMedications(Visit $visit, array $items)
    {
        $ids = collect($items)->pluck('chronic_medication_id')->filter()->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        $medications = PatientChronicMedication::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($medications as $medication) {
            if ($medication->patient_id !== $visit->patient_id) {
                throw ValidationException::withMessages([
                    'prescription_items' => 'A continued medication does not belong to this patient.',
                ]);
            }
        }

        return $medications;
    }

    /**
     * Write the clinical note, leaving an existing note untouched when nothing
     * was submitted.
     */
    protected function saveClinicalNote(Consultation $consultation, ?string $content): void
    {
        if ($content === null || trim($content) === '') {
            return;
        }

        ClinicalNote::updateOrCreate(
            ['consultation_id' => $consultation->id],
            ['content' => $content]
        );
    }

    /**
     * Replace the consultation's diagnoses with the submitted set.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     */
    protected function replaceDiagnoses(Consultation $consultation, array $diagnoses): void
    {
        $consultation->diagnoses()->delete();

        foreach ($diagnoses as $diag) {
            $type = $this->resolveDiagnosisType($diag);

            Diagnosis::create([
                'consultation_id' => $consultation->id,
                'icd10_code' => $diag['code'],
                'description' => $diag['description'] ?? '',
                'type' => $type,
                'certainty' => $diag['certainty'] ?? 'confirmed',
                // Kept in step with `type` so both representations agree.
                'is_primary' => $type === Diagnosis::TYPE_PRIMARY,
            ]);
        }
    }

    /**
     * Rows complete enough to store.
     *
     * A diagnosis needs an ICD-10 code to mean anything; while drafting, the
     * clinical detail may not have been typed yet, so it is allowed to be blank.
     *
     * @param  array<int, array<string, mixed>>  $diagnoses
     * @return array<int, array<string, mixed>>
     */
    protected function storableDiagnoses(array $diagnoses, bool $isDraft): array
    {
        if (! $isDraft) {
            return $diagnoses;
        }

        return array_values(array_filter(
            $diagnoses,
            fn ($diagnosis) => ! empty($diagnosis['code'])
        ));
    }

    /**
     * A prescription row needs a drug; dose, frequency and duration can still be
     * missing on a draft and are filled in before sign-off.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function storablePrescriptionItems(array $items, bool $isDraft): array
    {
        if (! $isDraft) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn ($item) => ! empty($item['drug_id'])
        ));
    }

    /**
     * The diagnosis type for a submitted row. An explicit type wins; otherwise it
     * is inferred from the legacy `is_primary` flag.
     *
     * @param  array<string, mixed>  $diag
     */
    protected function resolveDiagnosisType(array $diag): string
    {
        if (! empty($diag['type'])) {
            return $diag['type'];
        }

        return filter_var($diag['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? Diagnosis::TYPE_PRIMARY
            : Diagnosis::TYPE_SECONDARY;
    }

    /**
     * Create or update the visit's draft prescription to match the submitted
     * items. Returns null when nothing is prescribed.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, PatientChronicMedication>  $chronicMedications
     */
    protected function syncPrescription(Visit $visit, array $items, $chronicMedications, bool $isDraft): ?Prescription
    {
        $draft = Prescription::where('visit_id', $visit->id)->where('status', 'draft')->latest('id')->first();

        if ($items === []) {
            // The clinician cleared the plan; drop the draft rather than leaving a stale one.
            $draft?->items()->delete();
            $draft?->delete();

            return null;
        }

        $prescription = $draft ?: Prescription::create([
            'visit_id' => $visit->id,
            'doctor_id' => auth()->id(),
            'status' => 'draft',
        ]);

        $prescription->items()->delete();

        foreach ($items as $item) {
            $drug = Drug::find($item['drug_id']);
            $chronic = $chronicMedications->get($item['chronic_medication_id'] ?? null);

            PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'drug_id' => $drug->id,
                'chronic_medication_id' => $chronic?->id,
                // Blank on a draft; validation demands all of them at sign-off.
                'dose' => $item['dose'] ?? '',
                'route' => $item['route'] ?? $chronic?->route ?? 'Oral',
                'frequency' => $item['frequency'] ?? '',
                'duration_days' => $item['duration'] ?? 0,
                'quantity_to_dispense' => $item['quantity'] ?? 0,
                'nhis_covered_snapshot' => $drug->is_nhis_covered,
                'instructions' => $item['instructions'] ?? null,
            ]);
        }

        if (! $isDraft) {
            $prescription->update(['status' => 'active']);
        }

        return $prescription;
    }

    /**
     * Record every allergy conflict the clinician was shown, and whether they
     * overrode it with a justification.
     *
     * @param  list<array<string, mixed>>  $conflicts
     * @param  array<int|string, string|null>  $justifications
     */
    protected function recordAllergyChecks(Prescription $prescription, array $conflicts, array $justifications): void
    {
        $prescription->allergyChecks()->delete();

        foreach ($conflicts as $conflict) {
            $justification = $justifications[$conflict['drug_id']] ?? null;

            AllergyConflictCheck::create([
                'prescription_id' => $prescription->id,
                'allergy_found' => $conflict['allergen'],
                'drug_prescribed' => $conflict['drug_name'],
                'match_type' => $conflict['match_type'],
                'severity' => $conflict['severity'],
                'was_overridden' => $this->allergyEngine->isJustified($justification),
                'doctor_justification' => $justification,
                'doctor_id' => auth()->id(),
            ]);
        }
    }

    /**
     * A stable one-line rendering of the current diagnoses, used to show what an
     * amendment changed.
     */
    protected function diagnosisSummary(Consultation $consultation): string
    {
        return $consultation->diagnoses()
            ->orderBy('icd10_code')
            ->get()
            ->map(fn (Diagnosis $d) => "{$d->icd10_code} ({$d->type}/{$d->certainty}): {$d->description}")
            ->implode('; ');
    }

    /**
     * Prescription items already saved against this visit's draft, so re-opening
     * a draft shows the plan as it was left.
     *
     * @return EloquentCollection<int, PrescriptionItem>
     */
    protected function existingPrescriptionItems(Visit $visit)
    {
        $prescription = Prescription::where('visit_id', $visit->id)
            ->whereIn('status', ['draft', 'active'])
            ->latest('id')
            ->first();

        return $prescription
            ? $prescription->items()->with('drug')->get()
            : new EloquentCollection;
    }
}
