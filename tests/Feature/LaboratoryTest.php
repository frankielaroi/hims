<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $orderPerm = Permission::updateOrCreate(['slug' => 'order_lab_tests'], ['name' => 'Order Lab Tests']);
        $this->doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $this->doctorRole->permissions()->sync([$orderPerm->id]);

        $processPerm = Permission::updateOrCreate(['slug' => 'process_lab_orders'], ['name' => 'Process Lab Orders']);
        $this->labRole = Role::create(['name' => 'Lab Technician', 'slug' => 'lab_tech']);
        $this->labRole->permissions()->sync([$processPerm->id]);

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($this->doctorRole);

        $this->labTech = User::factory()->create();
        $this->labTech->roles()->attach($this->labRole);

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);

        $this->visit = Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-001',
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->doctor->id,
            'status' => 'waiting_doctor',
        ]);

        $this->labTestModel = LabTest::create([
            'name' => 'Malaria RDT',
            'code' => 'MP-RDT',
            'specimen_type' => 'Blood',
            'reference_range' => 'Negative',
            'price' => 15,
        ]);
    }

    public function test_doctor_can_order_lab_tests()
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [$this->labTestModel->id],
            'priority' => 'urgent',
            'clinical_indication' => 'Fever and chills for 3 days',
        ]);

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('lab_orders', [
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'urgent',
        ]);
        $this->assertDatabaseHas('lab_order_items', ['lab_test_id' => $this->labTestModel->id]);

        $this->visit->refresh();
        $this->assertEquals('waiting_lab', $this->visit->status);
    }

    public function test_lab_order_requires_at_least_one_test()
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [],
            'priority' => 'routine',
        ]);

        $response->assertSessionHasErrors('lab_tests');
        $this->assertDatabaseCount('lab_orders', 0);
    }

    public function test_lab_tech_can_collect_specimen_then_record_and_verify_result()
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [$this->labTestModel->id],
            'priority' => 'routine',
        ]);

        $labOrder = LabOrder::first();
        $item = $labOrder->items->first();

        $collect = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/items/{$item->id}/collect");
        $collect->assertRedirect();
        $this->assertDatabaseHas('lab_order_items', ['id' => $item->id, 'specimen_status' => 'collected']);

        $verifyBeforeResults = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/verify");
        $verifyBeforeResults->assertStatus(422);

        $result = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/items/{$item->id}/result", [
            'result_value' => 'Negative',
            'result_flag' => 'normal',
        ]);
        $result->assertRedirect();
        $this->assertDatabaseHas('lab_order_items', ['id' => $item->id, 'result_value' => 'Negative', 'result_flag' => 'normal']);

        $verify = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/verify");
        $verify->assertRedirect('/lab');
        $this->assertDatabaseHas('lab_orders', ['id' => $labOrder->id, 'status' => 'verified', 'verified_by' => $this->labTech->id]);

        $this->visit->refresh();
        $this->assertEquals('waiting_doctor', $this->visit->status);
    }
}
