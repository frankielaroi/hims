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

/**
 * The AHPC boundary: a Medical Laboratory Assistant may register a sample and
 * enter a raw metric, but only a licensed Medical Laboratory Scientist or
 * Pathologist may authorise a result for release. These are the refusals, which
 * are the half of the record a HEFRA audit actually asks to see.
 */
class LabAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $assistant;

    protected User $scientist;

    protected User $receptionist;

    protected User $doctor;

    protected Visit $visit;

    protected LabTest $labTest;

    /**
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

    /**
     * @param  list<string>  $slugs
     */
    protected function userWithPermissions(string $roleSlug, array $slugs): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $this->grant($role, $slugs);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assistant = $this->userWithPermissions('lab_tech', [
            'collect_lab_specimens', 'enter_lab_results', 'view_clinical_history',
        ]);

        $this->scientist = $this->userWithPermissions('lab_scientist', [
            'collect_lab_specimens', 'enter_lab_results', 'authorize_lab_results', 'view_clinical_history',
        ]);

        $this->receptionist = $this->userWithPermissions('receptionist', ['view_patients']);

        $this->doctor = $this->userWithPermissions('doctor', ['order_lab_tests', 'view_clinical_history']);

        $department = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $patient = Patient::create([
            'full_name' => 'Ama Owusu',
            'sex' => 'female',
            'hospital_id' => 'P-900',
            'payment_method' => 'cash',
        ]);

        $this->visit = Visit::create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'visit_number' => 'V-900',
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->doctor->id,
            'status' => 'waiting_lab',
        ]);

        $this->labTest = LabTest::create([
            'name' => 'Full Blood Count',
            'code' => 'FBC-AUTH',
            'specimen_type' => 'Blood',
            'price' => 40,
        ]);
    }

    /**
     * An order whose result has been entered but not yet authorised.
     */
    protected function orderAwaitingAuthorization(?User $enteredBy = null): LabOrder
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'routine',
            'status' => 'in_progress',
        ]);

        $order->items()->create([
            'lab_test_id' => $this->labTest->id,
            'specimen_type' => 'Blood',
            'specimen_status' => 'collected',
            'result_value' => '12.1',
            'result_flag' => 'normal',
            'entered_by' => ($enteredBy ?? $this->assistant)->id,
            'performed_by' => ($enteredBy ?? $this->assistant)->id,
            'performed_at' => now(),
        ]);

        return $order;
    }

    public function test_an_assistant_cannot_authorize_results_for_release()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->assistant)
            ->post("/lab/{$order->id}/authorize")
            ->assertForbidden();

        $this->assertDatabaseHas('lab_order_items', [
            'lab_order_id' => $order->id,
            'authorized_at' => null,
        ]);
    }

    public function test_an_assistant_cannot_verify_an_order()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->assistant)
            ->post("/lab/{$order->id}/verify")
            ->assertForbidden();

        $this->assertNotSame('verified', $order->fresh()->status);
    }

    public function test_a_scientist_can_authorize_results_an_assistant_entered()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->scientist)
            ->post("/lab/{$order->id}/authorize")
            ->assertRedirect();

        $item = $order->items()->first();

        $this->assertNotNull($item->authorized_at);
        $this->assertSame($this->scientist->id, $item->authorized_by);
        $this->assertSame($this->assistant->id, $item->entered_by);
    }

    public function test_a_user_without_the_entry_permission_cannot_record_a_result()
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'routine',
            'status' => 'specimen_collected',
        ]);

        $item = $order->items()->create([
            'lab_test_id' => $this->labTest->id,
            'specimen_type' => 'Blood',
            'specimen_status' => 'collected',
        ]);

        $this->actingAs($this->receptionist)
            ->post("/lab/{$order->id}/items/{$item->id}/result", [
                'result_value' => '9.9',
                'result_flag' => 'normal',
            ])
            ->assertForbidden();

        $this->assertNull($item->fresh()->result_value);
    }

    public function test_a_refused_action_is_written_to_the_audit_log()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->assistant)->post("/lab/{$order->id}/authorize");

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->assistant->id,
            'action' => 'authorization_denied',
        ]);
    }

    public function test_a_user_with_no_lab_permissions_cannot_open_the_worklist()
    {
        $this->actingAs($this->receptionist)
            ->get('/lab')
            ->assertForbidden();
    }

    public function test_second_person_rule_stops_a_scientist_signing_off_their_own_entry()
    {
        config(['lab.require_second_person' => true]);

        $order = $this->orderAwaitingAuthorization($this->scientist);

        $this->actingAs($this->scientist)
            ->post("/lab/{$order->id}/authorize")
            ->assertSessionHasErrors('authorize');

        $this->assertNull($order->items()->first()->authorized_at);
    }

    public function test_second_person_rule_allows_a_colleague_to_sign_off()
    {
        config(['lab.require_second_person' => true]);

        $colleague = $this->userWithPermissions('pathologist', [
            'authorize_lab_results', 'view_clinical_history',
        ]);

        $order = $this->orderAwaitingAuthorization($this->scientist);

        $this->actingAs($colleague)
            ->post("/lab/{$order->id}/authorize")
            ->assertRedirect();

        $this->assertSame($colleague->id, $order->items()->first()->authorized_by);
    }

    public function test_an_assistant_is_not_shown_the_authorize_control()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->assistant)
            ->get("/lab/{$order->id}")
            ->assertOk()
            ->assertDontSee('Authorize Results');
    }

    public function test_a_scientist_is_shown_the_authorize_control()
    {
        $order = $this->orderAwaitingAuthorization();

        $this->actingAs($this->scientist)
            ->get("/lab/{$order->id}")
            ->assertOk()
            ->assertSee('Authorize Results');
    }

    public function test_the_second_person_rule_is_off_by_default()
    {
        $order = $this->orderAwaitingAuthorization($this->scientist);

        $this->actingAs($this->scientist)
            ->post("/lab/{$order->id}/authorize")
            ->assertRedirect();

        $this->assertNotNull($order->items()->first()->authorized_at);
    }
}
