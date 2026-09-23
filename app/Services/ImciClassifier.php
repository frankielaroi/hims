<?php

namespace App\Services;

use App\Models\ImciAssessment;

/**
 * The WHO IMCI classification tables for children aged 2 months to 5 years.
 *
 * IMCI is deliberately mechanical: the clinician records a fixed set of
 * findings, and the classification follows from them without judgement. That is
 * what makes it safe in the hands of a health worker who is not a paediatrician,
 * and it is why this is a table lookup rather than a scoring heuristic.
 *
 * Colours are the chart's own: pink means refer urgently, yellow means treat
 * here, green means advise and send home.
 *
 * Nothing here is invented. Where a threshold is not in the published tables it
 * is not guessed at — the finding is simply not classified.
 */
class ImciClassifier
{
    public const PINK = 'pink';

    public const YELLOW = 'yellow';

    public const GREEN = 'green';

    /**
     * IMCI proper starts at two months; below that the young infant chart
     * applies, which asks different questions and is not implemented here.
     */
    public const MIN_AGE_MONTHS = 2;

    public const MAX_AGE_MONTHS = 60;

    /**
     * Fast breathing thresholds. The cut-off falls as a child grows, which is
     * why a single number cannot be used.
     */
    public const FAST_BREATHING_UNDER_12_MONTHS = 50;

    public const FAST_BREATHING_12_TO_59_MONTHS = 40;

    /**
     * Diarrhoea lasting this long or more is persistent.
     */
    public const PERSISTENT_DIARRHOEA_DAYS = 14;

    public const CHRONIC_EAR_DISCHARGE_DAYS = 14;

    public const MUAC_SEVERE_CM = 11.5;

    public const MUAC_MODERATE_CM = 12.5;

    public function appliesTo(int $ageMonths): bool
    {
        return $ageMonths >= self::MIN_AGE_MONTHS && $ageMonths < self::MAX_AGE_MONTHS;
    }

    /**
     * The respiratory rate at or above which breathing is "fast" for this age.
     */
    public function fastBreathingThreshold(int $ageMonths): int
    {
        return $ageMonths < 12
            ? self::FAST_BREATHING_UNDER_12_MONTHS
            : self::FAST_BREATHING_12_TO_59_MONTHS;
    }

    /**
     * Every classification this assessment yields.
     *
     * @return list<array{condition: string, classification: string, colour: string, action: string}>
     */
    public function classify(ImciAssessment $a): array
    {
        $danger = $this->hasGeneralDangerSign($a);

        return array_values(array_filter(array_merge(
            [$this->dangerSigns($a, $danger)],
            [$this->breathing($a, $danger)],
            $this->diarrhoea($a),
            [$this->fever($a, $danger)],
            [$this->ear($a)],
            [$this->nutrition($a)],
            [$this->anaemia($a)],
        )));
    }

    public function hasGeneralDangerSign(ImciAssessment $a): bool
    {
        return $a->unable_to_drink
            || $a->vomits_everything
            || $a->convulsions
            || $a->lethargic_or_unconscious
            || $a->convulsing_now;
    }

    /**
     * @return array<string, string>|null
     */
    protected function dangerSigns(ImciAssessment $a, bool $danger): ?array
    {
        if (! $danger) {
            return null;
        }

        return $this->row(
            'General danger signs',
            'VERY SEVERE DISEASE',
            self::PINK,
            'Give first dose of an appropriate antibiotic and refer URGENTLY to hospital.'
        );
    }

    /**
     * @return array<string, string>|null
     */
    protected function breathing(ImciAssessment $a, bool $danger): ?array
    {
        if (! $a->has_cough) {
            return null;
        }

        if ($danger || $a->stridor || $a->chest_indrawing) {
            return $this->row(
                'Cough or difficult breathing',
                'SEVERE PNEUMONIA OR VERY SEVERE DISEASE',
                self::PINK,
                'Give first dose of an appropriate antibiotic and refer URGENTLY to hospital.'
            );
        }

        // Without a respiratory rate there is nothing to compare, so the cough
        // is left unclassified rather than assumed harmless.
        if ($a->respiratory_rate === null) {
            return $this->row(
                'Cough or difficult breathing',
                'NOT CLASSIFIED — count the breaths',
                self::YELLOW,
                'Count the respiratory rate for a full minute; the classification depends on it.'
            );
        }

        if ($a->respiratory_rate >= $this->fastBreathingThreshold((int) $a->age_months)) {
            return $this->row(
                'Cough or difficult breathing',
                'PNEUMONIA',
                self::YELLOW,
                'Give oral amoxicillin for 5 days. Soothe the throat. Advise when to return immediately. Follow up in 3 days.'
            );
        }

        return $this->row(
            'Cough or difficult breathing',
            'COUGH OR COLD',
            self::GREEN,
            'Soothe the throat and relieve the cough. Advise when to return immediately. Follow up in 5 days if not improving.'
        );
    }

