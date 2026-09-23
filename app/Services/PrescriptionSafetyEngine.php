<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\DrugInteraction;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Clinical decision support for a whole prescription.
 *
 * Composes five checks into one list of findings:
 *  - allergy       (delegated to AllergyEngine)
 *  - interaction   (drug-drug and class-class pairs)
 *  - dose          (daily and single limits, weight-based for children)
 *  - organ         (renal and hepatic clearance against recorded impairment)
 *  - pregnancy     (pregnancy category by trimester, and lactation)
 *
 * Every finding carries `requires_justification`. The engine never silently
 * blocks: it says what is wrong and whether the clinician has to write down why
 * they are going ahead anyway.
 */
class PrescriptionSafetyEngine
{
    /**
     * Minimum characters of justification for a blocking finding. Matches the
     * allergy engine so the clinician meets one rule, not several.
     */
    public const JUSTIFICATION_MIN_LENGTH = AllergyEngine::JUSTIFICATION_MIN_LENGTH;

    /**
     * Impairment grades that make a renally or hepatically cleared drug a
     * blocking concern rather than an advisory one.
     *
     * @var list<string>
     */
    protected const SERIOUS_IMPAIRMENT = ['moderate', 'severe'];

    /**
     * eGFR below this is treated as significant renal impairment when no grade
     * has been recorded by hand.
     */
    protected const EGFR_IMPAIRMENT_THRESHOLD = 30.0;

    /**
     * The vocabulary `drugs.pregnancy_category` is written in. These say what to
     * do rather than carrying a letter grade: the FDA A/B/C/D/X letters were
     * retired in 2015 precisely because clinicians read them as a safety
     * ranking, and Ghana's Standard Treatment Guidelines are written as advice.
     */
    public const PREGNANCY_SAFE = 'safe';

    public const PREGNANCY_CAUTION = 'caution';

    public const PREGNANCY_AVOID_FIRST = 'avoid_first_trimester';

    public const PREGNANCY_AVOID_THIRD = 'avoid_third_trimester';

    public const PREGNANCY_CONTRAINDICATED = 'contraindicated';

    /**
     * @var list<string>
     */
    public const PREGNANCY_CATEGORIES = [
        self::PREGNANCY_SAFE,
        self::PREGNANCY_CAUTION,
        self::PREGNANCY_AVOID_FIRST,
        self::PREGNANCY_AVOID_THIRD,
        self::PREGNANCY_CONTRAINDICATED,
    ];

    public const LACTATION_SAFE = 'safe';

    public const LACTATION_CAUTION = 'caution';

    public const LACTATION_AVOID = 'avoid';

    /**
     * @var list<string>
     */
    public const LACTATION_CATEGORIES = [
        self::LACTATION_SAFE,
        self::LACTATION_CAUTION,
        self::LACTATION_AVOID,
    ];

    public function __construct(protected AllergyEngine $allergyEngine) {}

    /**
     * Run every check against a submitted prescription.
     *
     * @param  array<int, array<string, mixed>>  $items  submitted prescription rows
     * @return list<array{category: string, severity: string, drug_id: int|null, drug_name: string, title: string, detail: string, requires_justification: bool, key: string}>
     */
    public function check(Patient $patient, array $items, ?Consultation $consultation = null): array
    {
        $drugs = $this->drugsFor($items);

        if ($drugs->isEmpty()) {
            return [];
        }

        return array_merge(
            $this->allergyFindings($patient, $drugs),
            $this->interactionFindings($drugs),
            $this->doseFindings($items, $drugs, $patient, $consultation),
            $this->organFunctionFindings($drugs, $consultation),
            $this->pregnancyFindings($drugs, $consultation),
        );
    }

