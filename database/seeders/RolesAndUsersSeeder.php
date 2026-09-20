<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            'view_patients' => 'Can view basic patient identity',
            'register_patients' => 'Can create new patient records',
            'edit_patient_identity' => 'Can edit sensitive identity fields',
            'view_clinical_history' => 'Can view past diagnoses and visits',
            'update_clinical_safety' => 'Can edit allergies and chronic conditions',
            'manage_staff' => 'Can create and manage staff accounts',
            'view_audit_logs' => 'Can view system security logs',
            'manage_pricing' => 'Can update service prices',
            'merge_patients' => 'Can combine duplicate patient records',
            'order_lab_tests' => 'Can order laboratory investigations',
            'process_lab_orders' => 'Can collect specimens and record/verify lab results',
            'dispense_medication' => 'Can dispense prescriptions and manage pharmacy stock',
            'manage_billing' => 'Can view invoices and record patient payments',
            'manage_nhis_claims' => 'Can generate, submit, and track NHIS claims',
            'manage_admissions' => 'Can admit, transfer, and discharge patients',
            'manage_referrals' => 'Can accept, decline, and track patient referrals',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 2. Create Roles and Assign Sensible Default Permissions
        $roleData = [
            'admin' => [
                'name' => 'Administrator',
                'permissions' => array_keys($permissions), // All
            ],
            'management' => [
                'name' => 'Hospital Management',
                'permissions' => ['view_patients', 'view_clinical_history', 'view_audit_logs'],
            ],
            'doctor' => [
                'name' => 'Medical Practitioner',
                'permissions' => ['view_patients', 'view_clinical_history', 'update_clinical_safety', 'order_lab_tests', 'manage_referrals'],
            ],
            'nurse' => [
                'name' => 'Nursing Staff',
                'permissions' => ['view_patients', 'register_patients', 'view_clinical_history', 'update_clinical_safety', 'manage_admissions', 'manage_referrals'],
            ],
            'receptionist' => [
                'name' => 'Front Desk / Reception',
                'permissions' => ['view_patients', 'register_patients'],
            ],
            'pharmacist' => [
                'name' => 'Pharmacist',
                'permissions' => ['view_patients', 'view_clinical_history', 'dispense_medication'],
            ],
            'lab_tech' => [
                'name' => 'Lab Technician',
                'permissions' => ['view_patients', 'view_clinical_history', 'process_lab_orders'],
            ],
            'cashier' => [
                'name' => 'Cashier / Billing',
                'permissions' => ['view_patients', 'manage_billing', 'manage_nhis_claims'],
            ],
        ];

        foreach ($roleData as $slug => $data) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $data['name'], 'description' => $data['name']]
            );

            $pIds = Permission::whereIn('slug', $data['permissions'])->pluck('id');
            $role->permissions()->sync($pIds);
        }

        // 3. Create Primary Admin
        $adminUser = User::updateOrCreate(
            ['username' => 'admin.user'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'requires_password_change' => false,
            ]
        );

        $adminRole = Role::where('slug', 'admin')->first();
        if (! $adminUser->roles()->where('role_id', $adminRole->id)->exists()) {
            $adminUser->roles()->attach($adminRole);
        }
    }
}