    /**
     * Diarrhoea yields a dehydration classification, and separately a
     * persistent or dysentery classification where those apply.
     *
     * @return list<array<string, string>>
     */
    protected function diarrhoea(ImciAssessment $a): array
    {
        if (! $a->has_diarrhoea) {
            return [];
        }

        $rows = [$this->dehydration($a)];

        $days = (int) ($a->diarrhoea_days ?? 0);

        if ($days >= self::PERSISTENT_DIARRHOEA_DAYS) {
            $severe = $this->dehydrationLevel($a) !== 'none';

            $rows[] = $severe
                ? $this->row('Diarrhoea', 'SEVERE PERSISTENT DIARRHOEA', self::PINK, 'Treat the dehydration before referral unless the child has another severe classification. Refer to hospital.')
                : $this->row('Diarrhoea', 'PERSISTENT DIARRHOEA', self::YELLOW, 'Advise on feeding. Give multivitamins and minerals including zinc for 14 days. Follow up in 5 days.');
        }

        if ($a->blood_in_stool) {
            $rows[] = $this->row('Diarrhoea', 'DYSENTERY', self::YELLOW, 'Give ciprofloxacin for 3 days. Follow up in 3 days.');
        }

        return array_values(array_filter($rows));
    }

    /**
     * IMCI counts signs rather than weighing them: two or more from a column
     * makes the classification.
     */
    public function dehydrationLevel(ImciAssessment $a): string
    {
        $severe = 0;
        $severe += $a->lethargic_or_unconscious ? 1 : 0;
        $severe += $a->sunken_eyes ? 1 : 0;
        $severe += in_array($a->drinking, ['not_able', 'poorly'], true) ? 1 : 0;
        $severe += $a->skin_pinch === 'very_slow' ? 1 : 0;

        if ($severe >= 2) {
            return 'severe';
        }

        $some = 0;
        $some += $a->restless_or_irritable ? 1 : 0;
        $some += $a->sunken_eyes ? 1 : 0;
        $some += $a->drinking === 'eagerly' ? 1 : 0;
        $some += $a->skin_pinch === 'slow' ? 1 : 0;

        return $some >= 2 ? 'some' : 'none';
    }

    /**
     * @return array<string, string>
     */
    protected function dehydration(ImciAssessment $a): array
    {
        return match ($this->dehydrationLevel($a)) {
            'severe' => $this->row('Dehydration', 'SEVERE DEHYDRATION', self::PINK, 'Give fluid for severe dehydration (Plan C) and refer urgently if another severe classification is present.'),
            'some' => $this->row('Dehydration', 'SOME DEHYDRATION', self::YELLOW, 'Give fluid, zinc and food for some dehydration (Plan B). Follow up in 5 days if not improving.'),
            default => $this->row('Dehydration', 'NO DEHYDRATION', self::GREEN, 'Give fluid, zinc and food to treat diarrhoea at home (Plan A). Follow up in 5 days if not improving.'),
        };
    }

    /**
     * @return array<string, string>|null
     */
    protected function fever(ImciAssessment $a, bool $danger): ?array
    {
        if (! $a->has_fever) {
            return null;
        }

        if ($danger || $a->stiff_neck) {
            return $this->row(
                'Fever',
                'VERY SEVERE FEBRILE DISEASE',
                self::PINK,
                'Give first dose of artesunate and an antibiotic, treat to prevent low blood sugar, and refer URGENTLY.'
            );
        }

        return match ($a->malaria_test_result) {
            'positive' => $this->row('Fever', 'MALARIA', self::YELLOW, 'Give an oral antimalarial (ACT). Give paracetamol for high fever. Advise when to return immediately. Follow up in 3 days if the fever persists.'),
            'negative' => $this->row('Fever', 'FEVER — NO MALARIA', self::GREEN, 'Give paracetamol for high fever. Look for other causes of fever. Follow up in 3 days if the fever persists.'),
            // In a malaria-endemic setting an untested fever is not cleared.
            default => $this->row('Fever', 'FEVER — MALARIA NOT EXCLUDED', self::YELLOW, 'Test for malaria before treating. Give paracetamol for high fever.'),
        };
    }

