<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LabOrder;
use App\Models\LabSample;
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

    /**
     * Attach a set of permissions to a role.
     *
     * @param  list<string>  $slugs
     */
    protected function grant(Role $role, array $slugs): void
    {
        $role->permissions()->sync(
            collect($slugs)
                ->map(fn (string $slug) => Permission::updateOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $this->grant($this->doctorRole, ['order_lab_tests', 'view_clinical_history']);

        // Collecting, entering a value and authorising release are separate
        // licensed acts. This actor is a Medical Laboratory Scientist, who holds
        // all three, so one user can carry the whole flow in these tests.
        $this->labRole = Role::create(['name' => 'Medical Laboratory Scientist', 'slug' => 'lab_scientist']);
        $this->grant($this->labRole, [
            'collect_lab_specimens', 'enter_lab_results', 'authorize_lab_results', 'view_clinical_history',
        ]);

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

    public function test_the_consultation_workspace_offers_the_doctor_a_lab_request_form()
    {
        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Request Laboratory Tests');
        // The formulary has to be reachable, not just the heading.
        $response->assertSee($this->labTestModel->name);
        $response->assertSee(route('consultations.labs.store', $this->visit), false);
    }

    public function test_a_clinician_without_the_ordering_permission_is_not_shown_the_lab_request_form()
    {
        $role = Role::create(['name' => 'Observer', 'slug' => 'observer']);
        $observer = User::factory()->create();
        $observer->roles()->attach($role);

        $response = $this->actingAs($observer)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertDontSee('Request Laboratory Tests');
    }

    public function test_ordering_labs_without_the_permission_is_forbidden()
    {
        $role = Role::create(['name' => 'Observer', 'slug' => 'observer']);
        $observer = User::factory()->create();
        $observer->roles()->attach($role);

        $this->actingAs($observer)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [$this->labTestModel->id],
            'priority' => 'routine',
        ])->assertForbidden();

        $this->assertDatabaseCount('lab_orders', 0);
    }

    public function test_a_doctor_can_raise_more_than_one_lab_request_for_the_same_visit()
    {
        $second = LabTest::create([
            'name' => 'Full Blood Count', 'code' => 'FBC', 'specimen_type' => 'Blood', 'price' => 40,
        ]);

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [$this->labTestModel->id],
            'priority' => 'routine',
        ]);

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/labs", [
            'lab_tests' => [$second->id],
            'priority' => 'stat',
        ]);

        $this->assertDatabaseCount('lab_orders', 2);
        $this->assertSame(2, $this->visit->refresh()->outstandingLabOrders()->count());
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

        // New sample-based collection
        $collect = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/collect", [
            'specimen_type' => 'Blood',
            'item_ids' => [$item->id],
        ]);
        $collect->assertRedirect();
        $this->assertDatabaseHas('lab_order_items', ['id' => $item->id, 'specimen_status' => 'collected']);

        $sample = LabSample::first();
        $this->actingAs($this->labTech)->post("/lab/samples/{$sample->id}/receive");

        $verifyBeforeResults = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/verify");
        $verifyBeforeResults->assertStatus(422);

        $result = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/items/{$item->id}/result", [
            'result_value' => 'Negative',
            'result_flag' => 'normal',
            'lab_reagent_id' => null,
        ]);
        $result->assertRedirect();

        // Authorize before verify
        $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/authorize");

        $verify = $this->actingAs($this->labTech)->post("/lab/{$labOrder->id}/verify");
        $verify->assertRedirect('/lab');
        $this->assertDatabaseHas('lab_orders', ['id' => $labOrder->id, 'status' => 'verified', 'verified_by' => $this->labTech->id]);

        $this->visit->refresh();
        $this->assertEquals('waiting_doctor', $this->visit->status);
    }
}
