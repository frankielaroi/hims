<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Visit;

/**
 * Who Ghana exempts from paying at the point of service.
 *
 * The NHIS exempt groups that a facility can determine from the record are
 * pregnant women and children under five. The others — indigents, SSNIT
 * contributors, the elderly — depend on documents the patient brings, so they
 * are recorded by the registration clerk rather than inferred here.
 */
class NhisExemption
{
    public const UNDER_FIVE = 'under_five';

    public const PREGNANT = 'pregnant';

    public const ELDERLY = 'elderly';

    public const INDIGENT = 'indigent';

    public const SSNIT_CONTRIBUTOR = 'ssnit_contributor';

    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        self::UNDER_FIVE => 'Child under five',
        self::PREGNANT => 'Pregnant woman',
        self::ELDERLY => 'Aged 70 or over',
        self::INDIGENT => 'Indigent',
        self::SSNIT_CONTRIBUTOR => 'SSNIT contributor',
    ];

    public const UNDER_FIVE_AGE = 5;

    public const ELDERLY_AGE = 70;

    /**
     * The exemption the record itself establishes, or null when none does.
     *
     * Pregnancy is only inferred from a status recorded at this visit — the
     * system never assumes a woman of childbearing age is pregnant.
     */
    public function categoryFor(Visit $visit): ?string
    {
        $patient = $visit->patient;

        if ($this->isUnderFive($patient)) {
            return self::UNDER_FIVE;
        }

        if ($this->isPregnantAtThisVisit($visit)) {
            return self::PREGNANT;
        }

        if ($patient->age !== null && $patient->age >= self::ELDERLY_AGE) {
            return self::ELDERLY;
        }

        return null;
    }

    public function isUnderFive(Patient $patient): bool
    {
        return $patient->age !== null && $patient->age < self::UNDER_FIVE_AGE;
    }

    public function isPregnantAtThisVisit(Visit $visit): bool
    {
        $status = $visit->consultations()->latest('id')->value('pregnancy_status');

        if (strtolower((string) $status) === 'pregnant') {
            return true;
        }

        // An antenatal contact at this visit is itself evidence of pregnancy.
        return $visit->ancAssessments()->exists();
    }

    /**
     * Apply the exemption the record establishes, without overwriting one a
     * clerk has already recorded from documents the patient brought.
     */
    public function applyTo(Visit $visit): ?string
    {
        if ($visit->exemption_category !== null) {
            return $visit->exemption_category;
        }

        $category = $this->categoryFor($visit);

        if ($category !== null) {
            $visit->update(['exemption_category' => $category]);
        }

        return $category;
    }

    public function label(?string $category): ?string
    {
        return $category === null ? null : (self::CATEGORIES[$category] ?? $category);
    }
}
