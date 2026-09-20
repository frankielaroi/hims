<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndUsersSeeder::class,
            DepartmentsSeeder::class,
            ServicesSeeder::class,
            PatientsSeeder::class,
            Icd10CodesSeeder::class,
            DrugsSeeder::class,
            DrugBatchesSeeder::class,
            LabTestsSeeder::class,
        ]);
    }
}
