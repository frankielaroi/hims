<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TriageAssessment;
use App\Models\Visit;
use App\Models\VitalsRecord;
use Illuminate\Http\Request;

class TriageController extends Controller
{
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

        return view('triage.create', compact('visit'));
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
            'weight' => 'nullable|numeric|between:0.5,500',
            'height' => 'nullable|numeric|between:10,300',
            'chief_complaint' => 'required|string',
            'history_presenting_illness' => 'nullable|string',
            'priority_level' => 'required|in:routine,urgent,emergency',
        ]);

        // 1. Record Vitals
        VitalsRecord::create([
            'visit_id' => $visit->id,
            'temperature' => $validated['temperature'] ?? null,
            'systolic_bp' => $validated['systolic_bp'] ?? null,
            'diastolic_bp' => $validated['diastolic_bp'] ?? null,
            'pulse_rate' => $validated['pulse_rate'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'spo2' => $validated['spo2'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        // 2. Record Triage Assessment
        TriageAssessment::create([
            'visit_id' => $visit->id,
            'chief_complaint' => $validated['chief_complaint'],
            'history_presenting_illness' => $validated['history_presenting_illness'] ?? null,
            'priority_level' => $validated['priority_level'],
            'nurse_id' => auth()->id(),
        ]);

        // 3. Update Visit Status & Priority
        $visit->update([
            'priority' => $validated['priority_level'],
        ]);
        $visit->updateStatus('waiting_doctor');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'triage_completed',
            'description' => "Triage completed for visit {$visit->visit_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['visit_id' => $visit->id, 'priority' => $validated['priority_level']],
        ]);

        return redirect()->route('triage.index')
            ->with('success', "Triage for {$visit->patient->full_name} completed. Sent to Doctor.");
    }
}
