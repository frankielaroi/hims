<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TheatreOperation;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TheatreTest extends TestCase
{
    use RefreshDatabase;

    protected User $surgeon;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $role->permissions()->sync([
            Permission::updateOrCreate(['slug' => 'manage_theatre'], ['name' => 'manage_theatre'])->id,
        ]);

        $this->surgeon = User::factory()->create();
        $this->surgeon->roles()->attach($role);

        $this->visit = Visit::factory()->create();
    }

    protected function openOperation(array $overrides = []): TheatreOperation
    {
        $this->actingAs($this->surgeon)->post("/visits/{$this->visit->id}/operations", array_merge([
            'procedure' => 'Emergency caesarean section',
            'urgency' => 'emergency',
            'anaesthesia_type' => 'spinal',
        ], $overrides));

        return TheatreOperation::where('visit_id', $this->visit->id)->latest('id')->sole();
    }

    public function test_an_operation_can_be_opened_and_closed(): void
    {
        $operation = $this->openOperation();

        $this->assertSame(TheatreOperation::STATUS_IN_THEATRE, $operation->status);

        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'surgeon_id' => $this->surgeon->id,
            'anaesthetist_id' => $this->surgeon->id,
            'findings' => 'Live female infant delivered.',
            'estimated_blood_loss_ml' => 600,
            'swab_count_correct' => 1,
        ])->assertSessionHasNoErrors();

        $operation->refresh();

        $this->assertSame(TheatreOperation::STATUS_COMPLETED, $operation->status);
        $this->assertTrue($operation->swab_count_correct);
        $this->assertSame([], $operation->alerts());
        $this->assertDatabaseHas('audit_logs', ['action' => 'operation_completed']);
    }

    public function test_the_swab_count_must_be_answered(): void
    {
        $operation = $this->openOperation();

        // A retained swab is a never-event; not recording the count is not the
        // same as the count being correct.
        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'surgeon_id' => $this->surgeon->id,
        ])->assertSessionHasErrors('swab_count_correct');

        $this->assertSame(TheatreOperation::STATUS_IN_THEATRE, $operation->refresh()->status);
    }

    public function test_an_incorrect_swab_count_is_raised_loudly(): void
    {
        $operation = $this->openOperation();

        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'surgeon_id' => $this->surgeon->id,
            'swab_count_correct' => 0,
        ])->assertSessionHas('warning');

        $this->assertContains(
            'Swab and instrument count INCORRECT — account for every item before the patient leaves theatre',
            $operation->refresh()->alerts()
        );
    }

    public function test_heavy_blood_loss_is_flagged(): void
    {
        $operation = $this->openOperation();

        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'surgeon_id' => $this->surgeon->id,
            'swab_count_correct' => 1,
            'estimated_blood_loss_ml' => 1500,
            'complications' => ['haemorrhage'],
        ])->assertSessionHas('warning');

        $alerts = $operation->refresh()->alerts();

        $this->assertContains('Haemorrhage (1500 ml)', $alerts);
        $this->assertContains('Complication: Haemorrhage', $alerts);
    }

    public function test_a_caesarean_links_back_to_its_delivery(): void
    {
        $delivery = Delivery::factory()->delivered()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->visit->patient_id,
            'mode' => 'caesarean',
        ]);

        $operation = $this->openOperation(['delivery_id' => $delivery->id]);

        $this->assertSame($delivery->id, $operation->delivery_id);
        $this->assertTrue($delivery->refresh()->operations->contains($operation));
    }

    public function test_an_operation_cannot_be_linked_to_another_visits_delivery(): void
    {
        $otherDelivery = Delivery::factory()->delivered()->create();

        $this->actingAs($this->surgeon)->post("/visits/{$this->visit->id}/operations", [
            'procedure' => 'Caesarean section',
            'urgency' => 'emergency',
            'delivery_id' => $otherDelivery->id,
        ])->assertStatus(422);
    }

    public function test_an_operation_cannot_be_closed_twice(): void
    {
        $operation = $this->openOperation();

        $payload = [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'surgeon_id' => $this->surgeon->id,
            'swab_count_correct' => 1,
        ];

        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", $payload);

        $this->actingAs($this->surgeon)->post("/operations/{$operation->id}/complete", $payload)
            ->assertStatus(422);
    }

    public function test_theatre_is_closed_without_the_permission(): void
    {
        $role = Role::create(['name' => 'Receptionist', 'slug' => 'receptionist']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/theatre')->assertForbidden();
    }
}
