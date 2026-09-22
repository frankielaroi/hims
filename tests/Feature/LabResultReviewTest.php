<?php

namespace Tests\Feature;

use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabResultReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $scientist;

    protected Visit $visit;

    protected LabTest $test;

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

        // Releasing a result and reading it are two different licensed roles.
        $doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $this->grant($doctorRole, ['order_lab_tests', 'view_clinical_history']);
        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($doctorRole);

        $scientistRole = Role::create(['name' => 'Medical Laboratory Scientist', 'slug' => 'lab_scientist']);
        $this->grant($scientistRole, ['authorize_lab_results', 'view_clinical_history']);
        $this->scientist = User::factory()->create();
        $this->scientist->roles()->attach($scientistRole);

        $this->visit = Visit::factory()->create([
            'created_by' => $this->doctor->id,
            'status' => 'waiting_lab',
        ]);

        $this->test = LabTest::create([
            'name' => 'Full Blood Count',
            'code' => 'FBC',
            'category' => 'Haematology',
            'specimen_type' => 'Blood',
            'price' => 75,
            'is_nhis_covered' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Build an order whose results are authorised and ready for verification.
     */
    protected function authorizedOrder(string $flag = 'normal'): LabOrder
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'routine',
            'status' => 'in_progress',
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order->id,
            'lab_test_id' => $this->test->id,
            'specimen_type' => 'Blood',
            'result_value' => '4.2',
            'result_flag' => $flag,
            'performed_by' => $this->doctor->id,
            'performed_at' => now(),
            'authorized_by' => $this->doctor->id,
            'authorized_at' => now(),
        ]);

        return $order;
    }

    public function test_verifying_the_only_order_sends_the_patient_back_to_the_doctor(): void
    {
        $order = $this->authorizedOrder();

        $this->actingAs($this->scientist)->post("/lab/{$order->id}/verify")->assertRedirect('/lab');

        $this->assertSame('verified', $order->refresh()->status);
        $this->assertSame('waiting_doctor', $this->visit->refresh()->status);
    }

    public function test_the_patient_stays_in_the_lab_while_another_order_is_outstanding(): void
    {
        $first = $this->authorizedOrder();
        $second = $this->authorizedOrder();

        $this->actingAs($this->scientist)->post("/lab/{$first->id}/verify");

        // The second order is still with the laboratory, so the clinician would
        // otherwise be handed a partial picture.
        $this->assertSame('waiting_lab', $this->visit->refresh()->status);

        $this->actingAs($this->scientist)->post("/lab/{$second->id}/verify");

        $this->assertSame('waiting_doctor', $this->visit->refresh()->status);
    }

    public function test_recording_a_review_captures_who_read_the_results_and_what_they_did(): void
    {
        $order = $this->authorizedOrder();
        $this->actingAs($this->scientist)->post("/lab/{$order->id}/verify");

        $response = $this->actingAs($this->doctor)->post("/lab/{$order->id}/review", [
            'review_action' => 'treatment_changed',
            'review_note' => 'Switched to oral iron.',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertSame($this->doctor->id, $order->reviewed_by);
        $this->assertNotNull($order->reviewed_at);
        $this->assertSame('treatment_changed', $order->review_action);
    }

    public function test_results_cannot_be_reviewed_before_the_laboratory_verifies_them(): void
    {
        $order = $this->authorizedOrder();

        $this->actingAs($this->doctor)
            ->post("/lab/{$order->id}/review", ['review_action' => 'acknowledged'])
            ->assertStatus(422);

        $this->assertNull($order->refresh()->reviewed_at);
    }

    public function test_a_critical_result_must_be_acknowledged_before_the_order_can_be_reviewed(): void
    {
        $order = $this->authorizedOrder('critical');
        $this->actingAs($this->scientist)->post("/lab/{$order->id}/verify");

        $response = $this->actingAs($this->doctor)->post("/lab/{$order->id}/review", [
            'review_action' => 'acknowledged',
        ]);

        $response->assertSessionHasErrors('review_action');
        $this->assertNull($order->refresh()->reviewed_at);
    }

    public function test_acknowledging_a_critical_result_records_who_read_it_back(): void
    {
        $order = $this->authorizedOrder('critical');
        $item = $order->items()->sole();
        $this->actingAs($this->scientist)->post("/lab/{$order->id}/verify");

        $this->actingAs($this->doctor)
            ->post("/lab/{$order->id}/items/{$item->id}/acknowledge-critical")
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($this->doctor->id, $item->critical_acknowledged_by);
        $this->assertNotNull($item->critical_acknowledged_at);

        // With the read-back recorded, the review can now be filed.
        $this->actingAs($this->doctor)->post("/lab/{$order->id}/review", [
            'review_action' => 'escalated',
        ]);

        $this->assertNotNull($order->refresh()->reviewed_at);
    }

    public function test_a_normal_result_does_not_need_acknowledgement(): void
    {
        $order = $this->authorizedOrder();
        $item = $order->items()->sole();

        $this->actingAs($this->doctor)
            ->post("/lab/{$order->id}/items/{$item->id}/acknowledge-critical")
            ->assertStatus(422);
    }
}
