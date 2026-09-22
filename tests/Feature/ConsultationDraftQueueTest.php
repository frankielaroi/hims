<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Icd10Code;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationDraftQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach(Role::create(['name' => 'Doctor', 'slug' => 'doctor']));

        $this->visit = Visit::factory()->waitingDoctor()->create(['created_by' => $this->doctor->id]);

        Icd10Code::create(['code' => 'A00', 'name' => 'Cholera']);
    }

    public function test_a_saved_draft_stays_on_the_queue_marked_as_a_draft(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Partial assessment.',
        ]);

        $response = $this->actingAs($this->doctor)->get('/consultations');

        $response->assertOk();
        $response->assertSee($this->visit->patient->full_name);
        $response->assertSee('Draft');
        $response->assertSee('Resume');
    }

    public function test_opening_a_case_does_not_remove_it_from_the_queue(): void
    {
        $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}")->assertOk();

        // Merely opening a case used to move the visit to in_consultation, which the
        // queue filtered out, so the case disappeared with no way back to it.
        $response = $this->actingAs($this->doctor)->get('/consultations');

        $response->assertOk();
        $response->assertSee($this->visit->patient->full_name);
        $response->assertSee('In Progress');
    }

    public function test_refreshing_an_open_case_keeps_it_on_the_queue(): void
    {
        $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");
        $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}")->assertOk();

        $this->actingAs($this->doctor)->get('/consultations')
            ->assertOk()
            ->assertSee($this->visit->patient->full_name);
    }

    public function test_a_signed_consultation_drops_off_the_queue(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'clinical_notes' => 'Complete assessment.',
            'diagnoses' => [
                ['code' => 'A00', 'description' => 'Cholera', 'type' => 'primary', 'certainty' => 'confirmed'],
            ],
            'outcome' => 'discharge',
        ]);

        $this->actingAs($this->doctor)->get('/consultations')
            ->assertOk()
            ->assertDontSee($this->visit->patient->full_name);
    }

    public function test_a_draft_needs_no_clinical_note_diagnosis_or_outcome(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('consultations', [
            'visit_id' => $this->visit->id,
            'status' => Consultation::STATUS_DRAFT,
        ]);
    }

    public function test_a_draft_accepts_a_diagnosis_row_with_no_clinical_detail_typed_yet(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'diagnoses' => [
                ['code' => 'A00', 'description' => null],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diagnoses', ['icd10_code' => 'A00', 'description' => '']);
    }

    public function test_a_draft_ignores_a_diagnosis_row_with_no_code_chosen(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Thinking about it.',
            'diagnoses' => [
                ['code' => null, 'description' => 'Something viral'],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        // A diagnosis with no code cannot be stored, so the row is dropped rather
        // than blocking the save.
        $this->assertDatabaseCount('diagnoses', 0);
    }

    public function test_a_draft_accepts_a_half_filled_prescription_row(): void
    {
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => null, 'frequency' => null, 'duration' => null],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('prescription_items', [
            'drug_id' => $drug->id,
            'dose' => '',
            'duration_days' => 0,
        ]);
        $this->assertDatabaseHas('prescriptions', ['visit_id' => $this->visit->id, 'status' => 'draft']);
    }

    public function test_a_draft_ignores_a_prescription_row_with_no_drug_chosen(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'prescription_items' => [
                ['drug_id' => null, 'dose' => '500mg', 'frequency' => 'TDS', 'duration' => 5],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_signing_still_demands_a_complete_prescription_row(): void
    {
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'clinical_notes' => 'Complete assessment.',
            'diagnoses' => [
                ['code' => 'A00', 'description' => 'Cholera', 'type' => 'primary', 'certainty' => 'confirmed'],
            ],
            'outcome' => 'discharge',
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => null, 'frequency' => null, 'duration' => null],
            ],
        ]);

        $response->assertSessionHasErrors([
            'prescription_items.0.dose',
            'prescription_items.0.frequency',
            'prescription_items.0.duration',
        ]);
    }

    public function test_signing_still_demands_a_note_diagnosis_and_outcome(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", []);

        $response->assertSessionHasErrors(['clinical_notes', 'diagnoses', 'outcome']);
    }

    public function test_a_draft_can_be_reopened_and_shows_what_was_saved(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Query enteric fever, awaiting bloods.',
        ]);

        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Query enteric fever, awaiting bloods.');
    }

    public function test_the_draft_button_bypasses_browser_validation(): void
    {
        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        // Without formnovalidate the browser blocks the draft save on the fields
        // that are only required at sign-off.
        $response->assertSee('name="save_as_draft" value="1" formnovalidate', false);
    }
}
