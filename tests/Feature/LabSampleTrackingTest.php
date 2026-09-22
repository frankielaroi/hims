<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LabOrder;
use App\Models\LabReagent;
use App\Models\LabSample;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabSampleTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->labTechRole = Role::create(['name' => 'Lab Technician', 'slug' => 'lab_tech']);
        $this->scientistRole = Role::create(['name' => 'Senior Scientist', 'slug' => 'scientist']);
        $this->doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);

        $this->labTech = User::factory()->create();
        $this->labTech->roles()->attach($this->labTechRole);

        $this->scientist = User::factory()->create();
        $this->scientist->roles()->attach($this->scientistRole);

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($this->doctorRole);

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

        $this->test1 = LabTest::create(['name' => 'FBC', 'code' => 'FBC', 'specimen_type' => 'Blood', 'price' => 40]);
        $this->test2 = LabTest::create(['name' => 'Blood Film', 'code' => 'BF', 'specimen_type' => 'Blood', 'price' => 20]);
    }

    public function test_many_items_can_belong_to_one_sample()
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'status' => 'ordered',
        ]);

        $item1 = $order->items()->create(['lab_test_id' => $this->test1->id, 'specimen_type' => 'Blood']);
        $item2 = $order->items()->create(['lab_test_id' => $this->test2->id, 'specimen_type' => 'Blood']);

        $this->actingAs($this->labTech)->post("/lab/{$order->id}/collect", [
            'specimen_type' => 'Blood',
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $this->assertDatabaseCount('lab_samples', 1);
        $sample = LabSample::first();

        $this->assertDatabaseHas('lab_order_items', ['id' => $item1->id, 'lab_sample_id' => $sample->id]);
        $this->assertDatabaseHas('lab_order_items', ['id' => $item2->id, 'lab_sample_id' => $sample->id]);
    }

    public function test_sample_rejection_flow()
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'status' => 'ordered',
        ]);

        $item = $order->items()->create(['lab_test_id' => $this->test1->id, 'specimen_type' => 'Blood']);

        $this->actingAs($this->labTech)->post("/lab/{$order->id}/collect", [
            'specimen_type' => 'Blood',
            'item_ids' => [$item->id],
        ]);

        $sample = LabSample::first();

        $this->actingAs($this->labTech)->post("/lab/samples/{$sample->id}/reject", [
            'rejection_reason' => 'Hemolyzed',
        ]);

        $sample->refresh();
        $this->assertEquals('rejected', $sample->status);
        $this->assertEquals('Hemolyzed', $sample->rejection_reason);

        $this->assertDatabaseHas('lab_order_items', [
            'id' => $item->id,
            'lab_sample_id' => null,
            'specimen_status' => 'pending_collection',
        ]);
    }

    public function test_multi_tier_authorization_workflow()
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'status' => 'ordered',
        ]);

        $item = $order->items()->create(['lab_test_id' => $this->test1->id, 'specimen_type' => 'Blood']);

        $sample = LabSample::create([
            'lab_order_id' => $order->id,
            'barcode_number' => 'S-1',
            'specimen_type' => 'Blood',
            'status' => 'received',
        ]);

        $item->update(['lab_sample_id' => $sample->id, 'specimen_status' => 'collected']);

        // Tech saves result
        $this->actingAs($this->labTech)->post("/lab/{$order->id}/items/{$item->id}/result", [
            'result_value' => 'Abnormal findings',
            'result_flag' => 'abnormal',
        ]);

        $order->refresh();
        $this->assertEquals('in_progress', $order->status);
        $this->assertFalse($order->allResultsAuthorized());

        // Attempt verify before authorization should fail
        $response = $this->actingAs($this->labTech)->post("/lab/{$order->id}/verify");
        $response->assertStatus(422);

        // Scientist authorizes
        $this->actingAs($this->scientist)->post("/lab/{$order->id}/authorize");

        $order->refresh();
        $this->assertTrue($order->allResultsAuthorized());
        $this->assertEquals('completed', $order->status);

        // Final verification
        $this->actingAs($this->labTech)->post("/lab/{$order->id}/verify");
        $order->refresh();
        $this->assertEquals('verified', $order->status);
    }

    public function test_reagent_stock_validation_and_alerts()
    {
        $reagent = LabReagent::create([
            'name' => 'Test Kit',
            'code' => 'TK-01',
            'batch_number' => 'B1',
            'current_stock' => 1,
            'min_stock_level' => 5,
            'unit' => 'tests',
        ]);

        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'status' => 'ordered',
        ]);

        $item = $order->items()->create(['lab_test_id' => $this->test1->id, 'specimen_type' => 'Blood']);
        $sample = LabSample::create(['lab_order_id' => $order->id, 'barcode_number' => 'S-1', 'specimen_type' => 'Blood', 'status' => 'received']);
        $item->update(['lab_sample_id' => $sample->id, 'specimen_status' => 'collected']);

        // Use last remaining kit
        $this->actingAs($this->labTech)->post("/lab/{$order->id}/items/{$item->id}/result", [
            'result_value' => 'Positive',
            'result_flag' => 'normal',
            'lab_reagent_id' => $reagent->id,
        ]);

        $reagent->refresh();
        $this->assertEquals(0, $reagent->current_stock);

        // Try to use kit when stock is 0
        $order2 = LabOrder::create(['visit_id' => $this->visit->id, 'ordered_by' => $this->doctor->id]);
        $item2 = $order2->items()->create(['lab_test_id' => $this->test2->id, 'specimen_type' => 'Blood']);
        $item2->update(['lab_sample_id' => $sample->id, 'specimen_status' => 'collected']);

        $response = $this->actingAs($this->labTech)->post("/lab/{$order2->id}/items/{$item2->id}/result", [
            'result_value' => 'Positive',
            'result_flag' => 'normal',
            'lab_reagent_id' => $reagent->id,
        ]);

        $response->assertSessionHasErrors('lab_reagent_id');
    }
}
