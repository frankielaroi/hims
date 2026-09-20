<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    /**
     * The active-visit statuses that put a patient on the emergency board.
     * Mirrors the "not yet closed" set used across the other queue screens.
     */
    protected const ACTIVE_STATUSES = [
        'waiting_triage', 'in_triage', 'waiting_doctor', 'in_consultation',
        'waiting_lab', 'waiting_pharmacy', 'awaiting_admission', 'admitted',
    ];

    public function board()
    {
        $visits = Visit::with(['patient', 'department'])
            ->where('priority', 'emergency')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('created_at')
            ->get();

        return view('emergency.board', compact('visits'));
    }

    public function create()
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized.');
        }

        $emergencyDept = Department::where('slug', 'emergency')->firstOrFail();

        return view('emergency.create', compact('emergencyDept'));
    }

    /**
     * Registers an emergency arrival and starts their visit in one step.
     * Per Ghana_HMS_System_Design_v2 §4.2: "documentation never gates
     * emergency care" — this deliberately skips the search-first flow and
     * full identity requirements that normal registration (Module 2) uses,
     * and supports an unidentified patient outright.
     */
    public function store(Request $request)
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'is_unidentified' => 'nullable|boolean',
            'full_name' => 'required_unless:is_unidentified,1|nullable|string|max:255',
            'apparent_sex' => 'required|in:male,female,other',
            'estimated_age' => 'nullable|integer|min:0',
            'reason_for_visit' => 'required|string',
        ]);

        $isUnidentified = $request->boolean('is_unidentified');

        $patient = Patient::create([
            'hospital_id' => Patient::generateHospitalId(),
            'full_name' => $isUnidentified
                ? 'Unidentified '.ucfirst($validated['apparent_sex']).' ('.now()->format('d M, H:i').')'
                : $validated['full_name'],
            'is_unidentified' => $isUnidentified,
            'sex' => $validated['apparent_sex'],
            'estimated_age' => $validated['estimated_age'] ?? null,
            'is_age_estimated' => true,
            'payment_method' => 'cash',
        ]);

        $emergencyDept = Department::where('slug', 'emergency')->firstOrFail();
        $queueNumber = Visit::nextQueueNumber($emergencyDept->id);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'department_id' => $emergencyDept->id,
            'visit_number' => Visit::generateVisitNumber(),
            'queue_number' => $queueNumber,
            'type' => 'Emergency',
            'priority' => 'emergency',
            'payment_method' => 'cash',
            'created_by' => auth()->id(),
            'reason_for_visit' => $validated['reason_for_visit'],
            'status' => 'waiting_triage',
        ]);

        $visit->updateStatus('waiting_triage');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'emergency_arrival_registered',
            'description' => "Emergency arrival registered: {$patient->full_name} (Visit {$visit->visit_number})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['visit_id' => $visit->id, 'patient_id' => $patient->id, 'is_unidentified' => $isUnidentified],
        ]);

        return redirect()->route('triage.create', $visit)
            ->with('success', "{$patient->full_name} registered. Priority: Emergency. Proceed straight to triage.");
    }
}
