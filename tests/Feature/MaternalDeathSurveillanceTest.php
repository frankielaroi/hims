<?php

namespace Tests\Feature;

use App\Models\DeathRecord;
use App\Models\Delivery;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\MaternalDeathSurveillance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ghana requires every maternal death to be notified within 24 hours and
 * reviewed afterwards. WHO counts a death as maternal when a woman dies while
 * pregnant or within 42 days of the pregnancy ending, from a cause related to
 * the pregnancy — the last clause being a judgement no system should make.
 */
class MaternalDeathSurveillanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $midwife;

    protected MaternalDeathSurveillance $surveillance;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Midwife', 'slug' => 'midwife']);
        $role->permissions()->sync([
            Permission::updateOrCreate(
                ['slug' => 'manage_maternal_death_surveillance'],
                ['name' => 'manage_maternal_death_surveillance']
            )->id,
        ]);

        $this->midwife = User::factory()->create();
        $this->midwife->roles()->attach($role);

        $this->surveillance = app(MaternalDeathSurveillance::class);
    }

    protected function deathRecordFor(Patient $patient, int $hoursAgo = 2): DeathRecord
    {
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);

        return DeathRecord::create([
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'died_at' => now()->subHours($hoursAgo),
            'immediate_cause' => 'Postpartum haemorrhage',
            'manner' => 'natural',
            'certified_by' => $this->midwife->id,
            'recorded_by' => $this->midwife->id,
        ]);
    }

    public function test_a_death_soon_after_delivery_is_suggested_as_maternal(): void
    {
        $mother = Patient::factory()->female()->create();
        Delivery::factory()->delivered()->create([
            'patient_id' => $mother->id,
            'delivered_at' => now()->subDays(10),
        ]);

        $this->assertSame(
            'within_42_days',
            $this->surveillance->suggestedTiming($mother, now())
        );
    }

    public function test_a_death_beyond_the_window_is_not_suggested(): void
    {
        $mother = Patient::factory()->female()->create();
        Delivery::factory()->delivered()->create([
            'patient_id' => $mother->id,
            'delivered_at' => now()->subDays(60),
        ]);

        // 42 days is the WHO boundary.
        $this->assertNull($this->surveillance->suggestedTiming($mother, now()));
    }

    public function test_a_death_during_labour_is_suggested_as_such(): void
    {
        $mother = Patient::factory()->female()->create();
        Delivery::factory()->create(['patient_id' => $mother->id]);

        $this->assertSame(
            'during_delivery',
            $this->surveillance->suggestedTiming($mother, now())
        );
    }

    public function test_nothing_is_classified_automatically(): void
    {
        $mother = Patient::factory()->female()->create();
        Delivery::factory()->delivered()->create([
            'patient_id' => $mother->id,
            'delivered_at' => now()->subDays(5),
        ]);

        $record = $this->deathRecordFor($mother);

        // Whether the death was related to the pregnancy is a clinical
        // judgement, so the flag stays false until a clinician sets it.
        $this->assertFalse($record->is_maternal_death);
    }

    public function test_a_clinician_classifies_the_death_and_the_clock_starts(): void
    {
        $mother = Patient::factory()->female()->create();
        $record = $this->deathRecordFor($mother);

        $this->actingAs($this->midwife)->post("/maternal-deaths/{$record->id}/classify", [
            'is_maternal_death' => 1,
            'maternal_death_timing' => 'within_42_days',
        ])->assertSessionHas('warning');

        $record->refresh();

        $this->assertTrue($record->is_maternal_death);
        $this->assertFalse($record->notificationIsOverdue());
        $this->assertContains(
            'Maternal death review outstanding',
            $record->outstandingActions()
        );
    }

    public function test_notification_past_the_window_is_reported_as_late(): void
    {
        $mother = Patient::factory()->female()->create();
        $record = $this->deathRecordFor($mother, hoursAgo: 40);
        $record->update(['is_maternal_death' => true, 'maternal_death_timing' => 'within_42_days']);

        $this->assertTrue($record->notificationIsOverdue());

        $this->actingAs($this->midwife)->post("/maternal-deaths/{$record->id}/notify")
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('audit_logs', ['action' => 'maternal_death_notified']);
        $this->assertTrue($record->refresh()->isNotified());
    }

    public function test_a_review_cannot_be_recorded_before_notification(): void
    {
        $mother = Patient::factory()->female()->create();
        $record = $this->deathRecordFor($mother);
        $record->update(['is_maternal_death' => true]);

        $this->actingAs($this->midwife)->post("/maternal-deaths/{$record->id}/review", [
            'review_findings' => 'Delay in recognising the haemorrhage and in calling for blood.',
        ])->assertStatus(422);
    }

    public function test_the_review_closes_the_loop(): void
    {
        $mother = Patient::factory()->female()->create();
        $record = $this->deathRecordFor($mother);
        $record->update([
            'is_maternal_death' => true,
            'notified_at' => now(),
            'notified_by' => $this->midwife->id,
        ]);

        $this->actingAs($this->midwife)->post("/maternal-deaths/{$record->id}/review", [
            'review_findings' => 'Delay in recognising the haemorrhage and in calling for blood.',
        ])->assertSessionHasNoErrors();

        $record->refresh();

        $this->assertNotNull($record->reviewed_at);
        $this->assertSame([], $record->outstandingActions());
    }

    public function test_the_board_shows_deaths_needing_classification(): void
    {
        $mother = Patient::factory()->female()->create();
        Delivery::factory()->delivered()->create([
            'patient_id' => $mother->id,
            'delivered_at' => now()->subDays(3),
        ]);
        $this->deathRecordFor($mother);

        $this->actingAs($this->midwife)->get('/maternal-deaths')
            ->assertOk()
            ->assertSee('Needs classifying')
            ->assertSee($mother->full_name);
    }
}
