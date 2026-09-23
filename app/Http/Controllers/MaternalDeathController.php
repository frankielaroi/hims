<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeathRecord;
use App\Services\MaternalDeathSurveillance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaternalDeathController extends Controller
{
    public function __construct(protected MaternalDeathSurveillance $surveillance) {}

    /**
     * The surveillance board: what must be notified, and what must be reviewed.
     */
    public function index()
    {
        $awaitingNotification = $this->surveillance->awaitingNotification();
        $awaitingReview = $this->surveillance->awaitingReview();

        $candidates = DeathRecord::with('patient')
            ->where('is_maternal_death', false)
            ->where('died_at', '>=', now()->subMonths(6))
            ->get()
            ->filter(fn (DeathRecord $record) => $this->surveillance->suggestedTiming(
                $record->patient,
                $record->died_at
            ) !== null);

        return view('maternal-deaths.index', compact('awaitingNotification', 'awaitingReview', 'candidates'));
    }

    /**
     * Confirm a death as maternal, or set it aside.
     *
     * Nothing classifies a death automatically: WHO excludes accidental and
     * incidental causes, which only the certifying clinician can judge.
     */
    public function classify(Request $request, DeathRecord $deathRecord)
    {
        $validated = $request->validate([
            'is_maternal_death' => 'required|boolean',
            'maternal_death_timing' => [
                'nullable',
                Rule::requiredIf((bool) $request->input('is_maternal_death')),
                Rule::in(DeathRecord::MATERNAL_TIMINGS),
            ],
        ]);

        $deathRecord->update([
            'is_maternal_death' => (bool) $validated['is_maternal_death'],
            'maternal_death_timing' => $validated['is_maternal_death']
                ? $validated['maternal_death_timing']
                : null,
        ]);

        $this->audit($request, 'maternal_death_classified', $deathRecord, [
            'is_maternal_death' => $deathRecord->is_maternal_death,
            'timing' => $deathRecord->maternal_death_timing,
        ]);

        if (! $deathRecord->is_maternal_death) {
            return back()->with('success', 'Recorded as not a maternal death.');
        }

        return back()->with(
            'warning',
            'Classified as a maternal death. It must be notified within '
            .DeathRecord::NOTIFICATION_DEADLINE_HOURS.' hours of death.'
        );
    }

    /**
     * Record that the death has been notified to the district.
     */
    public function notify(Request $request, DeathRecord $deathRecord)
    {
        abort_unless($deathRecord->is_maternal_death, 422, 'Only a maternal death is notified through this route.');
        abort_if($deathRecord->isNotified(), 422, 'This death has already been notified.');

        $validated = $request->validate([
            'notified_at' => 'nullable|date|before_or_equal:now',
        ]);

        $deathRecord->update([
            'notified_at' => $validated['notified_at'] ?? now(),
            'notified_by' => auth()->id(),
        ]);

        $wasLate = $deathRecord->died_at
            ->copy()
            ->addHours(DeathRecord::NOTIFICATION_DEADLINE_HOURS)
            ->lt($deathRecord->notified_at);

        $this->audit($request, 'maternal_death_notified', $deathRecord, ['was_late' => $wasLate]);

        return back()->with(
            $wasLate ? 'warning' : 'success',
            $wasLate
                ? 'Notification recorded, but it fell outside the 24-hour window. That delay is itself a review finding.'
                : 'Notification recorded. The review is still outstanding.'
        );
    }

    /**
     * Close the review with what it found.
     */
    public function review(Request $request, DeathRecord $deathRecord)
    {
        abort_unless($deathRecord->is_maternal_death, 422, 'Only a maternal death is reviewed through this route.');
        abort_unless($deathRecord->isNotified(), 422, 'Notify the death before recording the review.');

        $validated = $request->validate([
            'review_findings' => 'required|string|min:20',
        ]);

        $deathRecord->update([
            'reviewed_at' => now(),
            'review_findings' => $validated['review_findings'],
        ]);

        $this->audit($request, 'maternal_death_reviewed', $deathRecord, []);

        return back()->with('success', 'Maternal death review recorded.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function audit(Request $request, string $action, DeathRecord $deathRecord, array $extra): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => "Maternal death surveillance for {$deathRecord->patient->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge([
                'death_record_id' => $deathRecord->id,
                'patient_id' => $deathRecord->patient_id,
            ], $extra),
        ]);
    }
}
