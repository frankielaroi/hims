<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\DeathRecord;
use App\Models\Delivery;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Maternal Death Surveillance and Response.
 *
 * WHO counts a death as maternal when a woman dies while pregnant, or within 42
 * days of the pregnancy ending, from a cause related to or aggravated by the
 * pregnancy — and NOT from accidental or incidental causes.
 *
 * That last clause is a clinical judgement, so nothing here classifies a death
 * on its own. It finds the deaths that fall inside the window and asks the
 * certifying clinician to decide, which is the part a system can honestly do.
 */
class MaternalDeathSurveillance
{
    /**
     * Whether this death falls in the window that must be considered, and when
     * relative to the pregnancy. Null means nothing in the record suggests it.
     */
    public function suggestedTiming(Patient $patient, Carbon $diedAt): ?string
    {
        if ($patient->sex !== 'female') {
            return null;
        }

        $openLabour = Delivery::where('patient_id', $patient->id)
            ->where('status', Delivery::STATUS_IN_LABOUR)
            ->exists();

        if ($openLabour) {
            return 'during_delivery';
        }

        $recentDelivery = Delivery::where('patient_id', $patient->id)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $diedAt)
            ->where('delivered_at', '>=', $diedAt->copy()->subDays(DeathRecord::MATERNAL_WINDOW_DAYS))
            ->exists();

        if ($recentDelivery) {
            return 'within_42_days';
        }

        $recordedPregnant = Consultation::whereHas(
            'visit',
            fn ($query) => $query->where('patient_id', $patient->id)
        )
            ->where('pregnancy_status', 'pregnant')
            ->where('created_at', '>=', $diedAt->copy()->subMonths(10))
            ->exists();

        if ($recordedPregnant) {
            return 'during_pregnancy';
        }

        return null;
    }

    /**
     * Maternal deaths still waiting to be notified, most urgent first.
     *
     * @return Collection<int, DeathRecord>
     */
    public function awaitingNotification()
    {
        return DeathRecord::with(['patient', 'certifiedBy'])
            ->where('is_maternal_death', true)
            ->whereNull('notified_at')
            ->orderBy('died_at')
            ->get();
    }

    /**
     * Maternal deaths notified but not yet reviewed.
     *
     * @return Collection<int, DeathRecord>
     */
    public function awaitingReview()
    {
        return DeathRecord::with(['patient', 'notifiedBy'])
            ->where('is_maternal_death', true)
            ->whereNotNull('notified_at')
            ->whereNull('reviewed_at')
            ->orderBy('died_at')
            ->get();
    }
}
