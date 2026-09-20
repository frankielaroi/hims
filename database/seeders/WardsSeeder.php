<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wards = [
            ['name' => 'General Ward A', 'category' => 'General', 'floor' => '1', 'beds' => 6],
            ['name' => 'Male Surgical Ward', 'category' => 'Surgical', 'floor' => '2', 'beds' => 6],
            ['name' => 'Maternity Ward', 'category' => 'Maternity', 'floor' => '2', 'beds' => 4],
            ['name' => 'Paediatric Ward', 'category' => 'Paediatric', 'floor' => '3', 'beds' => 4],
        ];

        foreach ($wards as $wardData) {
            $ward = Ward::updateOrCreate(['name' => $wardData['name']], [
                'category' => $wardData['category'],
                'floor' => $wardData['floor'],
                'is_active' => true,
            ]);

            for ($i = 1; $i <= $wardData['beds']; $i++) {
                Bed::updateOrCreate([
                    'ward_id' => $ward->id,
                    'bed_number' => chr(64 + $i), // A, B, C...
                ]);
            }
        }
    }
}
