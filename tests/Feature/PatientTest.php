<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Permissions
        $this->regPermission = Permission::updateOrCreate(['slug' => 'register_patients'], ['name' => 'Register Patients']);
        $this->viewPermission = Permission::updateOrCreate(['slug' => 'view_patients'], ['name' => 'View Patients']);

        // 2. Create Role and Attach Permissions
        $this->role = Role::updateOrCreate(['name' => 'Receptionist', 'slug' => 'receptionist']);
        $this->role->permissions()->sync([$this->regPermission->id, $this->viewPermission->id]);

        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);
    }

    public function test_can_search_for_patients()
    {
        Patient::create([
            'hospital_id' => 'HIMS-2026-00001',
            'full_name' => 'Kofi Mensah',
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->user)->get('/patients?search=Kofi');

        $response->assertStatus(200);
        $response->assertSee('Kofi Mensah');
    }

    public function test_can_register_new_patient()
    {
        $response = $this->actingAs($this->user)->post('/patients', [
            'full_name' => 'Ama Serwaa',
            'sex' => 'female',
            'estimated_age' => 30,
            'payment_method' => 'nhis',
            'nhis_number' => '12345678',
            'nhis_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('patients', [
            'full_name' => 'Ama Serwaa',
            'nhis_number' => '12345678',
        ]);

        $patient = Patient::where('full_name', 'Ama Serwaa')->first();
        $response->assertRedirect("/patients/{$patient->id}");
    }

    public function test_non_authorized_role_cannot_register_patient()
    {
        $doctorRole = Role::updateOrCreate(['name' => 'Doctor', 'slug' => 'doctor']);
        $doctor = User::factory()->create();
        $doctor->roles()->attach($doctorRole);

        $response = $this->actingAs($doctor)->get('/patients/create');
        $response->assertStatus(403);
    }

    public function test_nurse_can_register_new_patient()
    {
        $nurseRole = Role::updateOrCreate(['name' => 'Nurse', 'slug' => 'nurse']);
        $nurseRole->permissions()->sync([$this->regPermission->id]);

        $nurse = User::factory()->create();
        $nurse->roles()->attach($nurseRole);

        $response = $this->actingAs($nurse)->post('/patients', [
            'full_name' => 'Nurse Registered Patient',
            'sex' => 'male',
            'estimated_age' => 45,
            'payment_method' => 'cash',
        ]);

        $this->assertDatabaseHas('patients', ['full_name' => 'Nurse Registered Patient']);
    }
}
