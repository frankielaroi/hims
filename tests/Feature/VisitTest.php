<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regPermission = Permission::updateOrCreate(['slug' => 'register_patients'], ['name' => 'Register Patients']);

        $this->role = Role::updateOrCreate(['name' => 'Receptionist', 'slug' => 'receptionist']);
        $this->role->permissions()->sync([$this->regPermission->id]);

        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);

        $this->dept = Department::create(['name' => 'General OPD', 'slug' => 'opd-general']);

        $this->patient = Patient::create([
            'hospital_id' => 'HIMS-2026-00001',
            'full_name' => 'Test Patient',
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);
    }

    public function test_receptionist_can_create_visit()
    {
        $response = $this->actingAs($this->user)->post("/patients/{$this->patient->id}/visits", [
            'department_id' => $this->dept->id,
            'type' => 'OPD General',
            'priority' => 'routine',
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect("/patients/{$this->patient->id}");
        $this->assertDatabaseHas('visits', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'type' => 'OPD General',
            'status' => 'waiting_triage',
        ]);

        $visit = Visit::first();
        $this->assertNotNull($visit->visit_number);
    }

    public function test_automatic_visit_number_generation()
    {
        $this->actingAs($this->user)->post("/patients/{$this->patient->id}/visits", [
            'department_id' => $this->dept->id,
            'type' => 'Emergency',
            'priority' => 'emergency',
            'payment_method' => 'cash',
        ]);

        $visit = Visit::first();
        $date = date('Ymd');
        $this->assertStringStartsWith("V-{$date}-", $visit->visit_number);
    }

    public function test_nhis_verification_checkbox_is_recorded_on_the_visit()
    {
        $this->actingAs($this->user)->post("/patients/{$this->patient->id}/visits", [
            'department_id' => $this->dept->id,
            'type' => 'OPD General',
            'priority' => 'routine',
            'payment_method' => 'nhis',
            'nhis_verified' => '1',
        ]);

        $visit = Visit::first();
        $this->assertTrue($visit->nhis_verified);
        $this->assertEquals($this->user->id, $visit->nhis_verified_by);
        $this->assertNotNull($visit->nhis_verified_at);
    }

    public function test_nhis_verification_defaults_to_false_when_not_checked()
    {
        $this->actingAs($this->user)->post("/patients/{$this->patient->id}/visits", [
            'department_id' => $this->dept->id,
            'type' => 'OPD General',
            'priority' => 'routine',
            'payment_method' => 'nhis',
        ]);

        $visit = Visit::first();
        $this->assertFalse($visit->nhis_verified);
        $this->assertNull($visit->nhis_verified_by);
    }
}
