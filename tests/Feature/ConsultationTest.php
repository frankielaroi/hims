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

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewPerm = Permission::updateOrCreate(['slug' => 'view_clinical_history'], ['name' => 'View History']);
        $this->role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $this->role->permissions()->sync([$this->viewPerm->id]);

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
            'status' => 'waiting_doctor',
        ]);

        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);
    }

    public function test_doctor_can_complete_consultation()
    {
        $response = $this->actingAs($this->user)->post("/triage/{$this->visit->id}", [
            // nurse logic stub if needed, but we test doc store directly
        ]);

        $response = $this->actingAs($this->user)->post("/consultations/{$this->visit->id}", [
            'clinical_notes' => 'Patient has high fever and chills.',
            'diagnoses' => [
                [
                    'code' => 'B54',
                    'description' => 'Suspected Uncomplicated Malaria',
                    'is_primary' => true,
                ],
            ],
            'outcome' => 'discharge',
            'follow_up_date' => now()->addWeek()->format('Y-m-d'),
        ]);

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('consultations', ['visit_id' => $this->visit->id, 'status' => 'finalized']);
        $this->assertDatabaseHas('diagnoses', ['icd10_code' => 'B54', 'is_primary' => true]);
        $this->assertDatabaseHas('visit_outcomes', ['visit_id' => $this->visit->id, 'outcome' => 'discharge']);

        $this->visit->refresh();
        $this->assertEquals('completed', $this->visit->status);
    }
}
