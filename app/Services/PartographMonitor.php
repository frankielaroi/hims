<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\PartographEntry;

/**
 * The partograph's alert and action lines, worked out from the readings.
 *
 * This is the classic WHO partograph Ghana teaches and audits: in the active
 * phase the cervix is expected to open at about a centimetre an hour, the alert
 * line tracks that rate, and the action line sits four hours to its right.
 * Falling behind the alert line means reassess and consider referral; crossing
 * the action line means a decision cannot wait.
 *
 * Nothing is stored. Correct a reading and the lines move with it.
 */
class PartographMonitor
{
    /**
     * The active phase of the first stage begins here.
     */
    public const ACTIVE_PHASE_CM = 4;

    public const FULL_DILATATION_CM = 10;

    /**
     * Expected progress in the active phase, in centimetres per hour.
     */
    public const EXPECTED_CM_PER_HOUR = 1.0;

    /**
     * The action line sits this many hours to the right of the alert line.
     */
    public const ACTION_LINE_HOURS = 4.0;

    public const STATUS_LATENT = 'latent_phase';

    public const STATUS_ON_TRACK = 'on_track';

    public const STATUS_ALERT = 'alert';

    public const STATUS_ACTION = 'action';

    /**
     * Where this labour sits against the alert and action lines.
     *
     * @return array{status: string, hours_behind: float|null, expected_cm: float|null, latest_cm: int|null, message: string}
     */
    public function progressFor(Delivery $delivery): array
    {
        $entries = $delivery->partographEntries()
            ->whereNotNull('cervical_dilatation_cm')
            ->orderBy('recorded_at')
            ->get();

        $activePhaseStart = $entries->first(
            fn (PartographEntry $entry) => $entry->cervical_dilatation_cm >= self::ACTIVE_PHASE_CM
        );

        $latest = $entries->last();

        if ($activePhaseStart === null || $latest === null) {
            return [
                'status' => self::STATUS_LATENT,
                'hours_behind' => null,
                'expected_cm' => null,
                'latest_cm' => $latest?->cervical_dilatation_cm,
                'message' => 'Labour has not reached the active phase ('.self::ACTIVE_PHASE_CM.' cm), so the alert line does not apply yet.',
            ];
        }

        $hoursElapsed = $activePhaseStart->recorded_at->floatDiffInHours($latest->recorded_at);

        $expected = min(
            self::FULL_DILATATION_CM,
            $activePhaseStart->cervical_dilatation_cm + ($hoursElapsed * self::EXPECTED_CM_PER_HOUR)
        );

        $hoursBehind = round($expected - $latest->cervical_dilatation_cm, 1);

        if ($hoursBehind <= 0) {
            return [
                'status' => self::STATUS_ON_TRACK,
                'hours_behind' => 0.0,
                'expected_cm' => $expected,
                'latest_cm' => $latest->cervical_dilatation_cm,
                'message' => 'Progress is at or ahead of the alert line.',
            ];
        }

        if ($hoursBehind >= self::ACTION_LINE_HOURS) {
            return [
                'status' => self::STATUS_ACTION,
                'hours_behind' => $hoursBehind,
                'expected_cm' => $expected,
                'latest_cm' => $latest->cervical_dilatation_cm,
                'message' => 'Action line crossed — '.$hoursBehind.' hours behind expected progress. Obstetric review now; decide on augmentation, referral or caesarean.',
            ];
        }

        return [
            'status' => self::STATUS_ALERT,
            'hours_behind' => $hoursBehind,
            'expected_cm' => $expected,
            'latest_cm' => $latest->cervical_dilatation_cm,
            'message' => 'Alert line crossed — '.$hoursBehind.' hours behind expected progress. Reassess and consider referral or augmentation.',
        ];
    }

    /**
     * Every warning across the labour, newest reading first.
     *
     * @return list<string>
     */
    public function warningsFor(Delivery $delivery): array
    {
        $warnings = [];

        foreach ($delivery->partographEntries()->orderByDesc('recorded_at')->get() as $entry) {
            foreach ($entry->warnings() as $warning) {
                $warnings[] = $entry->recorded_at->format('d M H:i').' — '.$warning;
            }
        }

        return $warnings;
    }
}
