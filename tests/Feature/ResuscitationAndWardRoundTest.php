<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\ResuscitationRecord;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoundNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResuscitationAndWardRoundTest extends TestCase
{
    use RefreshDatabase;

    protected User $clinician;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $role->permissions()->sync(
            collect(['manage_admissions', 'view_clinical_history', 'register_patients'])
                ->map(fn (string $slug) => Permission::updateOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );

        $this->clinician = User::factory()->create();
        $this->clinician->roles()->attach($role);

        $this->visit = Visit::factory()->create(['patient_id' => Patient::factory()->create()->id]);
    }

    protected function admission(): Admission
    {
        $ward = Ward::create(['name' => 'Male Medical', 'category' => 'General']);
        $bed = Bed::create(['ward_id' => $ward->id, 'bed_number' => '1', 'status' => 'occupied']);

        return Admission::create([
            'visit_id' => $this->visit->id,
            'bed_id' => $bed->id,
            'admitted_by' => $this->clinician->id,
            'admission_date' => now()->subDays(3),
            'status' => 'admitted',
        ]);
    }

    // ---- Resuscitation -------------------------------------------------

    public function test_a_resuscitation_opens_and_closes_with_its_outcome(): void
    {
        $this->actingAs($this->clinician)->post("/visits/{$this->visit->id}/resuscitation", [
            'arrest_type' => 'cardiorespiratory',
            'was_witnessed' => 1,
            'initial_rhythm' => 'vf',
        ])->assertSessionHasNoErrors();

        $record = ResuscitationRecord::where('visit_id', $this->visit->id)->sole();

        $this->assertTrue($record->isOngoing());
        $this->assertTrue($record->isShockableRhythm());

        $this->actingAs($this->clinician)->post("/resuscitation/{$record->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'interventions' => ['chest_compressions', 'defibrillation', 'adrenaline'],
            'shocks_delivered' => 3,
            'adrenaline_doses' => 4,
            'outcome' => ResuscitationRecord::OUTCOME_ROSC,
            'team_lead_id' => $this->clinician->id,
        ])->assertSessionHasNoErrors();

        $record->refresh();

        $this->assertFalse($record->isOngoing());
        $this->assertSame([], $record->alerts());
        $this->assertDatabaseHas('audit_logs', ['action' => 'resuscitation_completed']);
    }

    public function test_a_shockable_rhythm_with_no_defibrillation_is_flagged(): void
    {
        $this->actingAs($this->clinician)->post("/visits/{$this->visit->id}/resuscitation", [
            'arrest_type' => 'cardiac',
            'initial_rhythm' => 'pulseless_vt',
        ]);

        $record = ResuscitationRecord::where('visit_id', $this->visit->id)->sole();

        $this->actingAs($this->clinician)->post("/resuscitation/{$record->id}/complete", [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'interventions' => ['chest_compressions', 'adrenaline'],
            'outcome' => ResuscitationRecord::OUTCOME_DIED,
            'team_lead_id' => $this->clinician->id,
        ])->assertSessionHas('warning');

        // This is the single thing a review looks for hardest.
        $this->assertContains(
            'Shockable rhythm recorded but no defibrillation — explain in the notes',
            $record->refresh()->alerts()
        );
    }

    public function test_an_open_resuscitation_reports_only_that_it_is_open(): void
    {
        $this->actingAs($this->clinician)->post("/visits/{$this->visit->id}/resuscitation", [
            'arrest_type' => 'cardiac',
        ]);

        $record = ResuscitationRecord::where('visit_id', $this->visit->id)->sole();

        $this->assertSame(
            ['Resuscitation still open — record the outcome when it ends'],
            $record->alerts()
        );
    }

    public function test_a_resuscitation_cannot_be_closed_twice(): void
    {
        $this->actingAs($this->clinician)->post("/visits/{$this->visit->id}/resuscitation", ['arrest_type' => 'cardiac']);
        $record = ResuscitationRecord::where('visit_id', $this->visit->id)->sole();

        $payload = [
            'ended_at' => now()->format('Y-m-d H:i:s'),
            'outcome' => ResuscitationRecord::OUTCOME_DIED,
        ];

        $this->actingAs($this->clinician)->post("/resuscitation/{$record->id}/complete", $payload);

        $this->actingAs($this->clinician)->post("/resuscitation/{$record->id}/complete", $payload)
            ->assertStatus(422);
    }

    // ---- Ward rounds ---------------------------------------------------

    public function test_a_ward_round_note_is_recorded_against_the_admission(): void
    {
        $admission = $this->admission();

        $this->actingAs($this->clinician)->post("/admissions/{$admission->id}/rounds", [
            'subjective' => 'Feeling better, eating.',
            'objective' => 'Afebrile, chest clear.',
            'assessment' => 'Community acquired pneumonia, improving.',
            'plan' => 'Continue oral amoxicillin, review tomorrow.',
            'seen_by_senior' => 1,
        ])->assertSessionHasNoErrors();

        $note = WardRoundNote::where('admission_id', $admission->id)->sole();

        $this->assertTrue($note->seen_by_senior);
        $this->assertSame($this->clinician->id, $note->recorded_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ward_round_note_recorded']);
    }

    public function test_an_assessment_and_plan_are_required(): void
    {
        $admission = $this->admission();

        // A note that records only how the patient feels is not a ward round.
        $this->actingAs($this->clinician)->post("/admissions/{$admission->id}/rounds", [
            'subjective' => 'Comfortable.',
        ])->assertSessionHasErrors(['assessment', 'plan']);
    }

    public function test_an_admission_with_no_note_for_a_day_is_overdue(): void
    {
        $admission = $this->admission();

        // Admitted three days ago and never seen.
        $this->assertTrue($admission->isOverdueForRound());
        $this->assertNull($admission->hoursSinceLastRound());
    }

    public function test_a_patient_admitted_an_hour_ago_is_not_neglected(): void
    {
        $admission = $this->admission();
        $admission->update(['admission_date' => now()->subHour()]);

        $this->assertFalse($admission->fresh()->isOverdueForRound());
    }

    public function test_a_recent_note_clears_the_overdue_flag(): void
    {
        $admission = $this->admission();

        $this->actingAs($this->clinician)->post("/admissions/{$admission->id}/rounds", [
            'assessment' => 'Stable.',
            'plan' => 'Continue.',
        ]);

        $this->assertFalse($admission->fresh()->isOverdueForRound());
    }

    public function test_a_discharged_patient_is_never_chased_for_rounds(): void
    {
        $admission = $this->admission();
        $admission->update(['status' => 'discharged', 'discharge_date' => now()]);

        $this->assertFalse($admission->fresh()->isOverdueForRound());
    }

    public function test_notes_cannot_be_added_after_discharge(): void
    {
        $admission = $this->admission();
        $admission->update(['status' => 'discharged', 'discharge_date' => now()]);

        $this->actingAs($this->clinician)->post("/admissions/{$admission->id}/rounds", [
            'assessment' => 'Stable.',
            'plan' => 'Continue.',
        ])->assertStatus(422);
    }
}
