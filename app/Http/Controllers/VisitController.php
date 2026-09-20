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
        ]);

        $queueNumber = $this->getNextQueueNumber($validated['department_id']);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'department_id' => $validated['department_id'],
            'visit_number' => $this->generateVisitNumber(),
            'queue_number' => $queueNumber,
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'payment_method' => $validated['payment_method'],
            'nhis_number_snapshot' => ($validated['payment_method'] == 'nhis') ? $patient->nhis_number : null,
            'nhis_expiry_snapshot' => ($validated['payment_method'] == 'nhis') ? $patient->nhis_expiry : null,
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

    protected function getNextQueueNumber($departmentId)
    {
        return Visit::where('department_id', $departmentId)
            ->whereDate('created_at', date('Y-m-d'))
            ->count() + 1;
    }

    protected function generateVisitNumber()
    {
        $prefix = 'V';
        $date = date('Ymd');
        $count = Visit::whereDate('created_at', date('Y-m-d'))->count() + 1;

        return "{$prefix}-{$date}-".str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
