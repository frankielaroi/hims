<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TriageAssessment;
use App\Models\Visit;
use App\Models\VitalsRecord;
use App\Services\EtatTriage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TriageController extends Controller
{
    public function __construct(protected EtatTriage $etat) {}

    public function index()
    {
        // View the triage queue
        $visits = Visit::with(['patient', 'department'])
            ->where('status', 'waiting_triage')
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('triage.index', compact('visits'));
    }

    public function create(Visit $visit)
    {
        $visit->load(['patient', 'vitals', 'triageAssessments']);

        $usesEtat = $this->etat->appliesTo($visit->patient);

        return view('triage.create', compact('visit', 'usesEtat'));
    }

    public function store(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'temperature' => 'nullable|numeric|between:30,45',
            'systolic_bp' => 'nullable|integer|between:40,300',
            'diastolic_bp' => 'nullable|integer|between:20,200',
            'pulse_rate' => 'nullable|integer|between:20,300',
            'respiratory_rate' => 'nullable|integer|between:5,100',
            'spo2' => 'nullable|integer|between:30,100',
            'blood_glucose' => 'nullable|numeric|between:0.5,50',
            'weight' => 'nullable|numeric|between:0.5,500',
            'height' => 'nullable|numeric|between:10,300',
            'muac_cm' => 'nullable|numeric|between:5,50',
            'avpu' => 'nullable|in:alert,voice,pain,unresponsive',
            'chief_complaint' => 'required|string',
            'history_presenting_illness' => 'nullable|string',
            'priority_level' => 'required|in:routine,urgent,emergency',
            'etat_emergency_signs' => 'nullable|array',
            'etat_emergency_signs.*' => [Rule::in(array_keys(EtatTriage::EMERGENCY_SIGNS))],
            'etat_priority_signs' => 'nullable|array',
            'etat_priority_signs.*' => [Rule::in(array_keys(EtatTriage::PRIORITY_SIGNS))],
        ]);

        $usesEtat = $this->etat->appliesTo($visit->patient);

        $emergencySigns = $usesEtat ? array_values($validated['etat_emergency_signs'] ?? []) : [];
        $prioritySigns = $usesEtat ? array_values($validated['etat_priority_signs'] ?? []) : [];

        // Hypoglycaemia is common and treatable in exactly these children, and
        // is missed unless somebody measures it.
        if ($this->etat->requiresBloodGlucose($emergencySigns) && ($validated['blood_glucose'] ?? null) === null) {
            return back()->withInput()->withErrors([
                'blood_glucose' => 'Record a blood glucose for a child with coma or convulsions before completing triage.',
            ]);
        }

        // The signs decide the priority. They may raise what the nurse chose,
        // never lower it.
        $priority = $this->etat->mostUrgent(
            $validated['priority_level'],
            $this->etat->priorityFor($emergencySigns, $prioritySigns)
        );

        // 1. Record Vitals
        VitalsRecord::create([
            'visit_id' => $visit->id,
            'temperature' => $validated['temperature'] ?? null,
            'systolic_bp' => $validated['systolic_bp'] ?? null,
            'diastolic_bp' => $validated['diastolic_bp'] ?? null,
            'pulse_rate' => $validated['pulse_rate'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'spo2' => $validated['spo2'] ?? null,
            'blood_glucose' => $validated['blood_glucose'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'muac_cm' => $validated['muac_cm'] ?? null,
            'avpu' => $validated['avpu'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        // 2. Record Triage Assessment
        TriageAssessment::create([
            'visit_id' => $visit->id,
            'chief_complaint' => $validated['chief_complaint'],
            'history_presenting_illness' => $validated['history_presenting_illness'] ?? null,
            'priority_level' => $priority,
            'etat_emergency_signs' => $emergencySigns,
            'etat_priority_signs' => $prioritySigns,
            'nurse_id' => auth()->id(),
        ]);

        // 3. Update Visit Status & Priority
        $visit->update([
            'priority' => $priority,
        ]);
        $visit->updateStatus('waiting_doctor');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'triage_completed',
            'description' => "Triage completed for visit {$visit->visit_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $visit->id,
                'priority' => $priority,
                'priority_chosen' => $validated['priority_level'],
                'etat_signs' => $this->etat->describe($emergencySigns, $prioritySigns),
            ],
        ]);

        $message = "Triage for {$visit->patient->full_name} completed. Sent to Doctor.";

        if ($emergencySigns !== []) {
            return redirect()->route('triage.index')->with(
                'warning',
                $message.' ETAT emergency signs found — treat now: '.implode('; ', $this->etat->describe($emergencySigns, [])).'.'
            );
        }

        return redirect()->route('triage.index')->with('success', $message);
    }
}
