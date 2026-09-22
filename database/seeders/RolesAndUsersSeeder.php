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
            'collect_lab_specimens' => 'Can register samples and collect specimens for the laboratory',
            'enter_lab_results' => 'Can enter raw laboratory result values',
            'authorize_lab_results' => 'Can authorise and verify laboratory results for release',
            'manage_lab_inventory' => 'Can manage laboratory reagent stock',
            'dispense_medication' => 'Can dispense prescriptions and manage pharmacy stock',
            'manage_billing' => 'Can view invoices and record patient payments',
            'manage_nhis_claims' => 'Can generate, submit, and track NHIS claims',
            'manage_admissions' => 'Can admit, transfer, and discharge patients',
            'manage_referrals' => 'Can accept, decline, and track patient referrals',
            'amend_clinical_notes' => 'Can amend a signed consultation belonging to another clinician',
            'override_consultation_lock' => 'Can break-glass into a consultation another clinician is editing',
            'order_imaging' => 'Can request radiology investigations',
            'process_radiology_orders' => 'Can perform imaging, file reports, and manage study attachments',
            'manage_clinical_tasks' => 'Can view and close outstanding clinical tasks',
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
                'permissions' => ['view_patients', 'view_clinical_history', 'update_clinical_safety', 'order_lab_tests', 'manage_referrals', 'override_consultation_lock', 'order_imaging', 'manage_clinical_tasks'],
            ],
            'nurse' => [
                'name' => 'Nursing Staff',
                'permissions' => ['view_patients', 'register_patients', 'view_clinical_history', 'update_clinical_safety', 'manage_admissions', 'manage_referrals', 'manage_clinical_tasks'],
            ],
            'receptionist' => [
                'name' => 'Front Desk / Reception',
                'permissions' => ['view_patients', 'register_patients'],
            ],
            'pharmacist' => [
                'name' => 'Pharmacist',
                'permissions' => ['view_patients', 'view_clinical_history', 'dispense_medication'],
            ],
            'radiographer' => [
                'name' => 'Radiographer / Radiologist',
                'permissions' => ['view_patients', 'view_clinical_history', 'process_radiology_orders'],
            ],
            'lab_tech' => [
                'name' => 'Medical Laboratory Assistant',
                'permissions' => ['view_patients', 'view_clinical_history', 'collect_lab_specimens', 'enter_lab_results', 'manage_lab_inventory'],
            ],
            'lab_scientist' => [
                'name' => 'Medical Laboratory Scientist',
                'permissions' => ['view_patients', 'view_clinical_history', 'collect_lab_specimens', 'enter_lab_results', 'authorize_lab_results', 'manage_lab_inventory'],
            ],
            'pathologist' => [
                'name' => 'Pathologist',
                'permissions' => ['view_patients', 'view_clinical_history', 'collect_lab_specimens', 'enter_lab_results', 'authorize_lab_results', 'manage_lab_inventory'],
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
