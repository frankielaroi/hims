<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Support\Collection;

/**
 * Structured allergy checking for prescribing.
 *
 * Matching runs in three passes, strongest first:
 *  - exact:   the allergen names the brand/trade name of the drug
 *  - generic: the allergen names the drug's generic ingredient
 *  - class:   the allergen resolves to a drug class the drug belongs to
 *
 * Matching is word-boundary based. A substring comparison would flag every drug
 * for a short allergen entry, so plain `str_contains` on the allergen is
 * deliberately avoided.
 */
class AllergyEngine
{
    /**
     * Minimum characters of justification required to override a critical conflict.
     */
    public const JUSTIFICATION_MIN_LENGTH = 20;

    /**
     * Allergen keywords that imply a whole drug class.
     *
     * @var array<string, list<string>>
     */
    protected const CLASS_ALIASES = [
        'penicillin' => ['penicillin', 'penicillins', 'beta-lactam', 'betalactam'],
        'cephalosporin' => ['cephalosporin', 'cephalosporins'],
        'sulfonamide' => ['sulfonamide', 'sulfonamides', 'sulfa', 'sulpha'],
        'nsaid' => ['nsaid', 'nsaids', 'salicylate', 'salicylates'],
        'macrolide' => ['macrolide', 'macrolides'],
        'quinolone' => ['quinolone', 'quinolones', 'fluoroquinolone', 'fluoroquinolones'],
        'opioid' => ['opioid', 'opioids', 'opiate', 'opiates'],
        'tetracycline' => ['tetracycline', 'tetracyclines'],
        'aminoglycoside' => ['aminoglycoside', 'aminoglycosides'],
    ];

    /**
     * Generic-name fragments that place a drug in a class when `drugs.drug_class`
     * has not been populated.
     *
     * @var array<string, list<string>>
     */
    protected const CLASS_STEMS = [
        'penicillin' => ['cillin', 'penicillin'],
        'cephalosporin' => ['cef', 'ceph'],
        'sulfonamide' => ['sulfa', 'sulpha', 'cotrimoxazole'],
        'nsaid' => ['ibuprofen', 'diclofenac', 'aspirin', 'naproxen', 'indomethacin', 'piroxicam'],
        'macrolide' => ['mycin', 'thromycin'],
        'quinolone' => ['floxacin'],
        'opioid' => ['morphine', 'codeine', 'pethidine', 'tramadol', 'fentanyl'],
        'tetracycline' => ['cycline'],
        'aminoglycoside' => ['gentamicin', 'amikacin', 'streptomycin', 'neomycin'],
    ];

    /**
     * Severities that must not be prescribed against without a written justification.
     *
     * @var list<string>
     */
    protected const CRITICAL_SEVERITIES = ['severe', 'life-threatening'];

    /**
     * Severity ordering used to report the most serious conflict for a drug.
     *
     * @var array<string, int>
     */
    protected const SEVERITY_RANK = [
        'life-threatening' => 0,
        'severe' => 1,
        'moderate' => 2,
        'mild' => 3,
        'unknown' => 4,
    ];

    /**
     * Check one drug against a patient's active allergies.
     *
     * @return array{allergen: string, severity: string, reaction: string|null, match_type: string, drug_id: int, drug_name: string, is_critical: bool}|null
     */
    public function checkDrug(Patient $patient, Drug $drug): ?array
    {
        return $this->firstConflict($this->activeAllergies($patient), $drug);
    }

    /**
     * Check a set of drugs against a patient's active allergies.
     *
     * @param  iterable<Drug>  $drugs
     * @return list<array{allergen: string, severity: string, reaction: string|null, match_type: string, drug_id: int, drug_name: string, is_critical: bool}>
     */
    public function check(Patient $patient, iterable $drugs): array
    {
        $allergies = $this->activeAllergies($patient);
        $conflicts = [];

        foreach ($drugs as $drug) {
            $conflict = $this->firstConflict($allergies, $drug);

            if ($conflict !== null) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    /**
     * Whether a conflict of this severity blocks prescribing unless justified.
     */
    public function isCritical(?string $severity): bool
    {
        return in_array(strtolower(trim((string) $severity)), self::CRITICAL_SEVERITIES, true);
    }

    /**
     * Whether the supplied text is a usable override justification.
     */
    public function isJustified(?string $justification): bool
    {
        return mb_strlen(trim((string) $justification)) >= self::JUSTIFICATION_MIN_LENGTH;
    }

    /**
     * The patient's active allergy records, most serious severity first.
     *
     * @return Collection<int, PatientAllergy>
     */
    protected function activeAllergies(Patient $patient): Collection
    {
        return $patient->structuredAllergies()
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (PatientAllergy $allergy) => self::SEVERITY_RANK[strtolower((string) $allergy->severity)] ?? 5)
            ->values();
    }

    /**
     * @param  Collection<int, PatientAllergy>  $allergies
     * @return array{allergen: string, severity: string, reaction: string|null, match_type: string, drug_id: int, drug_name: string, is_critical: bool}|null
     */
    protected function firstConflict(Collection $allergies, Drug $drug): ?array
    {
        foreach ($allergies as $allergy) {
            $matchType = $this->matchType($allergy->allergen, $drug);

            if ($matchType === null) {
                continue;
            }

            return [
                'allergen' => $allergy->allergen,
                'severity' => strtolower((string) $allergy->severity),
                'reaction' => $allergy->reaction,
                'match_type' => $matchType,
                'drug_id' => $drug->id,
                'drug_name' => $drug->name,
                'is_critical' => $this->isCritical($allergy->severity),
            ];
        }

        return null;
    }

    /**
     * How an allergen matches a drug, or null when it does not.
     */
    protected function matchType(?string $allergen, Drug $drug): ?string
    {
        $allergen = trim(strtolower((string) $allergen));

        // Very short entries would match almost anything.
        if (mb_strlen($allergen) < 3) {
            return null;
        }

        if ($this->containsTerm((string) $drug->name, $allergen)) {
            return 'exact';
        }

        if ($this->containsTerm((string) $drug->generic_name, $allergen)) {
            return 'generic';
        }

        if ($this->matchesClass($allergen, $drug)) {
            return 'class';
        }

        return null;
    }

    /**
     * Whether $haystack contains $term as a whole word.
     */
    protected function containsTerm(string $haystack, string $term): bool
    {
        if ($haystack === '' || $term === '') {
            return false;
        }

        return preg_match('/\b'.preg_quote($term, '/').'\b/i', $haystack) === 1;
    }

    /**
     * Whether the allergen names a class this drug belongs to.
     */
    protected function matchesClass(string $allergen, Drug $drug): bool
    {
        $class = $this->resolveAllergenClass($allergen);

        if ($class === null) {
            return false;
        }

        if ($drug->drug_class !== null && strtolower($drug->drug_class) === $class) {
            return true;
        }

        $generic = strtolower((string) $drug->generic_name);

        if ($generic === '') {
            return false;
        }

        foreach (self::CLASS_STEMS[$class] ?? [] as $stem) {
            if (str_contains($generic, $stem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map an allergen keyword onto a canonical drug class.
     */
    protected function resolveAllergenClass(string $allergen): ?string
    {
        foreach (self::CLASS_ALIASES as $class => $aliases) {
            if (in_array($allergen, $aliases, true)) {
                return $class;
            }
        }

        return null;
    }
}