    /**
     * Findings the clinician cannot pass without writing a justification.
     *
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    public function blocking(array $findings): array
    {
        return array_values(array_filter($findings, fn (array $finding) => $finding['requires_justification']));
    }

    public function isJustified(?string $justification): bool
    {
        return mb_strlen(trim((string) $justification)) >= self::JUSTIFICATION_MIN_LENGTH;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Drug>
     */
    protected function drugsFor(array $items): Collection
    {
        $ids = collect($items)->pluck('drug_id')->filter()->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Drug::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Drug>  $drugs
     * @return list<array<string, mixed>>
     */
    protected function allergyFindings(Patient $patient, Collection $drugs): array
    {
        $findings = [];

        foreach ($this->allergyEngine->check($patient, $drugs) as $conflict) {
            $findings[] = [
                'category' => 'allergy',
                'severity' => $conflict['severity'],
                'drug_id' => $conflict['drug_id'],
                'drug_name' => $conflict['drug_name'],
                'title' => sprintf('%s allergy to %s', ucfirst($conflict['severity']), $conflict['allergen']),
                'detail' => sprintf(
                    'Matched on %s name.%s',
                    $conflict['match_type'],
                    $conflict['reaction'] ? ' Recorded reaction: '.$conflict['reaction'].'.' : ''
                ),
                'requires_justification' => $conflict['is_critical'],
                'key' => 'allergy:'.$conflict['drug_id'],
            ];
        }

        return $findings;
    }

    /**
     * Every unordered pair on the prescription is checked against the rule table.
     *
     * @param  Collection<int, Drug>  $drugs
     * @return list<array<string, mixed>>
     */
    protected function interactionFindings(Collection $drugs): array
    {
        if ($drugs->count() < 2) {
            return [];
        }

        $rules = DrugInteraction::where('is_active', true)->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $findings = [];
        $list = $drugs->values();

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $first = $list[$i];
                $second = $list[$j];

                foreach ($rules as $rule) {
                    if (! $rule->matches($first, $second)) {
                        continue;
                    }

                    $findings[] = [
                        'category' => 'interaction',
                        'severity' => $rule->severity,
                        'drug_id' => $second->id,
                        'drug_name' => $second->name,
                        'title' => sprintf('%s interaction: %s + %s', ucfirst($rule->severity), $first->name, $second->name),
                        'detail' => $rule->effect.($rule->management ? ' Management: '.$rule->management : ''),
                        'requires_justification' => $rule->isBlocking(),
                        'key' => 'interaction:'.min($first->id, $second->id).'-'.max($first->id, $second->id),
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Daily and single dose limits, plus weight-based limits for children.
     *
     * Only rows with a numeric dose and a recorded limit are checked; a free-text
     * dose such as "1 tablet" cannot be compared to a milligram ceiling.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Drug>  $drugs
     * @return list<array<string, mixed>>
     */
    protected function doseFindings(array $items, Collection $drugs, Patient $patient, ?Consultation $consultation): array
    {
        $findings = [];
        $weight = $consultation?->weight_kg !== null ? (float) $consultation->weight_kg : null;
        $isChild = $patient->age !== null && $patient->age < 12;

        foreach ($items as $item) {
            $drug = $drugs->get($item['drug_id'] ?? null);

            if ($drug === null) {
                continue;
            }

            $singleDose = $this->numericDose($item['dose'] ?? null);

            if ($singleDose === null) {
                continue;
            }

            $perDay = $this->dosesPerDay($item['frequency'] ?? null);
            $dailyDose = $perDay !== null ? $singleDose * $perDay : null;

            if ($drug->max_single_dose !== null && $singleDose > (float) $drug->max_single_dose) {
                $findings[] = [
                    'category' => 'dose',
                    'severity' => 'major',
                    'drug_id' => $drug->id,
                    'drug_name' => $drug->name,
                    'title' => 'Single dose above the recorded maximum',
                    'detail' => sprintf(
                        'Prescribed %s%s per dose against a maximum of %s%s.',
                        $this->trim($singleDose), $drug->dose_unit, $this->trim((float) $drug->max_single_dose), $drug->dose_unit
                    ),
                    'requires_justification' => true,
                    'key' => 'dose-single:'.$drug->id,
                ];
            }

            if ($dailyDose !== null && $drug->max_daily_dose !== null && $dailyDose > (float) $drug->max_daily_dose) {
                $findings[] = [
                    'category' => 'dose',
                    'severity' => 'major',
                    'drug_id' => $drug->id,
                    'drug_name' => $drug->name,
                    'title' => 'Daily dose above the recorded maximum',
                    'detail' => sprintf(
                        '%s%s per dose x %d a day is %s%s, against a maximum of %s%s.',
                        $this->trim($singleDose), $drug->dose_unit, $perDay,
                        $this->trim($dailyDose), $drug->dose_unit,
                        $this->trim((float) $drug->max_daily_dose), $drug->dose_unit
                    ),
                    'requires_justification' => true,
                    'key' => 'dose-daily:'.$drug->id,
                ];
            }

            if ($isChild && $drug->paediatric_max_per_kg !== null) {
                if ($weight === null) {
                    $findings[] = [
                        'category' => 'dose',
                        'severity' => 'moderate',
                        'drug_id' => $drug->id,
                        'drug_name' => $drug->name,
                        'title' => 'No weight recorded for a weight-based drug',
                        'detail' => $drug->name.' is dosed by weight in children. Record a weight to have the dose checked.',
                        'requires_justification' => false,
                        'key' => 'dose-weight-missing:'.$drug->id,
                    ];
                } elseif ($singleDose > (float) $drug->paediatric_max_per_kg * $weight) {
                    $findings[] = [
                        'category' => 'dose',
                        'severity' => 'major',
                        'drug_id' => $drug->id,
                        'drug_name' => $drug->name,
                        'title' => 'Paediatric dose above the weight-based maximum',
                        'detail' => sprintf(
                            'Prescribed %s%s against a maximum of %s%s for %skg (%s%s/kg).',
                            $this->trim($singleDose), $drug->dose_unit,
                            $this->trim((float) $drug->paediatric_max_per_kg * $weight), $drug->dose_unit,
                            $this->trim($weight),
                            $this->trim((float) $drug->paediatric_max_per_kg), $drug->dose_unit
                        ),
                        'requires_justification' => true,
                        'key' => 'dose-paediatric:'.$drug->id,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Renal and hepatic clearance against impairment recorded at this encounter.
     *
     * @param  Collection<int, Drug>  $drugs
     * @return list<array<string, mixed>>
     */
    protected function organFunctionFindings(Collection $drugs, ?Consultation $consultation): array
    {
        if ($consultation === null) {
            return [];
        }

        $renal = $this->renalGrade($consultation);
        $hepatic = strtolower((string) $consultation->hepatic_impairment);

        $findings = [];

        foreach ($drugs as $drug) {
            if ($drug->is_renally_cleared && $renal !== null && $renal !== 'none') {
                $serious = in_array($renal, self::SERIOUS_IMPAIRMENT, true);

                $findings[] = [
                    'category' => 'organ',
                    'severity' => $serious ? 'major' : 'moderate',
                    'drug_id' => $drug->id,
                    'drug_name' => $drug->name,
                    'title' => ucfirst($renal).' renal impairment — '.$drug->name.' is renally cleared',
                    'detail' => $drug->renal_guidance ?: 'Review the dose and interval against renal function.',
                    'requires_justification' => $serious,
                    'key' => 'organ-renal:'.$drug->id,
                ];
            }

            if ($drug->is_hepatically_cleared && $hepatic !== '' && $hepatic !== 'none') {
                $serious = in_array($hepatic, self::SERIOUS_IMPAIRMENT, true);

                $findings[] = [
                    'category' => 'organ',
                    'severity' => $serious ? 'major' : 'moderate',
                    'drug_id' => $drug->id,
                    'drug_name' => $drug->name,
                    'title' => ucfirst($hepatic).' hepatic impairment — '.$drug->name.' is hepatically cleared',
                    'detail' => $drug->hepatic_guidance ?: 'Review the dose against liver function.',
                    'requires_justification' => $serious,
                    'key' => 'organ-hepatic:'.$drug->id,
                ];
            }
        }

        return $findings;
    }

    /**
     * Pregnancy and lactation, against the category recorded on the drug.
     *
     * A drug with no category recorded is deliberately not checked rather than
     * wrongly flagged — the same rule the dose checks follow. Where a category
     * names a trimester but no gestational age has been recorded, the finding
     * still shows as a warning: a missing date is not evidence of safety.
     *
     * @param  Collection<int, Drug>  $drugs
     * @return list<array<string, mixed>>
     */
    protected function pregnancyFindings(Collection $drugs, ?Consultation $consultation): array
    {
        if ($consultation === null) {
            return [];
        }

        $isPregnant = strtolower((string) $consultation->pregnancy_status) === 'pregnant';
        $isBreastfeeding = strtolower((string) $consultation->lactation_status) === 'breastfeeding';

        if (! $isPregnant && ! $isBreastfeeding) {
            return [];
        }

        $trimester = $isPregnant ? $this->trimester($consultation) : null;
        $findings = [];

        foreach ($drugs as $drug) {
            if ($isPregnant) {
                $finding = $this->pregnancyFinding($drug, $trimester);

                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }

            if ($isBreastfeeding) {
                $finding = $this->lactationFinding($drug);

                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function pregnancyFinding(Drug $drug, ?int $trimester): ?array
    {
        $category = strtolower(trim((string) $drug->pregnancy_category));

        if ($category === '' || $category === self::PREGNANCY_SAFE) {
            return null;
        }

        $namedTrimester = match ($category) {
            self::PREGNANCY_AVOID_FIRST => 1,
            self::PREGNANCY_AVOID_THIRD => 3,
            default => null,
        };

        // Contraindicated binds throughout. A trimester-specific warning only
        // blocks inside its own trimester, and stays advisory when the
        // gestational age is unknown rather than guessing either way.
        $blocking = match (true) {
            $category === self::PREGNANCY_CONTRAINDICATED => true,
            $namedTrimester !== null => $trimester === $namedTrimester,
            default => false,
        };

        $detail = $drug->pregnancy_guidance ?: 'Check this drug against the Standard Treatment Guidelines before prescribing in pregnancy.';

        if ($namedTrimester !== null && $trimester === null) {
            $detail .= ' No gestational age or LMP is recorded for this encounter, so the trimester could not be confirmed.';
        }

        return [
            'category' => 'pregnancy',
            'severity' => $blocking ? 'major' : 'moderate',
            'drug_id' => $drug->id,
            'drug_name' => $drug->name,
            'title' => $this->pregnancyTitle($drug, $category, $trimester),
            'detail' => $detail,
            'requires_justification' => $blocking,
            'key' => 'pregnancy:'.$drug->id,
        ];
    }

    protected function pregnancyTitle(Drug $drug, string $category, ?int $trimester): string
    {
        $where = $trimester !== null ? sprintf(' (currently trimester %d)', $trimester) : '';

        return match ($category) {
            self::PREGNANCY_CONTRAINDICATED => $drug->name.' is contraindicated in pregnancy'.$where,
            self::PREGNANCY_AVOID_FIRST => $drug->name.' should be avoided in the first trimester'.$where,
            self::PREGNANCY_AVOID_THIRD => $drug->name.' should be avoided in the third trimester'.$where,
            default => $drug->name.' needs caution in pregnancy'.$where,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lactationFinding(Drug $drug): ?array
    {
        $category = strtolower(trim((string) $drug->lactation_category));

        if ($category === '' || $category === self::LACTATION_SAFE) {
            return null;
        }

        $blocking = $category === self::LACTATION_AVOID;

        return [
            'category' => 'pregnancy',
            'severity' => $blocking ? 'major' : 'moderate',
            'drug_id' => $drug->id,
            'drug_name' => $drug->name,
            'title' => $blocking
                ? $drug->name.' should be avoided while breastfeeding'
                : $drug->name.' needs caution while breastfeeding',
            'detail' => $drug->lactation_guidance ?: 'Check infant exposure against the Standard Treatment Guidelines.',
            'requires_justification' => $blocking,
            'key' => 'lactation:'.$drug->id,
        ];
    }

    /**
     * Which trimester this encounter falls in, from the LMP where one is
     * recorded, otherwise from a gestational age written as a number of weeks.
     * Returns null when neither is available — the caller must not assume.
     */
    protected function trimester(Consultation $consultation): ?int
    {
        $weeks = null;

        if ($consultation->lmp !== null) {
            $weeks = Carbon::parse($consultation->lmp)->diffInWeeks(Carbon::now());
        } elseif (preg_match('/\d+/', (string) $consultation->gestational_age, $matches) === 1) {
            $weeks = (int) $matches[0];
        }

        if ($weeks === null || $weeks < 0 || $weeks > 45) {
            return null;
        }

        return match (true) {
            $weeks < 14 => 1,
            $weeks < 28 => 2,
            default => 3,
        };
    }

    /**
     * Renal impairment as a grade, falling back to eGFR where no grade was set.
     */
    protected function renalGrade(Consultation $consultation): ?string
    {
        $recorded = strtolower((string) $consultation->renal_impairment);

        if ($recorded !== '') {
            return $recorded;
        }

        if ($consultation->egfr === null) {
            return null;
        }

        return (float) $consultation->egfr < self::EGFR_IMPAIRMENT_THRESHOLD ? 'severe' : 'none';
    }

    /**
     * Pull a number out of a dose string such as "500mg" or "1.5 g".
     * Returns null when the dose is not expressed numerically.
     */
    protected function numericDose(?string $dose): ?float
    {
        if ($dose === null || ! preg_match('/(\d+(?:\.\d+)?)/', $dose, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    /**
     * Translate a frequency abbreviation into doses per day.
     *
     * Public because the prescribing form derives its "Auto" dispensing
     * quantity from the same table; a second copy of it would drift.
     */
    public function dosesPerDay(?string $frequency): ?int
    {
        $normalised = strtolower(trim((string) $frequency));

        return match ($normalised) {
            'od', 'daily', 'once daily', 'nocte', 'mane' => 1,
            'bd', 'bid', 'twice daily' => 2,
            'tds', 'tid', 'three times daily' => 3,
            'qds', 'qid', 'four times daily' => 4,
            default => null,
        };
    }

    /**
     * Render a number without trailing zeros, so 500.000 reads as 500.
     */
    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
