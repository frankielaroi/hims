<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientChronicMedication;
use Illuminate\Database\Seeder;

/**
 * Structured clinical safety data for the demo patients: allergies with a
 * severity and reaction, and the chronic medications a clinician can continue
 * into a new prescription.
 */
class PatientClinicalSafetySeeder extends Seeder
{
    public function run(): void
    {
        $allergies = [
            'HIMS-2026-00001' => [
                ['allergen' => 'Penicillin', 'reaction' => 'Angioedema', 'severity' => 'severe', 'status' => 'active'],
            ],
            'HIMS-2026-00003' => [
                ['allergen' => 'Peanuts', 'reaction' => 'Anaphylaxis', 'severity' => 'life-threatening', 'status' => 'active'],
                ['allergen' => 'Sulfa', 'reaction' => 'Widespread rash', 'severity' => 'moderate', 'status' => 'active'],
            ],
        ];

        foreach ($allergies as $hospitalId => $records) {
            $patient = Patient::where('hospital_id', $hospitalId)->first();

            if ($patient === null) {
                continue;
            }

            foreach ($records as $record) {
                PatientAllergy::updateOrCreate(
                    ['patient_id' => $patient->id, 'allergen' => $record['allergen']],
                    $record
                );
            }
        }

        $medications = [
            'HIMS-2026-00001' => [
                ['drug_name' => 'Amlodipine', 'strength' => '10mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'OD'],
            ],
            'HIMS-2026-00003' => [
                ['drug_name' => 'Metformin', 'strength' => '500mg', 'dose' => '1 tablet', 'route' => 'Oral', 'frequency' => 'BD'],
                ['drug_name' => 'Insulin Glargine', 'strength' => '100 units/ml', 'dose' => '10 units', 'route' => 'Subcutaneous', 'frequency' => 'Nocte'],
            ],
        ];

        foreach ($medications as $hospitalId => $records) {
            $patient = Patient::where('hospital_id', $hospitalId)->first();

            if ($patient === null) {
                continue;
            }

            foreach ($records as $record) {
                PatientChronicMedication::updateOrCreate(
                    ['patient_id' => $patient->id, 'drug_name' => $record['drug_name']],
                    array_merge($record, [
                        // Linked to the formulary where a matching drug exists, so the
                        // medication can be continued without re-picking it.
                        'drug_id' => Drug::where('name', $record['drug_name'])->value('id'),
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
