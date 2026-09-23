<?php

namespace App\Services;

use App\Models\Patient;

/**
 * WHO Emergency Triage Assessment and Treatment, as Ghana applies it to
 * children under five.
 *
 * ETAT sorts a sick child into three groups by looking for named signs rather
 * than by overall impression: emergency signs need treatment now, priority
 * signs go to the front of the queue, and everyone else waits their turn. The
 * priority therefore follows from the signs — it is not a separate judgement.
 */
class EtatTriage
{
    /**
     * ETAT is a protocol for young children. Older children and adults keep the
     * ordinary triage categories.
     */
    public const MAX_AGE_YEARS = 5;

    /**
     * Emergency signs, in the ABCD order they are looked for. Any one of these
     * means treatment before anything else, including registration.
     *
     * @var array<string, string>
     */
    public const EMERGENCY_SIGNS = [
        'airway' => 'Obstructed or absent breathing',
        'severe_respiratory_distress' => 'Severe respiratory distress',
        'central_cyanosis' => 'Central cyanosis',
        'shock' => 'Cold hands with capillary refill over 3 seconds and a weak, fast pulse',
        'coma' => 'Coma or unresponsive (AVPU: P or U)',
        'convulsing' => 'Convulsing now',
        'severe_dehydration' => 'Diarrhoea with severe dehydration (lethargy, sunken eyes, very slow skin pinch)',
    ];

    /**
     * Priority signs — the 3TPR MOB list. These are seen ahead of the queue.
     *
     * @var array<string, string>
     */
    public const PRIORITY_SIGNS = [
        'tiny_baby' => 'Tiny baby (under 2 months)',
        'temperature' => 'Very high temperature',
        'trauma' => 'Trauma or another urgent surgical condition',
        'pallor' => 'Severe pallor',
        'poisoning' => 'Poisoning',
        'pain' => 'Severe pain',
        'respiratory_distress' => 'Respiratory distress',
        'restless' => 'Restless, irritable or lethargic',
        'referral' => 'Urgently referred',
        'malnutrition' => 'Visible severe wasting',
        'oedema' => 'Oedema of both feet',
        'burns' => 'Major burns',
    ];

    /**
     * Signs after which blood glucose must be measured. Hypoglycaemia is common
     * and treatable in exactly these children, and is missed without a reading.
     *
     * @var list<string>
     */
    public const SIGNS_REQUIRING_GLUCOSE = ['coma', 'convulsing'];

    /**
     * Triage levels from least to most urgent, so two can be compared.
     *
     * @var list<string>
     */
    public const PRIORITY_RANK = ['routine', 'urgent', 'emergency'];

    public function appliesTo(Patient $patient): bool
    {
        return $patient->age !== null && $patient->age < self::MAX_AGE_YEARS;
    }

    /**
     * The priority the recorded signs imply, or null when none were ticked.
     *
     * @param  list<string>  $emergencySigns
     * @param  list<string>  $prioritySigns
     */
    public function priorityFor(array $emergencySigns, array $prioritySigns): ?string
    {
        if ($emergencySigns !== []) {
            return 'emergency';
        }

        if ($prioritySigns !== []) {
            return 'urgent';
        }

        return null;
    }

    /**
     * The more urgent of two levels.
     *
     * The signs may raise the nurse's chosen priority but never lower it: a
     * nurse who judges a child an emergency for a reason ETAT does not list is
     * not overruled by a protocol.
     */
    public function mostUrgent(string $chosen, ?string $derived): string
    {
        if ($derived === null) {
            return $chosen;
        }

        $chosenRank = array_search($chosen, self::PRIORITY_RANK, true);
        $derivedRank = array_search($derived, self::PRIORITY_RANK, true);

        return $derivedRank > $chosenRank ? $derived : $chosen;
    }

    /**
     * Whether a blood glucose reading is required for the signs recorded.
     *
     * @param  list<string>  $emergencySigns
     */
    public function requiresBloodGlucose(array $emergencySigns): bool
    {
        return array_intersect($emergencySigns, self::SIGNS_REQUIRING_GLUCOSE) !== [];
    }

    /**
     * The signs found, as readable text for the record and the audit log.
     *
     * @param  list<string>  $emergencySigns
     * @param  list<string>  $prioritySigns
     * @return list<string>
     */
    public function describe(array $emergencySigns, array $prioritySigns): array
    {
        $described = [];

        foreach ($emergencySigns as $sign) {
            $described[] = self::EMERGENCY_SIGNS[$sign] ?? $sign;
        }

        foreach ($prioritySigns as $sign) {
            $described[] = self::PRIORITY_SIGNS[$sign] ?? $sign;
        }

        return $described;
    }
}
