<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\TheatreOperation;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TheatreController extends Controller
{
    public function index()
    {
        $active = TheatreOperation::with(['patient', 'surgeon'])
            ->whereIn('status', [TheatreOperation::STATUS_SCHEDULED, TheatreOperation::STATUS_IN_THEATRE])
            ->orderBy('started_at')
            ->get();

        $completed = TheatreOperation::with(['patient', 'surgeon'])
            ->where('status', TheatreOperation::STATUS_COMPLETED)
            ->whereDate('ended_at', today())
            ->latest('ended_at')
            ->get();

        return view('theatre.index', compact('active', 'completed'));
    }

    /**
     * Open an operation record against a visit. A caesarean passes its delivery
     * so the birth episode and the operation stay linked.
     */
    public function store(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'procedure' => 'required|string|max:255',
            'indication' => 'nullable|string',
            'urgency' => ['required', Rule::in(TheatreOperation::URGENCIES)],
            'anaesthesia_type' => ['nullable', Rule::in(TheatreOperation::ANAESTHESIA_TYPES)],
            'delivery_id' => 'nullable|exists:deliveries,id',
            'started_at' => 'nullable|date|before_or_equal:now',
        ]);

        if (! empty($validated['delivery_id'])) {
            $delivery = Delivery::findOrFail($validated['delivery_id']);

            abort_unless($delivery->visit_id === $visit->id, 422, 'That delivery belongs to a different visit.');
        }

        $operation = TheatreOperation::create(array_merge($validated, [
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'started_at' => $validated['started_at'] ?? now(),
            'status' => TheatreOperation::STATUS_IN_THEATRE,
            'recorded_by' => auth()->id(),
        ]));

        $this->audit($request, 'operation_started', $operation);

        return back()->with('success', 'Operation record opened.');
    }

    /**
     * Close the operation with the findings and the counts.
     */
    public function complete(Request $request, TheatreOperation $operation)
    {
        abort_if($operation->status === TheatreOperation::STATUS_COMPLETED, 422, 'This operation is already closed.');

        $validated = $request->validate([
            'ended_at' => 'required|date|before_or_equal:now',
            'surgeon_id' => 'required|exists:users,id',
            'anaesthetist_id' => 'nullable|exists:users,id',
            'scrub_nurse' => 'nullable|string|max:255',
            'findings' => 'nullable|string',
            'procedure_notes' => 'nullable|string',
            'complications' => 'nullable|array',
            'complications.*' => [Rule::in(TheatreOperation::COMPLICATIONS)],
            'estimated_blood_loss_ml' => 'nullable|integer|min:0|max:20000',
            'specimen_sent' => 'nullable|boolean',
            // Deliberately required: an unrecorded swab count is not the same
            // as a correct one, and a retained swab is a never-event.
            'swab_count_correct' => 'required|boolean',
        ]);

        $operation->update(array_merge($validated, [
            'complications' => array_values($validated['complications'] ?? []),
            'specimen_sent' => $request->boolean('specimen_sent'),
            'swab_count_correct' => $request->boolean('swab_count_correct'),
            'status' => TheatreOperation::STATUS_COMPLETED,
        ]));

        $this->audit($request, 'operation_completed', $operation->refresh());

        $alerts = $operation->alerts();

        if ($alerts !== []) {
            return back()->with('warning', 'Operation recorded. '.implode('; ', $alerts).'.');
        }

        return back()->with('success', 'Operation recorded.');
    }

    protected function audit(Request $request, string $action, TheatreOperation $operation): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => substr("{$operation->procedure} for {$operation->patient->full_name}.", 0, 250),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'operation_id' => $operation->id,
                'patient_id' => $operation->patient_id,
                'visit_id' => $operation->visit_id,
                'delivery_id' => $operation->delivery_id,
                'alerts' => $operation->alerts(),
            ],
        ]);
    }
}
