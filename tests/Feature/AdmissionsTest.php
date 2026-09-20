<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $perm = Permission::updateOrCreate(['slug' => 'manage_admissions'], ['name' => 'Manage Admissions']);
        $this->nurseRole = Role::create(['name' => 'Nurse', 'slug' => 'nurse']);
        $this->nurseRole->permissions()->sync([$perm->id]);

        $this->nurse = User::factory()->create();
        $this->nurse->roles()->attach($this->nurseRole);

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);

        $this->ward = Ward::create(['name' => 'General Ward A', 'category' => 'General', 'floor' => '1']);
        $this->bedA = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'A']);
        $this->bedB = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B']);

        $this->visit = Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-001',
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->nurse->id,
            'status' => 'awaiting_admission',
        ]);
    }

    public function test_nurse_can_admit_patient_to_an_available_bed()
    {
        $response = $this->actingAs($this->nurse)
            ->post("/admissions/visits/{$this->visit->id}/assign-bed", ['bed_id' => $this->bedA->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('admissions', ['visit_id' => $this->visit->id, 'bed_id' => $this->bedA->id, 'status' => 'admitted']);
        $this->assertEquals('occupied', $this->bedA->fresh()->status);

        $this->visit->refresh();
        $this->assertEquals('admitted', $this->visit->status);
    }

    public function test_cannot_admit_to_an_occupied_bed()
    {
        $this->bedA->update(['status' => 'occupied']);

        $response = $this->actingAs($this->nurse)
            ->post("/admissions/visits/{$this->visit->id}/assign-bed", ['bed_id' => $this->bedA->id]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_nurse_can_transfer_patient_between_beds()
    {
        $this->actingAs($this->nurse)->post("/admissions/visits/{$this->visit->id}/assign-bed", ['bed_id' => $this->bedA->id]);
        $admission = Admission::first();

        $response = $this->actingAs($this->nurse)
            ->post("/admissions/{$admission->id}/transfer", ['bed_id' => $this->bedB->id, 'reason' => 'Needs isolation']);

        $response->assertRedirect();
        $admission->refresh();
        $this->assertEquals($this->bedB->id, $admission->bed_id);
        $this->assertEquals('cleaning', $this->bedA->fresh()->status);
        $this->assertEquals('occupied', $this->bedB->fresh()->status);
        $this->assertDatabaseHas('bed_transfers', ['admission_id' => $admission->id, 'from_bed_id' => $this->bedA->id, 'to_bed_id' => $this->bedB->id]);
    }

    public function test_discharge_frees_the_bed_charges_ward_stay_and_completes_the_visit()
    {
        Service::create(['name' => 'Ward Stay (General)', 'category' => 'Ward', 'price' => 100, 'is_nhis_covered' => false, 'code' => 'WARD-GEN']);

        $this->actingAs($this->nurse)->post("/admissions/visits/{$this->visit->id}/assign-bed", ['bed_id' => $this->bedA->id]);
        $admission = Admission::first();

        $response = $this->actingAs($this->nurse)
            ->post("/admissions/{$admission->id}/discharge", ['discharge_notes' => 'Recovered well']);

        $response->assertRedirect('/admissions');
        $admission->refresh();
        $this->assertEquals('discharged', $admission->status);
        $this->assertEquals('cleaning', $this->bedA->fresh()->status);

        $this->visit->refresh();
        $this->assertEquals('completed', $this->visit->status);

        $this->assertDatabaseHas('invoice_items', ['description' => 'Ward Stay (General)', 'category' => 'Ward']);
    }

    public function test_bed_can_be_marked_available_after_cleaning()
    {
        $this->bedA->update(['status' => 'cleaning']);

        $response = $this->actingAs($this->nurse)->post("/beds/{$this->bedA->id}/mark-available");

        $response->assertRedirect();
        $this->assertEquals('available', $this->bedA->fresh()->status);
    }

    public function test_occupied_bed_cannot_be_marked_available_directly()
    {
        $this->bedA->update(['status' => 'occupied']);

        $response = $this->actingAs($this->nurse)->post("/beds/{$this->bedA->id}/mark-available");

        $response->assertStatus(422);
        $this->assertEquals('occupied', $this->bedA->fresh()->status);
    }
}
