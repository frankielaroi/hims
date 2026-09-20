<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = [
            [
                'hospital_id' => 'HIMS-2026-00001',
                'full_name' => 'Kofi Mensah',
                'date_of_birth' => '1985-05-15',
                'sex' => 'male',
                'phone_number' => '0244123456',
                'address' => 'House No. 45, Kumasi Central',
                'payment_method' => 'nhis',
                'nhis_number' => '88776655',
                'nhis_expiry' => '2026-12-31',
                'allergies' => ['Penicillin'],
                'chronic_conditions' => ['Hypertension'],
                'blood_group' => 'O+',
            ],
            [
                'hospital_id' => 'HIMS-2026-00002',
                'full_name' => 'Ama Serwaa',
                'date_of_birth' => '1992-10-20',
                'sex' => 'female',
                'phone_number' => '0551122334',
                'address' => 'Near the main Mosque, Tamale',
                'payment_method' => 'cash',
                'allergies' => [],
                'chronic_conditions' => [],
                'blood_group' => 'A+',
            ],
            [
                'hospital_id' => 'HIMS-2026-00003',
                'full_name' => 'John Kojo',
                'is_age_estimated' => true,
                'estimated_age' => 70,
                'sex' => 'male',
                'phone_number' => '0200998877',
                'address' => 'Lorry Station, Accra',
                'payment_method' => 'nhis',
                'nhis_number' => '11223344',
                'nhis_expiry' => '2025-06-30',
                'allergies' => ['Peanuts'],
                'chronic_conditions' => ['Diabetes Type 2'],
                'blood_group' => 'B-',
            ],
        ];

        foreach ($patients as $data) {
            Patient::updateOrCreate(['hospital_id' => $data['hospital_id']], $data);
        }
    }
}
