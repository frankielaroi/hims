<?php

namespace Database\Seeders;

use App\Models\Drug;
use Illuminate\Database\Seeder;

class DrugsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drugs = [
            ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '500mg', 'formulation' => 'Capsule', 'is_nhis_covered' => true],
            ['name' => 'Paracetamol', 'generic_name' => 'Acetaminophen', 'strength' => '500mg', 'formulation' => 'Tablet', 'is_nhis_covered' => true],
            ['name' => 'Ciprofloxacin', 'generic_name' => 'Ciprofloxacin', 'strength' => '500mg', 'formulation' => 'Tablet', 'is_nhis_covered' => true],
            ['name' => 'Metronidazole', 'generic_name' => 'Metronidazole', 'strength' => '400mg', 'formulation' => 'Tablet', 'is_nhis_covered' => true],
            ['name' => 'Artemether-Lumefantrine', 'generic_name' => 'AL', 'strength' => '20/120mg', 'formulation' => 'Tablet', 'is_nhis_covered' => true],
            ['name' => 'Augmentin', 'generic_name' => 'Co-amoxiclav', 'strength' => '625mg', 'formulation' => 'Tablet', 'is_nhis_covered' => false],
            ['name' => 'Insulin Glargine', 'generic_name' => 'Lantus', 'strength' => '100 units/ml', 'formulation' => 'Injection', 'is_nhis_covered' => false],
        ];

        foreach ($drugs as $drug) {
            Drug::updateOrCreate(['name' => $drug['name'], 'strength' => $drug['strength']], $drug);
        }
    }
}
