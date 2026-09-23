<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\NewbornRecord;
use App\Models\Permission;
use App\Models\PostnatalNewbornCheck;
use App\Models\PostnatalVisit;
use App\Models\Role;
use App\Models\User;
use App\Services\PostnatalSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ghana's 6-hour, 6-day, 6-week postnatal contacts. A contact that did not
 * happen matters as much as one that did, so most of this is about the
 * schedule noticing.
 */
class PostnatalCareTest extends TestCase
{
    use RefreshDatabase;

    protected User $midwife;

    protected PostnatalSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Midwife', 'slug' => 'midwife']);
        $role->permissions()->sync([
            Permission::updateOrCreate(['slug' => 'manage_deliveries'], ['name' => 'manage_deliveries'])->id,
        ]);

        $this->midwife = User::factory()->create();
        $this->midwife->roles()->attach($role);

        $this->schedule = app(PostnatalSchedule::class);
    }

    protected function deliveredAgo(int $hours): Delivery
    {
        $delivery = Delivery::factory()->delivered()->create([
            'delivered_at' => now()->subHours($hours),
        ]);

        NewbornRecord::factory()->create(['delivery_id' => $delivery->id, 'birth_order' => 1]);

        return $delivery->refresh();
    }

    public function test_nothing_is_due_immediately_after_delivery(): void
    {
        $delivery = $this->deliveredAgo(1);

        $this->assertSame([], $this->schedule->outstandingFor($delivery));
    }

    public function test_the_six_hour_contact_becomes_due_then_overdue(): void
    {
        $due = $this->schedule->statusFor($this->deliveredAgo(8));
        $this->assertSame(PostnatalSchedule::STATUS_DUE, $due[0]['status']);

        // Past the grace window it counts as missed.
        $overdue = $this->schedule->statusFor($this->deliveredAgo(40));
        $this->assertSame(PostnatalSchedule::STATUS_OVERDUE, $overdue[0]['status']);
    }

    public function test_recording_a_contact_marks_it_done_and_moves_to_the_next(): void
    {
        $delivery = $this->deliveredAgo(8);

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", [
            'contact_number' => 1,
            'temperature' => 36.8,
            'lochia' => 'normal',
            'uterus_involution' => 'well_contracted',
        ])->assertSessionHasNoErrors();

        $status = $this->schedule->statusFor($delivery->refresh());

        $this->assertSame(PostnatalSchedule::STATUS_DONE, $status[0]['status']);
        $this->assertSame(2, $this->schedule->nextContact($delivery)['number']);
    }

    public function test_a_contact_cannot_be_recorded_twice(): void
    {
        $delivery = $this->deliveredAgo(8);

        $payload = ['contact_number' => 1, 'temperature' => 36.8];

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", $payload);

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", $payload)
            ->assertSessionHasErrors('contact_number');

        $this->assertSame(1, PostnatalVisit::where('delivery_id', $delivery->id)->count());
    }

    public function test_maternal_danger_signs_are_reported(): void
    {
        $delivery = $this->deliveredAgo(8);

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", [
            'contact_number' => 1,
            'temperature' => 38.6,
            'lochia' => 'offensive',
            'uterus_involution' => 'poorly_contracted',
            'systolic_bp' => 156,
        ])->assertSessionHas('warning');

        $signs = PostnatalVisit::where('delivery_id', $delivery->id)->sole()->dangerSigns();

        $this->assertContains('Offensive lochia — assess for puerperal sepsis', $signs);
        $this->assertContains('Poorly contracted uterus — risk of postpartum haemorrhage', $signs);
        $this->assertContains('Raised blood pressure — postpartum pre-eclampsia can present after delivery', $signs);
    }

    public function test_a_baby_losing_too_much_birth_weight_is_flagged(): void
    {
        $delivery = $this->deliveredAgo(150);
        $baby = $delivery->newborns->first();
        $baby->update(['birth_weight_grams' => 3000]);

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", [
            'contact_number' => 1,
            'newborns' => [
                // 2600 g is a loss of just over 13 per cent.
                $baby->id => ['weight_grams' => 2600, 'feeding' => 'exclusive_breastfeeding'],
            ],
        ]);

        $check = PostnatalNewbornCheck::where('newborn_record_id', $baby->id)->sole();

        $this->assertEqualsWithDelta(0.133, $check->weightLossFraction(), 0.001);
        $this->assertContains('Lost 13.3% of birth weight — assess feeding', $check->alerts());
    }

    public function test_newborn_danger_signs_and_hypothermia_are_reported(): void
    {
        $delivery = $this->deliveredAgo(8);
        $baby = $delivery->newborns->first();

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", [
            'contact_number' => 1,
            'newborns' => [
                $baby->id => [
                    'temperature' => 35.1,
                    'cord_state' => 'infected',
                    'feeding' => 'not_feeding',
                    'danger_signs' => ['fast_breathing'],
                ],
            ],
        ])->assertSessionHas('warning');

        $alerts = PostnatalNewbornCheck::where('newborn_record_id', $baby->id)->sole()->alerts();

        $this->assertContains('Hypothermia (35.1°C) — warm the baby now', $alerts);
        $this->assertContains('Infected cord — treat for omphalitis', $alerts);
        $this->assertContains('Fast breathing (60 or more per minute)', $alerts);
    }

    public function test_a_stillborn_baby_gets_no_postnatal_check(): void
    {
        $delivery = $this->deliveredAgo(8);
        NewbornRecord::factory()->stillbirth()->create([
            'delivery_id' => $delivery->id,
            'birth_order' => 2,
        ]);

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/postnatal", [
            'contact_number' => 1,
        ]);

        // Only the live baby is followed up.
        $this->assertSame(1, PostnatalNewbornCheck::count());
    }

    public function test_postnatal_care_cannot_begin_before_delivery(): void
    {
        $inLabour = Delivery::factory()->create();

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$inLabour->id}/postnatal", ['contact_number' => 1])
            ->assertStatus(422);
    }

    public function test_the_board_lists_mothers_with_contacts_outstanding(): void
    {
        $overdue = $this->deliveredAgo(40);
        $this->deliveredAgo(1);

        $response = $this->actingAs($this->midwife)->get('/postnatal');

        $response->assertOk();
        $response->assertSee($overdue->patient->full_name);
    }
}
