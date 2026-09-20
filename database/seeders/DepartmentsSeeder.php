<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $depts = [
            ['name' => 'General OPD', 'slug' => 'opd-general', 'description' => 'Outpatient Department'],
            ['name' => 'Emergency', 'slug' => 'emergency', 'description' => 'Emergency & Trauma Unit'],
            ['name' => 'Maternity', 'slug' => 'maternity', 'description' => 'Maternity & ANC'],
            ['name' => 'Pediatrics', 'slug' => 'pediatrics', 'description' => 'Child Health'],
            ['name' => 'Specialist Clinic', 'slug' => 'specialist', 'description' => 'Specialist Consultations'],
            ['name' => 'Dental', 'slug' => 'dental', 'description' => 'Dental Unit'],
        ];

        foreach ($depts as $dept) {
            Department::updateOrCreate(['slug' => $dept['slug']], $dept);
        }
    }
}
