<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function create(Patient $patient)
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized.');
        }

        $departments = Department::where('is_active', true)->get();

        return view('visits.create', compact('patient', 'departments'));
    }

    public function store(Request $request, Patient $patient)
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'type' => 'required|string',
            'priority' => 'required|in:routine,urgent,emergency',
            'payment_method' => 'required|string',
            'reason_for_visit' => 'nullable|string',
            'nhis_verified' => 'nullable|boolean',
        ]);

        $isNhis = $validated['payment_method'] == 'nhis';
        $nhisVerified = $isNhis && $request->boolean('nhis_verified');

        $queueNumber = Visit::nextQueueNumber($validated['department_id']);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'department_id' => $validated['department_id'],
            'visit_number' => Visit::generateVisitNumber(),
            'queue_number' => $queueNumber,
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'payment_method' => $validated['payment_method'],
            'nhis_number_snapshot' => $isNhis ? $patient->nhis_number : null,
            'nhis_expiry_snapshot' => $isNhis ? $patient->nhis_expiry : null,
            'nhis_verified' => $nhisVerified,
            'nhis_verified_by' => $nhisVerified ? auth()->id() : null,
            'nhis_verified_at' => $nhisVerified ? now() : null,
            'created_by' => auth()->id(),
            'reason_for_visit' => $validated['reason_for_visit'] ?? null,
            'status' => 'waiting_triage',
        ]);

        // Initial status log
        $visit->updateStatus('waiting_triage');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'visit_created',
            'description' => "Created visit {$visit->visit_number} (Queue #{$queueNumber}) for patient {$patient->hospital_id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['visit_id' => $visit->id, 'patient_id' => $patient->id, 'department_id' => $visit->department_id],
        ]);

        return redirect()->route('patients.show', $patient)
            ->with('success', "Attendance registered successfully. Visit No: {$visit->visit_number} | Queue: {$visit->department->name} #{$queueNumber}");
    }
}
