<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

class Icd10CodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $codes = [
            ['code' => 'B54', 'name' => 'Unspecified malaria'],
            ['code' => 'B50', 'name' => 'Plasmodium falciparum malaria'],
            ['code' => 'I10', 'name' => 'Essential (primary) hypertension'],
            ['code' => 'E11', 'name' => 'Type 2 diabetes mellitus'],
            ['code' => 'J06.9', 'name' => 'Acute upper respiratory infection, unspecified'],
            ['code' => 'A09', 'name' => 'Infectious gastroenteritis and colitis, unspecified'],
            ['code' => 'L03', 'name' => 'Cellulitis'],
            ['code' => 'N39.0', 'name' => 'Urinary tract infection, site not specified'],
            ['code' => 'M54.5', 'name' => 'Low back pain'],
            ['code' => 'K29.7', 'name' => 'Gastritis, unspecified'],
        ];

        foreach ($codes as $code) {
            Icd10Code::updateOrCreate(['code' => $code['code']], $code);
        }
    }
}
