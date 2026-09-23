<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugInteraction;
use App\Services\PrescriptionSafetyEngine;
use Illuminate\Database\Seeder;

/**
 * Prescribing limits and a starter set of interaction rules.
 *
 * This is a small, widely-documented set to make the safety engine useful out of
 * the box. It is not a substitute for a licensed drug interaction database, and
 * a facility should load its own formulary rules on top.
 */
class DrugSafetySeeder extends Seeder
{
    public function run(): void
    {
        $this->applyDoseLimits();
        $this->applyInteractions();
        $this->applyPregnancyAndLactationGuidance();
    }

    /**
     * Pregnancy and lactation categories for the seeded formulary.
     *
     * Deliberately limited to entries that are unambiguous in the Ghana STG and
     * the WHO malaria guidelines. A drug left out here has no category, and the
     * engine then says nothing about it rather than implying it is safe — the
     * same rule the dose checks follow. Load the facility formulary on top.
     */
    protected function applyPregnancyAndLactationGuidance(): void
    {
        $guidance = [
            'Artemether-Lumefantrine' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
                'pregnancy_guidance' => 'Not recommended in the first trimester: treat uncomplicated malaria with quinine plus clindamycin, and use ACT from the second trimester onwards.',
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_CAUTION,
                'lactation_guidance' => 'Acceptable while breastfeeding; observe the infant.',
            ],
            'Ciprofloxacin' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_CAUTION,
                'pregnancy_guidance' => 'Fluoroquinolone. Avoid unless there is no safer alternative; prefer a beta-lactam in pregnancy.',
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_CAUTION,
                'lactation_guidance' => 'Passes into breast milk; prefer an alternative where one is suitable.',
            ],
            'Metronidazole' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
                'pregnancy_guidance' => 'Avoid high-dose regimens in the first trimester.',
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_CAUTION,
                'lactation_guidance' => 'Alters the taste of breast milk; consider deferring feeds after a single high dose.',
            ],
            'Cotrimoxazole' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
                'pregnancy_guidance' => 'Folate antagonist: avoid in the first trimester, and near term because of the risk of neonatal kernicterus. Give folic acid where it cannot be avoided.',
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_CAUTION,
                'lactation_guidance' => 'Avoid in jaundiced, premature or G6PD-deficient infants.',
            ],
            'Ibuprofen' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_THIRD,
                'pregnancy_guidance' => 'NSAIDs risk premature closure of the ductus arteriosus and oligohydramnios in the third trimester. Paracetamol is the analgesic of choice in pregnancy.',
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_SAFE,
            ],
            'Paracetamol' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_SAFE,
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_SAFE,
            ],
            'Amoxicillin' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_SAFE,
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_SAFE,
            ],
            'Augmentin' => [
                'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_SAFE,
                'lactation_category' => PrescriptionSafetyEngine::LACTATION_SAFE,
            ],
        ];

        foreach ($guidance as $name => $attributes) {
            Drug::where('name', $name)->update($attributes);
        }
    }

    /**
     * Ceilings for adults, in the unit recorded against each drug.
     */
    protected function applyDoseLimits(): void
    {
        $limits = [
            'Paracetamol' => [
                'max_single_dose' => 1000, 'max_daily_dose' => 4000, 'dose_unit' => 'mg',
                'paediatric_max_per_kg' => 15, 'is_hepatically_cleared' => true,
                'hepatic_guidance' => 'Reduce the dose in liver impairment; avoid in severe disease.',
            ],
            'Ibuprofen' => [
                'max_single_dose' => 800, 'max_daily_dose' => 2400, 'dose_unit' => 'mg',
                'paediatric_max_per_kg' => 10, 'is_renally_cleared' => true,
                'renal_guidance' => 'Avoid in moderate to severe renal impairment.',
            ],
            'Amoxicillin' => [
                'max_single_dose' => 1000, 'max_daily_dose' => 3000, 'dose_unit' => 'mg',
                'paediatric_max_per_kg' => 30, 'is_renally_cleared' => true,
                'renal_guidance' => 'Extend the dosing interval when eGFR is below 30.',
            ],
            'Ciprofloxacin' => [
                'max_single_dose' => 750, 'max_daily_dose' => 1500, 'dose_unit' => 'mg',
                'is_renally_cleared' => true,
                'renal_guidance' => 'Halve the dose when eGFR is below 30.',
            ],
            'Metronidazole' => [
                'max_single_dose' => 800, 'max_daily_dose' => 2400, 'dose_unit' => 'mg',
                'is_hepatically_cleared' => true,
                'hepatic_guidance' => 'Reduce the dose in severe liver impairment.',
            ],
            'Cotrimoxazole' => [
                'max_single_dose' => 960, 'max_daily_dose' => 1920, 'dose_unit' => 'mg',
                'is_renally_cleared' => true,
                'renal_guidance' => 'Reduce the dose when eGFR is below 30; avoid below 15.',
            ],
        ];

        foreach ($limits as $name => $attributes) {
            Drug::where('name', $name)->update($attributes);
        }
    }

    /**
     * Interaction rules, stated by class where the whole group behaves alike.
     */
    protected function applyInteractions(): void
    {
        $rules = [
            [
                'drug_class' => 'quinolone',
                'interacts_with_class' => 'nsaid',
                'severity' => 'moderate',
                'effect' => 'Increased risk of seizures, particularly at high doses.',
                'management' => 'Use with caution; avoid together in patients with epilepsy.',
            ],
            [
                'drug_class' => 'sulfonamide',
                'interacts_with_class' => 'nsaid',
                'severity' => 'moderate',
                'effect' => 'Additive renal strain and risk of hyperkalaemia.',
                'management' => 'Monitor renal function and potassium.',
            ],
            [
                'drug_class' => 'penicillin',
                'interacts_with_class' => 'tetracycline',
                'severity' => 'moderate',
                'effect' => 'Tetracyclines are bacteriostatic and may blunt the effect of penicillins.',
                'management' => 'Avoid giving together for the same infection.',
            ],
            [
                'drug_class' => 'aminoglycoside',
                'interacts_with_class' => 'nsaid',
                'severity' => 'major',
                'effect' => 'Substantially increased risk of nephrotoxicity.',
                'management' => 'Avoid together; if unavoidable, monitor renal function closely.',
            ],
        ];

        foreach ($rules as $rule) {
            DrugInteraction::updateOrCreate(
                [
                    'drug_class' => $rule['drug_class'],
                    'interacts_with_class' => $rule['interacts_with_class'],
                ],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->pairRule('Ciprofloxacin', 'Metronidazole', 'minor',
            'Both may prolong the QT interval.',
            'Consider an ECG in patients with cardiac risk factors.');
    }

    /**
     * Record a rule between two specific drugs, where both are on the formulary.
     */
    protected function pairRule(string $first, string $second, string $severity, string $effect, ?string $management = null): void
    {
        $a = Drug::where('name', $first)->first();
        $b = Drug::where('name', $second)->first();

        if ($a === null || $b === null) {
            return;
        }

        DrugInteraction::updateOrCreate(
            ['drug_id' => $a->id, 'interacts_with_drug_id' => $b->id],
            [
                'severity' => $severity,
                'effect' => $effect,
                'management' => $management,
                'is_active' => true,
            ]
        );
    }
}
