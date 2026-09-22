<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regPermission = Permission::updateOrCreate(['slug' => 'register_patients'], ['name' => 'Register Patients']);
        $this->role = Role::create(['name' => 'Receptionist', 'slug' => 'receptionist']);
        $this->role->permissions()->sync([$this->regPermission->id]);

        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);

        Department::create(['name' => 'Emergency', 'slug' => 'emergency']);
    }

    public function test_can_register_an_unidentified_emergency_arrival()
    {
        $response = $this->actingAs($this->user)->post('/emergency/register', [
            'is_unidentified' => '1',
            'apparent_sex' => 'male',
            'estimated_age' => 35,
            'reason_for_visit' => 'Found unconscious, road traffic accident',
        ]);

        $patient = Patient::first();
        $this->assertNotNull($patient);
        $this->assertTrue($patient->is_unidentified);
        $this->assertStringContainsString('Unidentified Male', $patient->full_name);

        $visit = Visit::first();
        $this->assertEquals('emergency', $visit->priority);
        $this->assertEquals('waiting_triage', $visit->status);
        $this->assertEquals('Emergency', $visit->type);

        $response->assertRedirect(route('triage.create', $visit));
    }

    public function test_known_patient_name_is_required_when_not_marked_unidentified()
    {
        $response = $this->actingAs($this->user)->post('/emergency/register', [
            'apparent_sex' => 'female',
            'reason_for_visit' => 'Severe chest pain',
        ]);

        $response->assertSessionHasErrors('full_name');
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_emergency_board_only_shows_active_emergency_visits()
    {
        $dept = Department::first();
        $patient = Patient::create(['full_name' => 'Active Case', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);
        $completedPatient = Patient::create(['full_name' => 'Closed Case', 'sex' => 'male', 'hospital_id' => 'P-002', 'payment_method' => 'cash']);

        $active = Visit::create([
            'patient_id' => $patient->id, 'department_id' => $dept->id, 'visit_number' => 'V-A', 'queue_number' => 1,
            'type' => 'Emergency', 'priority' => 'emergency', 'payment_method' => 'cash', 'created_by' => $this->user->id,
            'status' => 'waiting_triage',
        ]);

        Visit::create([
            'patient_id' => $completedPatient->id, 'department_id' => $dept->id, 'visit_number' => 'V-B', 'queue_number' => 2,
            'type' => 'Emergency', 'priority' => 'emergency', 'payment_method' => 'cash', 'created_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get('/emergency');

        $response->assertSee('Active Case');
        $response->assertDontSee('Closed Case');
    }

    public function test_consultation_can_be_finalized_as_dama_or_deceased()
    {
        $doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $doctor = User::factory()->create();
        $doctor->roles()->attach($doctorRole);

        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);
        $dept = Department::first();
        $patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-003', 'payment_method' => 'cash']);

        $visit = Visit::create([
            'patient_id' => $patient->id, 'department_id' => $dept->id, 'visit_number' => 'V-C', 'queue_number' => 1,
            'type' => 'Emergency', 'priority' => 'emergency', 'payment_method' => 'cash', 'created_by' => $doctor->id,
            'status' => 'waiting_doctor',
        ]);

        $response = $this->actingAs($doctor)->post("/consultations/{$visit->id}", [
            'clinical_notes' => 'Patient arrived in critical condition.',
            'diagnoses' => [['code' => 'B54', 'description' => 'Severe malaria', 'is_primary' => true]],
            'outcome' => 'deceased',
            // Certifying a death now requires the time, cause and manner.
            'death_died_at' => now()->subMinutes(20)->format('Y-m-d H:i:s'),
            'death_immediate_cause' => 'Cerebral malaria',
            'death_manner' => 'natural',
        ]);

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('visit_outcomes', ['visit_id' => $visit->id, 'outcome' => 'deceased']);
        $this->assertDatabaseHas('death_records', [
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'immediate_cause' => 'Cerebral malaria',
            'manner' => 'natural',
            'certified_by' => $doctor->id,
        ]);
        $this->assertEquals('completed', $visit->fresh()->status);
    }
}
