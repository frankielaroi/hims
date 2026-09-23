<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PartographMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The classic WHO partograph Ghana teaches: in the active phase the cervix is
 * expected to open at about a centimetre an hour, the alert line tracks that,
 * and the action line sits four hours to its right.
 */
class PartographTest extends TestCase
{
    use RefreshDatabase;

    protected PartographMonitor $monitor;

    protected Delivery $delivery;

    protected User $midwife;

    protected function setUp(): void
    {
        parent::setUp();

        $this->monitor = app(PartographMonitor::class);
        $this->midwife = User::factory()->create();
        $this->delivery = Delivery::factory()->create();
    }

    protected function observe(int $dilatation, Carbon $at): void
    {
        $this->delivery->partographEntries()->create([
            'recorded_at' => $at,
            'cervical_dilatation_cm' => $dilatation,
            'recorded_by' => $this->midwife->id,
        ]);
    }

    public function test_the_latent_phase_is_not_measured_against_the_alert_line(): void
    {
        $this->observe(2, now()->subHour());
        $this->observe(3, now());

        $progress = $this->monitor->progressFor($this->delivery);

        $this->assertSame(PartographMonitor::STATUS_LATENT, $progress['status']);
        $this->assertNull($progress['hours_behind']);
    }

    public function test_a_centimetre_an_hour_is_on_track(): void
    {
        $start = now()->subHours(3);

        $this->observe(4, $start);
        $this->observe(7, $start->copy()->addHours(3));

        $progress = $this->monitor->progressFor($this->delivery);

        $this->assertSame(PartographMonitor::STATUS_ON_TRACK, $progress['status']);
    }

    public function test_falling_behind_crosses_the_alert_line(): void
    {
        $start = now()->subHours(4);

        // Four hours in the active phase but only two centimetres of progress.
        $this->observe(4, $start);
        $this->observe(6, $start->copy()->addHours(4));

        $progress = $this->monitor->progressFor($this->delivery);

        $this->assertSame(PartographMonitor::STATUS_ALERT, $progress['status']);
        $this->assertEqualsWithDelta(2.0, $progress['hours_behind'], 0.1);
        $this->assertStringContainsString('Reassess', $progress['message']);
    }

    public function test_four_hours_behind_crosses_the_action_line(): void
    {
        $start = now()->subHours(6);

        $this->observe(4, $start);
        $this->observe(6, $start->copy()->addHours(6));

        $progress = $this->monitor->progressFor($this->delivery);

        $this->assertSame(PartographMonitor::STATUS_ACTION, $progress['status']);
        $this->assertEqualsWithDelta(4.0, $progress['hours_behind'], 0.1);
        $this->assertStringContainsString('Obstetric review now', $progress['message']);
    }

    public function test_expected_progress_never_exceeds_full_dilatation(): void
    {
        $start = now()->subHours(12);

        // Twelve hours from 4 cm would imply 16 cm without the cap.
        $this->observe(4, $start);
        $this->observe(10, $start->copy()->addHours(12));

        $progress = $this->monitor->progressFor($this->delivery);

        $this->assertSame(PartographMonitor::FULL_DILATATION_CM, (int) $progress['expected_cm']);
        $this->assertSame(PartographMonitor::STATUS_ON_TRACK, $progress['status']);
    }

    public function test_warnings_name_the_reading_that_raised_them(): void
    {
        $this->delivery->partographEntries()->create([
            'recorded_at' => now(),
            'cervical_dilatation_cm' => 5,
            'fetal_heart_rate' => 95,
            'liquor' => 'meconium',
            'moulding' => 'plus_plus_plus',
            'temperature' => 38.4,
            'recorded_by' => $this->midwife->id,
        ]);

        $warnings = $this->monitor->warningsFor($this->delivery);

        $this->assertCount(4, $warnings);
        $this->assertStringContainsString('Fetal heart rate outside 110-160 bpm (95)', implode(' ', $warnings));
        $this->assertStringContainsString('Meconium-stained liquor', implode(' ', $warnings));
        $this->assertStringContainsString('Severe moulding', implode(' ', $warnings));
        $this->assertStringContainsString('Maternal fever', implode(' ', $warnings));
    }

    public function test_crossing_the_action_line_is_written_to_the_audit_log(): void
    {
        $role = Role::create(['name' => 'Midwife', 'slug' => 'midwife']);
        $permission = Permission::updateOrCreate(['slug' => 'manage_deliveries'], ['name' => 'manage_deliveries']);
        $role->permissions()->sync([$permission->id]);
        $this->midwife->roles()->attach($role);

        $start = now()->subHours(6);
        $this->observe(4, $start);

        $this->actingAs($this->midwife)->post("/deliveries/{$this->delivery->id}/observations", [
            'recorded_at' => $start->copy()->addHours(6)->format('Y-m-d H:i:s'),
            'cervical_dilatation_cm' => 6,
        ])->assertSessionHas('warning');

        $this->assertDatabaseHas('audit_logs', ['action' => 'partograph_action_line_crossed']);
    }

    public function test_observations_cannot_be_added_after_delivery(): void
    {
        $delivered = Delivery::factory()->delivered()->create();

        $role = Role::create(['name' => 'Midwife 2', 'slug' => 'midwife2']);
        $permission = Permission::updateOrCreate(['slug' => 'manage_deliveries'], ['name' => 'manage_deliveries']);
        $role->permissions()->sync([$permission->id]);
        $this->midwife->roles()->attach($role);

        $this->actingAs($this->midwife)
            ->post("/deliveries/{$delivered->id}/observations", ['cervical_dilatation_cm' => 8])
            ->assertStatus(422);
    }
}
