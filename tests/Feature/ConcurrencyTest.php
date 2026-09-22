<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor1;

    protected User $doctor2;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);

        $this->doctor1 = User::factory()->create(['name' => 'Dr. Smith']);
        $this->doctor2 = User::factory()->create(['name' => 'Dr. Jones']);
        $this->doctor1->roles()->attach($role);
        $this->doctor2->roles()->attach($role);

        $this->visit = Visit::factory()->waitingDoctor()->create(['created_by' => $this->doctor1->id]);
    }

    /**
     * Give a user the break-glass permission through a fresh role.
     */
    protected function grantLockOverride(User $user): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'override_consultation_lock'],
            ['name' => 'Override consultation lock']
        );

        $role = Role::create(['name' => 'Senior '.$user->id, 'slug' => 'senior-'.$user->id]);
        $role->permissions()->sync([$permission->id]);
        $user->roles()->attach($role);
    }

    public function test_opening_a_case_claims_the_lock_and_marks_it_in_progress(): void
    {
        $this->actingAs($this->doctor1)->get("/consultations/{$this->visit->id}")->assertOk();

        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();
        $this->assertSame($this->doctor1->id, $consultation->locked_by);
        $this->assertNotNull($consultation->locked_at);
        $this->assertSame(Consultation::STATUS_IN_PROGRESS, $consultation->status);
    }

    public function test_redirects_a_second_doctor_away_from_a_locked_case(): void
    {
        $this->actingAs($this->doctor1)->get("/consultations/{$this->visit->id}");

        $response = $this->actingAs($this->doctor2)->get("/consultations/{$this->visit->id}");

        $response->assertRedirect('/consultations');
        $response->assertSessionHas('error', 'This case is currently being edited by Dr. Smith.');
        $this->assertSame($this->doctor1->id, Consultation::where('visit_id', $this->visit->id)->sole()->locked_by);
    }

    public function test_a_lock_left_untouched_past_the_timeout_can_be_taken_over(): void
    {
        $consultation = Consultation::factory()->staleLockBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
            'status' => Consultation::STATUS_ASSIGNED,
        ]);

        $this->actingAs($this->doctor2)->get("/consultations/{$this->visit->id}")->assertOk();

        $this->assertSame($this->doctor2->id, $consultation->refresh()->locked_by);
    }

    public function test_a_heartbeat_keeps_the_lock_warm(): void
    {
        $consultation = Consultation::factory()->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
            'locked_by' => $this->doctor1->id,
            'locked_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($this->doctor1)->post("/consultations/{$consultation->id}/heartbeat");

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertTrue($consultation->refresh()->locked_at->gt(now()->subSeconds(5)));
    }

    public function test_a_heartbeat_returns_403_once_the_lock_has_moved_on(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor2)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor1)->post("/consultations/{$consultation->id}/heartbeat");

        $response->assertForbidden()->assertJson(['status' => 'locked', 'locked_by' => 'Dr. Jones']);
    }

    public function test_autosave_persists_the_draft_without_signing_it(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'clinical_notes' => 'Typing in progress, not yet finished.',
            'pregnancy_status' => 'unknown',
        ]);

        $response->assertOk()->assertJson(['status' => 'saved']);
        $this->assertDatabaseHas('clinical_notes', [
            'consultation_id' => $consultation->id,
            'content' => 'Typing in progress, not yet finished.',
        ]);

        $consultation->refresh();
        $this->assertSame(Consultation::STATUS_DRAFT, $consultation->status);
        $this->assertNotNull($consultation->autosaved_at);
    }

    public function test_autosave_skips_diagnosis_rows_that_are_still_incomplete(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'clinical_notes' => 'Partial.',
            'diagnoses' => [
                ['code' => '', 'description' => ''],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('diagnoses', 0);
    }

    public function test_autosave_persists_a_completed_prescription_row(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'clinical_notes' => 'Malaria suspected.',
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('prescriptions', ['visit_id' => $this->visit->id, 'status' => 'draft']);
        $this->assertDatabaseHas('prescription_items', ['drug_id' => $drug->id, 'duration_days' => 3]);
    }

    public function test_autosave_leaves_a_saved_plan_alone_when_the_new_row_is_incomplete(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ])->assertOk();

        // The clinician has begun a second row but not finished it.
        $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '', 'frequency' => '', 'duration' => null],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('prescription_items', ['drug_id' => $drug->id, 'duration_days' => 3]);
    }

    public function test_autosave_returns_409_when_another_clinician_holds_the_lock(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor2)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor2->id,
        ]);

        $response = $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'clinical_notes' => 'Should not be saved.',
        ]);

        $response->assertConflict()->assertJson(['status' => 'locked', 'locked_by' => 'Dr. Jones']);
        $this->assertDatabaseCount('clinical_notes', 0);
    }

    public function test_autosave_returns_409_for_a_signed_consultation(): void
    {
        $consultation = Consultation::factory()->signedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor1)->postJson("/consultations/{$consultation->id}/autosave", [
            'clinical_notes' => 'Should not overwrite a signed note.',
        ]);

        $response->assertConflict()->assertJson(['status' => 'signed']);
        $this->assertDatabaseCount('clinical_notes', 0);
    }

    public function test_break_glass_takes_the_lock_and_records_why(): void
    {
        $this->grantLockOverride($this->doctor2);

        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor2)->post("/consultations/{$consultation->id}/break-glass", [
            'reason' => 'Dr. Smith has gone to theatre and the patient is deteriorating.',
        ]);

        $response->assertRedirect(route('consultations.create', $this->visit->id));
        $this->assertSame($this->doctor2->id, $consultation->refresh()->locked_by);

        $log = AuditLog::where('action', 'consultation_lock_overridden')->sole();
        $this->assertSame($this->doctor2->id, $log->user_id);
        $this->assertSame('Dr. Smith', $log->metadata['previous_holder']);
        $this->assertSame('Dr. Smith has gone to theatre and the patient is deteriorating.', $log->metadata['reason']);
    }

    public function test_break_glass_requires_a_reason(): void
    {
        $this->grantLockOverride($this->doctor2);

        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor2)->post("/consultations/{$consultation->id}/break-glass", []);

        $response->assertSessionHasErrors('reason');
        $this->assertSame($this->doctor1->id, $consultation->refresh()->locked_by);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_forbids_break_glass_without_the_override_permission(): void
    {
        $consultation = Consultation::factory()->lockedBy($this->doctor1)->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor1->id,
        ]);

        $response = $this->actingAs($this->doctor2)->post("/consultations/{$consultation->id}/break-glass", [
            'reason' => 'I would like to take over this consultation please.',
        ]);

        $response->assertForbidden();
        $this->assertSame($this->doctor1->id, $consultation->refresh()->locked_by);
    }
}
