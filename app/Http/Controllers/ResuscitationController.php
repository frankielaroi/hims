<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ResuscitationRecord;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResuscitationController extends Controller
{
    /**
     * Open a resuscitation record as it starts.
     *
     * It opens with what is known at the door and is closed afterwards, because
     * nobody stops to type during an arrest.
     */
    public function store(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'arrest_type' => ['required', Rule::in(ResuscitationRecord::ARREST_TYPES)],
            'was_witnessed' => 'nullable|boolean',
            'initial_rhythm' => ['nullable', Rule::in(array_keys(ResuscitationRecord::RHYTHMS))],
            'started_at' => 'nullable|date|before_or_equal:now',
            'team_lead_id' => 'nullable|exists:users,id',
        ]);

        $record = ResuscitationRecord::create(array_merge($validated, [
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'started_at' => $validated['started_at'] ?? now(),
            'was_witnessed' => $request->boolean('was_witnessed'),
            'recorded_by' => auth()->id(),
        ]));

        $this->audit($request, 'resuscitation_started', $record);

        return back()->with('warning', 'Resuscitation record opened. Close it with the outcome when the attempt ends.');
    }

    /**
     * Close the record with what was done and how it ended.
     */
    public function complete(Request $request, ResuscitationRecord $resuscitation)
    {
        abort_unless($resuscitation->isOngoing(), 422, 'This resuscitation record is already closed.');

        $validated = $request->validate([
            'ended_at' => 'required|date|before_or_equal:now',
            'interventions' => 'nullable|array',
            'interventions.*' => [Rule::in(array_keys(ResuscitationRecord::INTERVENTIONS))],
            'shocks_delivered' => 'nullable|integer|min:0|max:50',
            'adrenaline_doses' => 'nullable|integer|min:0|max:50',
            'outcome' => ['required', Rule::in(ResuscitationRecord::OUTCOMES)],
            'team_lead_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $resuscitation->update(array_merge($validated, [
            'interventions' => array_values($validated['interventions'] ?? []),
            'team_lead_id' => $validated['team_lead_id'] ?? $resuscitation->team_lead_id,
        ]));

        $this->audit($request, 'resuscitation_completed', $resuscitation->refresh());

        $alerts = $resuscitation->alerts();

        if ($alerts !== []) {
            return back()->with('warning', 'Resuscitation recorded. '.implode('; ', $alerts).'.');
        }

        return back()->with('success', 'Resuscitation recorded.');
    }

    protected function audit(Request $request, string $action, ResuscitationRecord $record): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => "Resuscitation for {$record->patient->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'resuscitation_record_id' => $record->id,
                'patient_id' => $record->patient_id,
                'visit_id' => $record->visit_id,
                'outcome' => $record->outcome,
                'alerts' => $record->alerts(),
            ],
        ]);
    }
}
