<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalsRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->role = Role::create(['name' => 'Nurse', 'slug' => 'nurse']);
        $this->user = User::factory()->create();
        $this->user->roles()->attach($this->role);

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create(['full_name' => 'Test', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);

        $this->visit = Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-001',
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_nurse_can_complete_triage()
    {
        $response = $this->actingAs($this->user)->post("/triage/{$this->visit->id}", [
            'temperature' => 37.5,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'weight' => 70,
            'height' => 170,
            'chief_complaint' => 'Persistent cough',
            'priority_level' => 'urgent',
        ]);

        $response->assertRedirect('/triage');
        $this->assertDatabaseHas('vitals_records', ['visit_id' => $this->visit->id, 'temperature' => 37.5]);
        $this->assertDatabaseHas('triage_assessments', ['visit_id' => $this->visit->id, 'priority_level' => 'urgent']);

        $this->visit->refresh();
        $this->assertEquals('waiting_doctor', $this->visit->status);
        $this->assertEquals('urgent', $this->visit->priority);
    }

    public function test_bmi_is_automatically_calculated()
    {
        $this->actingAs($this->user)->post("/triage/{$this->visit->id}", [
            'weight' => 70, // kg
            'height' => 175, // cm
            'chief_complaint' => 'Test',
            'priority_level' => 'routine',
        ]);

        $vitals = VitalsRecord::where('visit_id', $this->visit->id)->first();
        // BMI = 70 / (1.75 * 1.75) = 22.857...
        $this->assertEquals(22.9, $vitals->bmi);
    }
}