    /**
     * @return array<string, string>|null
     */
    protected function ear(ImciAssessment $a): ?array
    {
        if (! $a->has_ear_problem) {
            return null;
        }

        if ($a->tender_swelling_behind_ear) {
            return $this->row('Ear problem', 'MASTOIDITIS', self::PINK, 'Give first dose of an appropriate antibiotic and paracetamol for pain. Refer URGENTLY.');
        }

        $days = $a->ear_discharge_days;

        if ($days !== null && $days >= self::CHRONIC_EAR_DISCHARGE_DAYS) {
            return $this->row('Ear problem', 'CHRONIC EAR INFECTION', self::YELLOW, 'Dry the ear by wicking. Treat with topical quinolone ear drops for 14 days.');
        }

        if (($days !== null && $days > 0) || $a->ear_pain) {
            return $this->row('Ear problem', 'ACUTE EAR INFECTION', self::YELLOW, 'Give oral amoxicillin for 5 days. Give paracetamol for pain. Dry the ear by wicking. Follow up in 5 days.');
        }

        return $this->row('Ear problem', 'NO EAR INFECTION', self::GREEN, 'No additional treatment.');
    }

    /**
     * @return array<string, string>|null
     */
    protected function nutrition(ImciAssessment $a): ?array
    {
        $muac = $a->muac_cm === null ? null : (float) $a->muac_cm;

        if ($a->oedema_both_feet || $a->visible_severe_wasting || ($muac !== null && $muac < self::MUAC_SEVERE_CM)) {
            return $this->row(
                'Nutritional status',
                'SEVERE ACUTE MALNUTRITION',
                self::PINK,
                'Give vitamin A. Treat to prevent low blood sugar. Keep the child warm. Refer URGENTLY to therapeutic feeding.'
            );
        }

        if ($muac !== null && $muac < self::MUAC_MODERATE_CM) {
            return $this->row(
                'Nutritional status',
                'MODERATE ACUTE MALNUTRITION',
                self::YELLOW,
                'Assess and counsel on feeding. Start supplementary feeding. Follow up in 7 days.'
            );
        }

        return $muac === null ? null : $this->row('Nutritional status', 'NO ACUTE MALNUTRITION', self::GREEN, 'Counsel on feeding. Follow up at the next child welfare visit.');
    }

    /**
     * @return array<string, string>|null
     */
    protected function anaemia(ImciAssessment $a): ?array
    {
        return match ($a->palmar_pallor) {
            'severe' => $this->row('Anaemia', 'SEVERE ANAEMIA', self::PINK, 'Refer URGENTLY to hospital.'),
            'some' => $this->row('Anaemia', 'ANAEMIA', self::YELLOW, 'Give iron. Give an antimalarial where indicated. Give mebendazole if over one year and none in the last 6 months. Follow up in 14 days.'),
            'none' => $this->row('Anaemia', 'NO ANAEMIA', self::GREEN, 'No additional treatment.'),
            default => null,
        };
    }

    /**
     * The most urgent colour across a set of classifications.
     *
     * @param  list<array<string, string>>  $classifications
     */
    public function highestUrgency(array $classifications): ?string
    {
        foreach ([self::PINK, self::YELLOW, self::GREEN] as $colour) {
            foreach ($classifications as $classification) {
                if ($classification['colour'] === $colour) {
                    return $colour;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $classifications
     */
    public function needsUrgentReferral(array $classifications): bool
    {
        return $this->highestUrgency($classifications) === self::PINK;
    }

    /**
     * @return array<string, string>
     */
    protected function row(string $condition, string $classification, string $colour, string $action): array
    {
        return compact('condition', 'classification', 'colour', 'action');
    }
}
