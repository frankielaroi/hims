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
        ];

        foreach ($tests as $test) {
            LabTest::updateOrCreate(['code' => $test['code']], $test);
        }
    }
}
