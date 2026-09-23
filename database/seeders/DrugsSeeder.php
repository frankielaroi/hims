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
            ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '500mg', 'formulation' => 'Capsule', 'drug_class' => 'penicillin', 'is_nhis_covered' => true],
            ['name' => 'Paracetamol', 'generic_name' => 'Acetaminophen', 'strength' => '500mg', 'formulation' => 'Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Ciprofloxacin', 'generic_name' => 'Ciprofloxacin', 'strength' => '500mg', 'formulation' => 'Tablet', 'drug_class' => 'quinolone', 'is_nhis_covered' => true],
            ['name' => 'Metronidazole', 'generic_name' => 'Metronidazole', 'strength' => '400mg', 'formulation' => 'Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Artemether-Lumefantrine', 'generic_name' => 'AL', 'strength' => '20/120mg', 'formulation' => 'Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Augmentin', 'generic_name' => 'Co-amoxiclav', 'strength' => '625mg', 'formulation' => 'Tablet', 'drug_class' => 'penicillin', 'is_nhis_covered' => false],
            ['name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'strength' => '400mg', 'formulation' => 'Tablet', 'drug_class' => 'nsaid', 'is_nhis_covered' => true],
            ['name' => 'Cotrimoxazole', 'generic_name' => 'Sulfamethoxazole-Trimethoprim', 'strength' => '960mg', 'formulation' => 'Tablet', 'drug_class' => 'sulfonamide', 'is_nhis_covered' => true],
            ['name' => 'Insulin Glargine', 'generic_name' => 'Lantus', 'strength' => '100 units/ml', 'formulation' => 'Injection', 'drug_class' => null, 'is_nhis_covered' => false],

            // Obstetric emergency drugs. Without these on the formulary a
            // postpartum haemorrhage or an eclamptic fit cannot be prescribed
            // at all, which is the commonest way a mother dies in a facility.
            ['name' => 'Oxytocin', 'generic_name' => 'Oxytocin', 'strength' => '10 IU/ml', 'formulation' => 'Injection', 'drug_class' => 'uterotonic', 'is_nhis_covered' => true],
            ['name' => 'Misoprostol', 'generic_name' => 'Misoprostol', 'strength' => '200mcg', 'formulation' => 'Tablet', 'drug_class' => 'uterotonic', 'is_nhis_covered' => true],
            ['name' => 'Magnesium Sulphate', 'generic_name' => 'Magnesium Sulphate', 'strength' => '500mg/ml', 'formulation' => 'Injection', 'drug_class' => 'anticonvulsant', 'is_nhis_covered' => true],
            ['name' => 'Hydralazine', 'generic_name' => 'Hydralazine', 'strength' => '20mg/ml', 'formulation' => 'Injection', 'drug_class' => 'antihypertensive', 'is_nhis_covered' => true],
            ['name' => 'Tranexamic Acid', 'generic_name' => 'Tranexamic Acid', 'strength' => '500mg/5ml', 'formulation' => 'Injection', 'drug_class' => 'antifibrinolytic', 'is_nhis_covered' => true],
            ['name' => 'Ergometrine', 'generic_name' => 'Ergometrine', 'strength' => '0.5mg/ml', 'formulation' => 'Injection', 'drug_class' => 'uterotonic', 'is_nhis_covered' => true],

            // Antenatal and postnatal supplements.
            ['name' => 'Ferrous Sulphate + Folic Acid', 'generic_name' => 'Iron-Folate', 'strength' => '200mg/0.4mg', 'formulation' => 'Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Sulfadoxine-Pyrimethamine', 'generic_name' => 'SP', 'strength' => '500/25mg', 'formulation' => 'Tablet', 'drug_class' => 'sulfonamide', 'is_nhis_covered' => true],
            ['name' => 'Albendazole', 'generic_name' => 'Albendazole', 'strength' => '400mg', 'formulation' => 'Tablet', 'drug_class' => null, 'is_nhis_covered' => true],

            // Paediatric formulations. A child cannot swallow a 500mg tablet, so
            // prescribing for children needs suspensions on the formulary.
            ['name' => 'Amoxicillin Suspension', 'generic_name' => 'Amoxicillin', 'strength' => '125mg/5ml', 'formulation' => 'Suspension', 'drug_class' => 'penicillin', 'is_nhis_covered' => true],
            ['name' => 'Paracetamol Syrup', 'generic_name' => 'Acetaminophen', 'strength' => '120mg/5ml', 'formulation' => 'Syrup', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Artemether-Lumefantrine Dispersible', 'generic_name' => 'AL', 'strength' => '20/120mg', 'formulation' => 'Dispersible Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Artesunate Injection', 'generic_name' => 'Artesunate', 'strength' => '60mg', 'formulation' => 'Injection', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Oral Rehydration Salts', 'generic_name' => 'ORS', 'strength' => '20.5g sachet', 'formulation' => 'Sachet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Zinc Sulphate', 'generic_name' => 'Zinc', 'strength' => '20mg', 'formulation' => 'Dispersible Tablet', 'drug_class' => null, 'is_nhis_covered' => true],
            ['name' => 'Vitamin K', 'generic_name' => 'Phytomenadione', 'strength' => '1mg/0.5ml', 'formulation' => 'Injection', 'drug_class' => null, 'is_nhis_covered' => true],
        ];

        foreach ($drugs as $drug) {
            Drug::updateOrCreate(['name' => $drug['name'], 'strength' => $drug['strength']], $drug);
        }
    }
}
