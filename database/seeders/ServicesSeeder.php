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
                'name' => 'X-Ray',
                'category' => 'Radiology',
                'price' => 120.00,
                'is_nhis_covered' => true,
                'code' => 'RAD-XRAY',
                'is_active' => true,
            ],
            [
                'name' => 'Ultrasound Scan',
                'category' => 'Radiology',
                'price' => 180.00,
                'is_nhis_covered' => true,
                'code' => 'RAD-ULTRASOUND',
                'is_active' => true,
            ],
            [
                'name' => 'CT Scan',
                'category' => 'Radiology',
                'price' => 900.00,
                'is_nhis_covered' => false,
                'code' => 'RAD-CT',
                'is_active' => true,
            ],
            [
                'name' => 'MRI Scan',
                'category' => 'Radiology',
                'price' => 1500.00,
                'is_nhis_covered' => false,
                'code' => 'RAD-MRI',
                'is_active' => true,
            ],
            [
                'name' => 'Imaging Study',
                'category' => 'Radiology',
                'price' => 150.00,
                'is_nhis_covered' => true,
                'code' => 'RAD-GEN',
                'is_active' => true,
            ],
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
