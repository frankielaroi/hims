<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\DrugInteraction;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientChronicMedication;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\PrescriptionSafetyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach(Role::create(['name' => 'Doctor', 'slug' => 'doctor', 'description' => 'Clinical staff']));

        $this->visit = Visit::factory()->waitingDoctor()->create(['created_by' => $this->doctor->id]);

        Icd10Code::create(['code' => 'A00', 'name' => 'Cholera']);
        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);
    }

    /**
     * A complete, signable payload. Individual tests override the parts they exercise.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function signablePayload(array $overrides = []): array
    {
        return array_merge([
            'clinical_notes' => 'Fever for three days, no rigors. Chest clear.',
            'diagnoses' => [
                [
                    'code' => 'A00',
                    'description' => 'Suspected cholera',
                    'type' => 'primary',
                    'certainty' => 'probable',
                ],
            ],
            'outcome' => 'discharge',
            'pregnancy_status' => 'unknown',
        ], $overrides);
    }

    public function test_renders_the_workspace_with_the_patients_allergies_and_chronic_medications(): void
    {
        PatientAllergy::factory()->for($this->visit->patient)->to('Penicillin')->severe()->create([
            'reaction' => 'Angioedema',
        ]);
        PatientChronicMedication::factory()->for($this->visit->patient)->create(['drug_name' => 'Amlodipine']);
        Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Penicillin');
        $response->assertSee('Angioedema');
        $response->assertSee('Amlodipine');
    }

    public function test_gives_the_clinical_note_the_full_width_when_there_is_no_obstetric_card(): void
    {
        $this->visit->patient->update(['sex' => 'male']);

        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertDontSee('Obstetric History');
        // Without the obstetric card beside it, the note must not sit in a two-column
        // grid, or it renders at half width with dead space next to it.
        $response->assertDontSee('grid grid-cols-1 gap-6 xl:grid-cols-2', false);
        $response->assertSee('grid grid-cols-1 gap-6', false);
    }

    public function test_pairs_the_clinical_note_with_the_obstetric_card_for_a_female_patient(): void
    {
        $this->visit->patient->update(['sex' => 'female']);

        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Obstetric History');
        $response->assertSee('grid grid-cols-1 gap-6 xl:grid-cols-2', false);
    }

    public function test_renders_a_signed_consultation_as_read_only_with_its_amendment_history(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $this->actingAs($this->doctor)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Chest x-ray reported after sign-off and changes the impression.',
            'clinical_notes' => 'Right basal consolidation on chest x-ray.',
        ]);

        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Record an Amendment');
        $response->assertSee('Amendment History');
        $response->assertSee('Chest x-ray reported after sign-off and changes the impression.');
        // A signed note offers no in-place editing controls.
        $response->assertDontSee('Sign Consultation');
        $response->assertDontSee('Save Draft');
    }

    public function test_viewing_a_signed_consultation_does_not_reopen_the_closed_visit(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}")->assertOk();

        $this->assertSame('completed', $this->visit->refresh()->status);
        // Nor does it take an editing lock on a note that can no longer be edited.
        $this->assertNull($consultation->refresh()->locked_by);
        $this->assertSame(Consultation::STATUS_FINALIZED, $consultation->status);
    }

    public function test_saves_a_partial_consultation_as_a_draft(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Partial notes',
            'pregnancy_status' => 'none',
        ]);

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('consultations', [
            'visit_id' => $this->visit->id,
            'status' => Consultation::STATUS_DRAFT,
            'pregnancy_status' => 'none',
        ]);
        $this->assertDatabaseHas('clinical_notes', ['content' => 'Partial notes']);
        $this->assertSame('in_consultation', $this->visit->refresh()->status);
    }

    public function test_a_saved_draft_releases_the_editing_lock(): void
    {
        $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Partial notes',
        ]);

        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();
        $this->assertNull($consultation->locked_by);
        $this->assertNull($consultation->locked_at);
    }

    public function test_rejects_signing_without_notes_or_a_diagnosis(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'clinical_notes' => '',
            'outcome' => 'discharge',
        ]);

        $response->assertSessionHasErrors(['clinical_notes', 'diagnoses']);
        $this->assertDatabaseMissing('consultations', ['status' => Consultation::STATUS_SIGNED]);
    }

    public function test_rejects_signing_without_a_primary_diagnosis(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'diagnoses' => [
                ['code' => 'A00', 'description' => 'Possible cholera', 'type' => 'differential', 'certainty' => 'possible'],
            ],
        ]));

        $response->assertSessionHasErrors([
            'diagnoses' => 'Exactly one primary diagnosis is required to sign this consultation.',
        ]);
        $this->assertDatabaseMissing('consultations', ['status' => Consultation::STATUS_SIGNED]);
    }

    public function test_rejects_signing_with_more_than_one_primary_diagnosis(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'diagnoses' => [
                ['code' => 'A00', 'description' => 'Cholera', 'type' => 'primary', 'certainty' => 'confirmed'],
                ['code' => 'B54', 'description' => 'Malaria', 'type' => 'primary', 'certainty' => 'confirmed'],
            ],
        ]));

        $response->assertSessionHasErrors([
            'diagnoses' => 'Only one diagnosis may be marked as the primary diagnosis.',
        ]);
    }

    public function test_signing_moves_the_consultation_through_signed_to_finalized(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Initial impression.',
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());

        $response->assertRedirect('/consultations');

        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();
        $this->assertSame(Consultation::STATUS_FINALIZED, $consultation->status);
        $this->assertSame($this->doctor->id, $consultation->signed_by);
        $this->assertNotNull($consultation->signed_at);

        // The draft -> signed -> finalized path is recorded, not just the end state.
        $logged = AuditLog::where('action', 'consultation_status_changed')
            ->where('metadata->consultation_id', $consultation->id)
            ->get()
            ->map(fn (AuditLog $log) => $log->metadata['from'].'->'.$log->metadata['to'])
            ->all();

        $this->assertContains('draft->ready_for_sign_off', $logged);
        $this->assertContains('ready_for_sign_off->signed', $logged);
        $this->assertContains('signed->finalized', $logged);
    }

    public function test_signing_records_the_diagnoses_outcome_and_closes_the_visit(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'follow_up_date' => now()->addWeek()->format('Y-m-d'),
        ]));

        $this->assertDatabaseHas('diagnoses', [
            'icd10_code' => 'A00',
            'type' => Diagnosis::TYPE_PRIMARY,
            'certainty' => 'probable',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('visit_outcomes', [
            'visit_id' => $this->visit->id,
            'outcome' => 'discharge',
        ]);
        $this->assertSame('completed', $this->visit->refresh()->status);
        $this->assertNotNull($this->visit->closed_at);
    }

    public function test_defaults_a_diagnosis_type_from_the_legacy_primary_flag(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'diagnoses' => [
                ['code' => 'B54', 'description' => 'Uncomplicated malaria', 'is_primary' => true],
            ],
        ]));

        $this->assertDatabaseHas('diagnoses', [
            'icd10_code' => 'B54',
            'type' => Diagnosis::TYPE_PRIMARY,
            'certainty' => 'confirmed',
            'is_primary' => true,
        ]);
    }

    public function test_routes_a_signed_consultation_with_a_prescription_to_the_pharmacy(): void
    {
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ]));

        $this->assertSame('waiting_pharmacy', $this->visit->refresh()->status);
        $this->assertDatabaseHas('prescriptions', ['visit_id' => $this->visit->id, 'status' => 'active']);
        $this->assertDatabaseHas('prescription_items', ['drug_id' => $drug->id, 'quantity_to_dispense' => 24]);
    }

    public function test_the_disposition_is_marked_required_on_the_form(): void
    {
        // `outcome` is demanded at sign-off, so the form must stop the clinician
        // before a round-trip that would discard the rest of what they typed.
        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('name="outcome" value="discharge" required', false);
        $response->assertSee('Required to sign');
        // A hidden control cannot be focused, and Chrome then blocks submission silently.
        $response->assertDontSee('name="outcome" value="discharge" required class="peer sr-only"', false);
    }

    public function test_an_omitted_quantity_is_derived_from_the_frequency_and_duration(): void
    {
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        // The prescribing form leaves quantity blank and labels it "Auto".
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '500mg', 'frequency' => 'TDS', 'duration' => 7],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prescription_items', [
            'drug_id' => $drug->id,
            'quantity_to_dispense' => 21,
        ]);
    }

    public function test_a_quantity_the_clinician_typed_is_not_overwritten(): void
    {
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '500mg', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 30],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('prescription_items', [
            'drug_id' => $drug->id,
            'quantity_to_dispense' => 30,
        ]);
    }

    public function test_an_unrecognised_frequency_still_asks_for_a_quantity(): void
    {
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        // "PRN" has no doses per day, so there is nothing safe to infer.
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '500mg', 'frequency' => 'PRN', 'duration' => 7],
            ],
        ]))->assertSessionHasErrors('prescription_items.0.quantity');

        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_re_saving_a_draft_replaces_the_prescription_instead_of_duplicating_it(): void
    {
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();
        $payload = [
            'save_as_draft' => '1',
            'clinical_notes' => 'Working diagnosis.',
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ];

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $payload);
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $payload);

        $this->assertDatabaseCount('prescriptions', 1);
        $this->assertDatabaseCount('prescription_items', 1);
    }

    public function test_clearing_the_plan_removes_the_draft_prescription(): void
    {
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create();

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Working diagnosis.',
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ]);

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", [
            'save_as_draft' => '1',
            'clinical_notes' => 'Plan withdrawn.',
        ]);

        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_blocks_a_severe_allergy_conflict_without_a_justification(): void
    {
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();
        PatientAllergy::factory()->for($this->visit->patient)->to('Penicillin')->severe()->create();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '1 tablet', 'frequency' => 'TDS', 'duration' => 5, 'quantity' => 15],
            ],
        ]));

        $response->assertSessionHasErrors("allergy_justifications.{$drug->id}");
        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertDatabaseMissing('consultations', ['status' => Consultation::STATUS_SIGNED]);
    }

    public function test_allows_a_severe_allergy_conflict_when_justified_and_records_the_override(): void
    {
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();
        PatientAllergy::factory()->for($this->visit->patient)->to('Penicillin')->severe()->create();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '1 tablet', 'frequency' => 'TDS', 'duration' => 5, 'quantity' => 15],
            ],
            'allergy_justifications' => [
                $drug->id => 'Reaction was a mild rash in 2015; no alternative cover available on formulary.',
            ],
        ]));

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('allergy_conflict_checks', [
            'allergy_found' => 'Penicillin',
            'drug_prescribed' => 'Amoxil 500mg',
            'match_type' => 'class',
            'severity' => 'severe',
            'was_overridden' => true,
            'doctor_id' => $this->doctor->id,
        ]);
    }

    public function test_records_a_moderate_allergy_conflict_as_a_warning_without_blocking(): void
    {
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create();
        PatientAllergy::factory()->for($this->visit->patient)->to('Ibuprofen')->create();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $drug->id, 'dose' => '1 tablet', 'frequency' => 'TDS', 'duration' => 5, 'quantity' => 15],
            ],
        ]));

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('allergy_conflict_checks', [
            'allergy_found' => 'Ibuprofen',
            'severity' => 'moderate',
            'was_overridden' => false,
        ]);
    }

    public function test_links_a_continued_chronic_medication_to_the_prescribed_item(): void
    {
        $drug = Drug::factory()->named('Glucophage 500mg', 'Metformin')->create();
        $chronic = PatientChronicMedication::factory()->for($this->visit->patient)->create([
            'drug_id' => $drug->id,
            'drug_name' => 'Metformin',
            'frequency' => 'BD',
        ]);

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                [
                    'drug_id' => $drug->id,
                    'chronic_medication_id' => $chronic->id,
                    'dose' => '1 tablet',
                    'frequency' => 'BD',
                    'duration' => 30,
                    'quantity' => 60,
                ],
            ],
        ]));

        $this->assertDatabaseHas('prescription_items', [
            'drug_id' => $drug->id,
            'chronic_medication_id' => $chronic->id,
        ]);
    }

    public function test_rejects_continuing_a_chronic_medication_belonging_to_another_patient(): void
    {
        $drug = Drug::factory()->named('Glucophage 500mg', 'Metformin')->create();
        $otherPatient = Patient::factory()->create();
        $chronic = PatientChronicMedication::factory()->for($otherPatient)->create(['drug_id' => $drug->id]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                [
                    'drug_id' => $drug->id,
                    'chronic_medication_id' => $chronic->id,
                    'dose' => '1 tablet',
                    'frequency' => 'BD',
                    'duration' => 30,
                    'quantity' => 60,
                ],
            ],
        ]));

        $response->assertSessionHasErrors([
            'prescription_items' => 'A continued medication does not belong to this patient.',
        ]);
        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_blocks_a_major_drug_interaction_without_a_justification(): void
    {
        $warfarin = Drug::factory()->named('Warfarin 5mg', 'Warfarin')->create();
        $aspirin = Drug::factory()->named('Aspirin 300mg', 'Aspirin')->create();

        DrugInteraction::create([
            'drug_id' => $warfarin->id,
            'interacts_with_drug_id' => $aspirin->id,
            'severity' => 'major',
            'effect' => 'Greatly increased bleeding risk.',
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $warfarin->id, 'dose' => '5mg', 'frequency' => 'OD', 'duration' => 30, 'quantity' => 30],
                ['drug_id' => $aspirin->id, 'dose' => '300mg', 'frequency' => 'OD', 'duration' => 30, 'quantity' => 30],
            ],
        ]));

        $key = 'interaction:'.min($warfarin->id, $aspirin->id).'-'.max($warfarin->id, $aspirin->id);
        $response->assertSessionHasErrors("safety_justifications.{$key}");
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_allows_a_major_interaction_when_justified_and_records_the_warning(): void
    {
        $warfarin = Drug::factory()->named('Warfarin 5mg', 'Warfarin')->create();
        $aspirin = Drug::factory()->named('Aspirin 300mg', 'Aspirin')->create();

        DrugInteraction::create([
            'drug_id' => $warfarin->id,
            'interacts_with_drug_id' => $aspirin->id,
            'severity' => 'major',
            'effect' => 'Greatly increased bleeding risk.',
        ]);

        $key = 'interaction:'.min($warfarin->id, $aspirin->id).'-'.max($warfarin->id, $aspirin->id);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'prescription_items' => [
                ['drug_id' => $warfarin->id, 'dose' => '5mg', 'frequency' => 'OD', 'duration' => 30, 'quantity' => 30],
                ['drug_id' => $aspirin->id, 'dose' => '300mg', 'frequency' => 'OD', 'duration' => 30, 'quantity' => 30],
            ],
            'safety_justifications' => [
                $key => 'Mechanical valve on warfarin; aspirin indicated after cardiology advice.',
            ],
        ]));

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription_safety_warning']);
    }

    public function test_organ_impairment_entered_on_this_submission_gates_the_first_save(): void
    {
        // The impairment is only written inside the transaction, so checking the
        // stored consultation meant this gate never fired on a first save.
        $gentamicin = Drug::factory()->named('Gentamicin 80mg', 'Gentamicin')->create([
            'is_renally_cleared' => true,
            'renal_guidance' => 'Extend the dosing interval and monitor levels.',
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'renal_impairment' => 'severe',
            'prescription_items' => [
                ['drug_id' => $gentamicin->id, 'dose' => '80mg', 'frequency' => 'TDS', 'duration' => 5, 'quantity' => 15],
            ],
        ]));

        $response->assertSessionHasErrors("safety_justifications.organ-renal:{$gentamicin->id}");
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_blocks_a_first_trimester_drug_for_a_pregnant_patient_without_a_justification(): void
    {
        $coartem = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
            'pregnancy_guidance' => 'Use quinine plus clindamycin in the first trimester.',
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(9)->toDateString(),
            'prescription_items' => [
                ['drug_id' => $coartem->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
        ]));

        $response->assertSessionHasErrors("safety_justifications.pregnancy:{$coartem->id}");
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_allows_a_pregnancy_warning_when_justified_and_records_it(): void
    {
        $coartem = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(9)->toDateString(),
            'prescription_items' => [
                ['drug_id' => $coartem->id, 'dose' => '4 tablets', 'frequency' => 'BD', 'duration' => 3, 'quantity' => 24],
            ],
            'safety_justifications' => [
                "pregnancy:{$coartem->id}" => 'Severe malaria, quinine unavailable; benefit outweighs risk after consultant advice.',
            ],
        ]));

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription_safety_warning']);
    }

    public function test_refuses_to_re_save_a_signed_consultation(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());

        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload([
            'clinical_notes' => 'Trying to overwrite the signed note.',
        ]));

        $response->assertRedirect(route('consultations.create', $this->visit));
        $response->assertSessionHas('error', 'This consultation is already signed. Record an amendment instead.');
        $this->assertDatabaseMissing('clinical_notes', ['content' => 'Trying to overwrite the signed note.']);
    }

    public function test_an_amendment_raises_the_version_and_keeps_the_original_text(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();
        $this->assertSame(1, $consultation->version);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Chest x-ray reported after sign-off and changes the impression.',
            'clinical_notes' => 'Fever for three days. Chest x-ray shows right basal consolidation.',
        ]);

        $response->assertRedirect(route('consultations.create', $this->visit->id));
        $this->assertSame(2, $consultation->refresh()->version);
        $this->assertDatabaseHas('consultation_amendments', [
            'consultation_id' => $consultation->id,
            'field_name' => 'clinical_notes',
            'old_value' => 'Fever for three days, no rigors. Chest clear.',
            'reason' => 'Chest x-ray reported after sign-off and changes the impression.',
        ]);
        $this->assertDatabaseHas('clinical_notes', [
            'consultation_id' => $consultation->id,
            'content' => 'Fever for three days. Chest x-ray shows right basal consolidation.',
        ]);
    }

    public function test_an_amendment_that_changes_nothing_is_not_recorded(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $this->actingAs($this->doctor)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Re-reading the note after the ward round.',
            'clinical_notes' => 'Fever for three days, no rigors. Chest clear.',
        ]);

        $this->assertSame(1, $consultation->refresh()->version);
        $this->assertDatabaseCount('consultation_amendments', 0);
    }

    public function test_rejects_an_amendment_without_a_reason(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $response = $this->actingAs($this->doctor)->post("/consultations/{$consultation->id}/amend", [
            'clinical_notes' => 'Revised impression.',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(1, $consultation->refresh()->version);
    }

    public function test_rejects_an_amendment_to_a_consultation_that_is_not_signed(): void
    {
        $consultation = Consultation::factory()->draft()->create([
            'visit_id' => $this->visit->id,
            'doctor_id' => $this->doctor->id,
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Trying to amend a note that has not been signed yet.',
            'clinical_notes' => 'Revised impression.',
        ]);

        $response->assertSessionHasErrors('amendment');
        $this->assertDatabaseCount('consultation_amendments', 0);
    }

    public function test_forbids_another_clinician_from_amending_without_permission(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $otherDoctor = User::factory()->create();

        $response = $this->actingAs($otherDoctor)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Covering colleague wants to change the impression.',
            'clinical_notes' => 'Revised impression.',
        ]);

        $response->assertForbidden();
        $this->assertSame(1, $consultation->refresh()->version);
    }

    public function test_allows_another_clinician_holding_the_amend_permission(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->signablePayload());
        $consultation = Consultation::where('visit_id', $this->visit->id)->sole();

        $permission = Permission::create(['slug' => 'amend_clinical_notes', 'name' => 'Amend clinical notes']);
        $seniorRole = Role::create(['name' => 'Consultant', 'slug' => 'consultant']);
        $seniorRole->permissions()->sync([$permission->id]);
        $senior = User::factory()->create();
        $senior->roles()->attach($seniorRole);

        $response = $this->actingAs($senior)->post("/consultations/{$consultation->id}/amend", [
            'reason' => 'Senior review changed the working impression after the ward round.',
            'clinical_notes' => 'Revised impression after senior review.',
        ]);

        $response->assertRedirect(route('consultations.create', $this->visit->id));
        $this->assertSame(2, $consultation->refresh()->version);
        $this->assertDatabaseHas('consultation_amendments', [
            'consultation_id' => $consultation->id,
            'user_id' => $senior->id,
        ]);
    }
}
