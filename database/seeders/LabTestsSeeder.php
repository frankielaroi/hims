<?php

namespace Database\Seeders;

use App\Models\LabTest;
use Illuminate\Database\Seeder;

class LabTestsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tests = [
            ['name' => 'Malaria RDT', 'code' => 'MP-RDT', 'category' => 'Parasitology', 'specimen_type' => 'Blood', 'reference_range' => 'Negative', 'turnaround_minutes' => 20, 'price' => 15, 'is_nhis_covered' => true],
            ['name' => 'Full Blood Count', 'code' => 'FBC', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'reference_range' => 'WBC 4.5-11.0 x10^9/L', 'turnaround_minutes' => 60, 'price' => 40, 'is_nhis_covered' => true],
            ['name' => 'Random Blood Sugar', 'code' => 'RBS', 'category' => 'Chemistry', 'specimen_type' => 'Blood', 'reference_range' => '4.0-7.8 mmol/L', 'turnaround_minutes' => 15, 'price' => 10, 'is_nhis_covered' => true],
            ['name' => 'Widal Test', 'code' => 'WIDAL', 'category' => 'Serology', 'specimen_type' => 'Blood', 'reference_range' => 'Titre < 1:80', 'turnaround_minutes' => 45, 'price' => 25, 'is_nhis_covered' => true],
            ['name' => 'HIV Screening', 'code' => 'HIV-AB', 'category' => 'Serology', 'specimen_type' => 'Blood', 'reference_range' => 'Non-reactive', 'turnaround_minutes' => 30, 'price' => 20, 'is_nhis_covered' => false],
            ['name' => 'Urine Pregnancy Test', 'code' => 'UPT', 'category' => 'Serology', 'specimen_type' => 'Urine', 'reference_range' => 'Negative', 'turnaround_minutes' => 15, 'price' => 15, 'is_nhis_covered' => true],
            ['name' => 'Urinalysis', 'code' => 'URIN', 'category' => 'Microbiology', 'specimen_type' => 'Urine', 'reference_range' => 'No abnormal findings', 'turnaround_minutes' => 30, 'price' => 20, 'is_nhis_covered' => true],
            ['name' => 'Stool Routine Examination', 'code' => 'STOOL-RE', 'category' => 'Parasitology', 'specimen_type' => 'Stool', 'reference_range' => 'No ova/cysts seen', 'turnaround_minutes' => 40, 'price' => 20, 'is_nhis_covered' => true],
            ['name' => 'Hepatitis B Surface Antigen', 'code' => 'HBSAG', 'category' => 'Serology', 'specimen_type' => 'Blood', 'reference_range' => 'Non-reactive', 'turnaround_minutes' => 30, 'price' => 25, 'is_nhis_covered' => false],
            ['name' => 'Wound Swab Culture & Sensitivity', 'code' => 'WOUND-CS', 'category' => 'Microbiology', 'specimen_type' => 'Swab', 'reference_range' => 'No growth', 'turnaround_minutes' => 2880, 'price' => 60, 'is_nhis_covered' => false],

            // The antenatal booking profile. Every pregnancy needs all of these,
            // and AntenatalSchedule::PROFILE_ITEMS chases whatever is missing.
            ['name' => 'Blood Group & Rhesus', 'code' => 'ABO-RH', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'reference_range' => 'Report group and rhesus', 'turnaround_minutes' => 30, 'price' => 20, 'is_nhis_covered' => true],
            ['name' => 'Haemoglobin', 'code' => 'HB', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'reference_range' => '11.0-16.0 g/dL', 'turnaround_minutes' => 20, 'price' => 15, 'is_nhis_covered' => true],
            ['name' => 'Sickling Test', 'code' => 'SICKLING', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'reference_range' => 'Negative', 'turnaround_minutes' => 40, 'price' => 20, 'is_nhis_covered' => true],
            ['name' => 'Haemoglobin Electrophoresis', 'code' => 'HB-ELECTRO', 'category' => 'Haematology', 'specimen_type' => 'Blood', 'reference_range' => 'AA', 'turnaround_minutes' => 240, 'price' => 60, 'is_nhis_covered' => true],
            ['name' => 'VDRL (Syphilis)', 'code' => 'VDRL', 'category' => 'Serology', 'specimen_type' => 'Blood', 'reference_range' => 'Non-reactive', 'turnaround_minutes' => 40, 'price' => 20, 'is_nhis_covered' => true],

            // Sickle cell disease and G6PD deficiency are both common in Ghana,
            // and G6PD status changes which antimalarials are safe.
            ['name' => 'G6PD Screening', 'code' => 'G6PD', 'category' => 'Chemistry', 'specimen_type' => 'Blood', 'reference_range' => 'Normal activity', 'turnaround_minutes' => 120, 'price' => 40, 'is_nhis_covered' => true],

            // Malaria microscopy reports a parasite density, which an RDT cannot.
            ['name' => 'Blood Film for Malaria Parasites', 'code' => 'BF-MPS', 'category' => 'Parasitology', 'specimen_type' => 'Blood', 'reference_range' => 'No parasites seen; report density per µL', 'turnaround_minutes' => 60, 'price' => 20, 'is_nhis_covered' => true],

            // Tuberculosis. GeneXpert is the front-line test in the national programme.
            ['name' => 'Sputum AFB Microscopy', 'code' => 'AFB', 'category' => 'Microbiology', 'specimen_type' => 'Sputum', 'reference_range' => 'No AFB seen', 'turnaround_minutes' => 240, 'price' => 30, 'is_nhis_covered' => true],
            ['name' => 'GeneXpert MTB/RIF', 'code' => 'GENEXPERT', 'category' => 'Microbiology', 'specimen_type' => 'Sputum', 'reference_range' => 'MTB not detected', 'turnaround_minutes' => 180, 'price' => 0, 'is_nhis_covered' => true],

            // Ghana sits in the meningitis belt; CSF is a same-day emergency test.
            ['name' => 'CSF Analysis', 'code' => 'CSF', 'category' => 'Microbiology', 'specimen_type' => 'CSF', 'reference_range' => 'Clear, no organisms, glucose 2.8-4.4 mmol/L', 'turnaround_minutes' => 90, 'price' => 60, 'is_nhis_covered' => true],

            ['name' => 'Liver Function Tests', 'code' => 'LFT', 'category' => 'Chemistry', 'specimen_type' => 'Blood', 'reference_range' => 'ALT 7-56 U/L', 'turnaround_minutes' => 120, 'price' => 70, 'is_nhis_covered' => true],
            ['name' => 'Renal Function Tests', 'code' => 'RFT', 'category' => 'Chemistry', 'specimen_type' => 'Blood', 'reference_range' => 'Creatinine 60-110 µmol/L', 'turnaround_minutes' => 120, 'price' => 70, 'is_nhis_covered' => true],
            ['name' => 'Blood Culture & Sensitivity', 'code' => 'BLOOD-CS', 'category' => 'Microbiology', 'specimen_type' => 'Blood', 'reference_range' => 'No growth', 'turnaround_minutes' => 4320, 'price' => 80, 'is_nhis_covered' => false],
        ];

        foreach ($tests as $test) {
            LabTest::updateOrCreate(['code' => $test['code']], $test);
        }
    }
}
