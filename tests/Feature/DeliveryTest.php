<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\NewbornRecord;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $midwife;

    protected User $receptionist;

    protected Patient $mother;

    protected Visit $visit;

    /**
     * @param  list<string>  $slugs
     */
    protected function userWith(string $roleSlug, array $slugs): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->sync(
            collect($slugs)
                ->map(fn (string $slug) => Permission::updateOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->midwife = $this->userWith('midwife', ['manage_deliveries', 'view_patients', 'register_patients']);
        $this->receptionist = $this->userWith('receptionist', ['view_patients']);

        $this->mother = Patient::factory()->female()->create(['payment_method' => 'nhis']);
        $this->visit = Visit::factory()->create([
            'patient_id' => $this->mother->id,
            'created_by' => $this->midwife->id,
        ]);
    }

    protected function openLabour(): Delivery
    {
        $this->actingAs($this->midwife)->post("/visits/{$this->visit->id}/deliveries", [
            'gestation_weeks' => 39,
        ]);

        return Delivery::where('visit_id', $this->visit->id)->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function outcomePayload(array $overrides = []): array
    {
        return array_merge([
            'delivered_at' => now()->format('Y-m-d H:i:s'),
            'mode' => 'spontaneous_vertex',
            'place' => 'facility',
            'uterotonic_given' => 1,
            'third_stage_management' => 'active',
            'placenta_state' => 'complete',
            'blood_loss_ml' => 250,
            'newborns' => [
                [
                    'sex' => 'female',
                    'outcome' => NewbornRecord::OUTCOME_LIVE_BIRTH,
                    'birth_weight_grams' => 3100,
                    'apgar_one_minute' => 8,
                    'apgar_five_minutes' => 9,
                    'breastfed_within_hour' => 1,
                    'vitamin_k_given' => 1,
                    'eye_prophylaxis_given' => 1,
                ],
            ],
        ], $overrides);
    }

    public function test_a_labour_record_can_be_opened_and_is_not_duplicated(): void
    {
        $delivery = $this->openLabour();

        $this->assertSame(Delivery::STATUS_IN_LABOUR, $delivery->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'labour_record_opened']);

        // Opening a second record for the same visit must return the first.
        $this->actingAs($this->midwife)->post("/visits/{$this->visit->id}/deliveries", [])
            ->assertRedirect(route('deliveries.show', $delivery));

        $this->assertSame(1, Delivery::where('visit_id', $this->visit->id)->count());
    }

    public function test_recording_a_delivery_stores_the_mother_and_every_baby(): void
    {
        $delivery = $this->openLabour();

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload())
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();

        $this->assertTrue($delivery->isDelivered());
        $this->assertSame($this->midwife->id, $delivery->delivered_by);
        $this->assertCount(1, $delivery->newborns);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery_recorded']);
    }

    public function test_twins_are_numbered_in_birth_order(): void
    {
        $delivery = $this->openLabour();

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload([
            'newborns' => [
                ['sex' => 'female', 'outcome' => NewbornRecord::OUTCOME_LIVE_BIRTH, 'birth_weight_grams' => 2400],
                ['sex' => 'male', 'outcome' => NewbornRecord::OUTCOME_FRESH_STILLBIRTH, 'birth_weight_grams' => 2200],
            ],
        ]))->assertSessionHasNoErrors();

        $newborns = $delivery->refresh()->newborns;

        $this->assertSame([1, 2], $newborns->pluck('birth_order')->all());
        // Twins do not always share an outcome.
        $this->assertTrue($newborns[0]->isLiveBirth());
        $this->assertTrue($newborns[1]->isStillbirth());
    }

    public function test_postpartum_haemorrhage_is_flagged_against_the_right_threshold(): void
    {
        $vaginal = Delivery::factory()->delivered()->create(['blood_loss_ml' => 600, 'mode' => 'spontaneous_vertex']);
        $caesarean = Delivery::factory()->delivered()->create(['blood_loss_ml' => 600, 'mode' => 'caesarean']);

        // 500 ml after a vaginal birth, 1000 ml after a caesarean.
        $this->assertTrue($vaginal->hasPostpartumHaemorrhage());
        $this->assertFalse($caesarean->hasPostpartumHaemorrhage());
    }

    public function test_a_delivery_without_a_uterotonic_is_flagged(): void
    {
        $delivery = $this->openLabour();

        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload([
            'uterotonic_given' => 0,
        ]));

        $this->assertContains(
            'No uterotonic recorded — active management of the third stage prevents most postpartum haemorrhage',
            $delivery->refresh()->alerts()
        );
    }

    public function test_a_low_birth_weight_baby_is_flagged_for_kangaroo_care(): void
    {
        $baby = NewbornRecord::factory()->lowBirthWeight()->create();

        $this->assertTrue($baby->isLowBirthWeight());
        $this->assertContains('Low birth weight (2100 g) — start kangaroo mother care', $baby->alerts());
    }

    public function test_a_stillbirth_reports_only_the_stillbirth(): void
    {
        $baby = NewbornRecord::factory()->stillbirth()->create();

        // Vitamin K and feeding are meaningless here; the record should not nag.
        $this->assertSame(
            ['Stillbirth (fresh stillbirth) — record the cause and notify'],
            $baby->alerts()
        );
    }

    public function test_registering_a_newborn_links_mother_to_child(): void
    {
        $delivery = $this->openLabour();
        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload());

        $baby = $delivery->refresh()->newborns->first();

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$delivery->id}/newborns/{$baby->id}/register", ['full_name' => 'Baby Mensah'])
            ->assertSessionHasNoErrors();

        $baby->refresh();

        $this->assertNotNull($baby->patient_id);
        $this->assertSame('Baby Mensah', $baby->patient->full_name);
        // The date of birth drives the whole EPI schedule, so it must come from
        // the delivery rather than being typed.
        $this->assertSame(
            $delivery->refresh()->delivered_at->toDateString(),
            $baby->patient->date_of_birth->toDateString()
        );
        $this->assertSame($this->mother->full_name, $baby->patient->next_of_kin_name);
    }

    public function test_a_stillbirth_cannot_be_registered_as_a_patient(): void
    {
        $delivery = $this->openLabour();
        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload([
            'newborns' => [['sex' => 'male', 'outcome' => NewbornRecord::OUTCOME_MACERATED_STILLBIRTH]],
        ]));

        $baby = $delivery->refresh()->newborns->first();

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$delivery->id}/newborns/{$baby->id}/register", ['full_name' => 'Baby'])
            ->assertStatus(422);
    }

    public function test_a_delivery_cannot_be_recorded_twice(): void
    {
        $delivery = $this->openLabour();
        $this->actingAs($this->midwife)->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload());

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$delivery->id}/outcome", $this->outcomePayload())
            ->assertStatus(422);

        $this->assertCount(1, $delivery->refresh()->newborns);
    }

    public function test_the_labour_ward_is_closed_without_the_permission(): void
    {
        $this->actingAs($this->receptionist)->get('/deliveries')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->receptionist->id,
            'action' => 'authorization_denied',
        ]);
    }
}
