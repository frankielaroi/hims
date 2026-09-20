<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'General Consultation',
                'category' => 'Consultation',
                'price' => 50.00,
                'is_nhis_covered' => true,
                'code' => 'CONS-GEN',
                'is_active' => true,
            ],
            [
                'name' => 'Specialist Consultation',
                'category' => 'Consultation',
                'price' => 150.00,
                'is_nhis_covered' => true,
                'code' => 'CONS-SPEC',
                'is_active' => true,
            ],
            [
                'name' => 'Full Blood Count',
                'category' => 'Lab',
                'price' => 75.00,
                'is_nhis_covered' => true,
                'code' => 'LAB-FBC',
                'is_active' => true,
            ],
            [
                'name' => 'Malaria Test (RDT)',
                'category' => 'Lab',
                'price' => 20.00,
                'is_nhis_covered' => true,
                'code' => 'LAB-MAL',
                'is_active' => true,
            ],
            [
                'name' => 'Ward Stay (General)',
                'category' => 'Ward',
                'price' => 100.00,
                'is_nhis_covered' => false,
                'code' => 'WARD-GEN',
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['code' => $service['code']], $service);
        }
    }
}
