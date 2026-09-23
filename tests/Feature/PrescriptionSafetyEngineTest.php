<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\DrugInteraction;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Services\PrescriptionSafetyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionSafetyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected PrescriptionSafetyEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(PrescriptionSafetyEngine::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function item(Drug $drug, array $overrides = []): array
    {
        return array_merge([
            'drug_id' => $drug->id,
            'dose' => '500mg',
            'frequency' => 'TDS',
            'duration' => 5,
            'quantity' => 15,
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    protected function ofCategory(array $findings, string $category): array
    {
        return array_values(array_filter($findings, fn ($f) => $f['category'] === $category));
    }

    public function test_reports_a_major_interaction_between_two_prescribed_drugs(): void
    {
        $patient = Patient::factory()->create();
        $warfarin = Drug::factory()->named('Warfarin 5mg', 'Warfarin')->create();
        $aspirin = Drug::factory()->named('Aspirin 300mg', 'Aspirin')->create();

        DrugInteraction::create([
            'drug_id' => $warfarin->id,
            'interacts_with_drug_id' => $aspirin->id,
            'severity' => 'major',
            'effect' => 'Greatly increased bleeding risk.',
            'management' => 'Avoid; use paracetamol for analgesia.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($warfarin), $this->item($aspirin)]);
        $interactions = $this->ofCategory($findings, 'interaction');

        $this->assertCount(1, $interactions);
        $this->assertSame('major', $interactions[0]['severity']);
        $this->assertTrue($interactions[0]['requires_justification']);
    }

    public function test_an_interaction_rule_applies_whichever_way_round_the_pair_is_prescribed(): void
    {
        $patient = Patient::factory()->create();
        $first = Drug::factory()->named('Warfarin 5mg', 'Warfarin')->create();
        $second = Drug::factory()->named('Aspirin 300mg', 'Aspirin')->create();

        DrugInteraction::create([
            'drug_id' => $second->id,
            'interacts_with_drug_id' => $first->id,
            'severity' => 'major',
            'effect' => 'Increased bleeding risk.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($first), $this->item($second)]);

        $this->assertCount(1, $this->ofCategory($findings, 'interaction'));
    }

    public function test_reports_an_interaction_stated_between_two_drug_classes(): void
    {
        $patient = Patient::factory()->create();
        $ace = Drug::factory()->named('Lisinopril 10mg', 'Lisinopril', 'ace-inhibitor')->create();
        $nsaid = Drug::factory()->named('Ibuprofen 400mg', 'Ibuprofen', 'nsaid')->create();

        DrugInteraction::create([
            'drug_class' => 'ace-inhibitor',
            'interacts_with_class' => 'nsaid',
            'severity' => 'moderate',
            'effect' => 'Reduced antihypertensive effect and risk of renal impairment.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($ace), $this->item($nsaid)]);
        $interactions = $this->ofCategory($findings, 'interaction');

        $this->assertCount(1, $interactions);
        // Moderate is advisory, not a gate.
        $this->assertFalse($interactions[0]['requires_justification']);
    }

    public function test_a_single_drug_cannot_interact_with_itself(): void
    {
        $patient = Patient::factory()->create();
        $drug = Drug::factory()->named('Warfarin 5mg', 'Warfarin')->create();

        DrugInteraction::create([
            'drug_id' => $drug->id,
            'interacts_with_drug_id' => $drug->id,
            'severity' => 'major',
            'effect' => 'Nonsense rule.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($drug)]);

        $this->assertSame([], $this->ofCategory($findings, 'interaction'));
    }

    public function test_flags_a_daily_dose_above_the_recorded_maximum(): void
    {
        $patient = Patient::factory()->create();
        $drug = Drug::factory()->named('Paracetamol 500mg', 'Paracetamol')->create([
            'max_daily_dose' => 4000,
            'dose_unit' => 'mg',
        ]);

        // 1500mg four times a day is 6000mg, well over the 4000mg ceiling.
        $findings = $this->engine->check($patient, [
            $this->item($drug, ['dose' => '1500mg', 'frequency' => 'QDS']),
        ]);

        $doses = $this->ofCategory($findings, 'dose');
        $this->assertCount(1, $doses);
        $this->assertTrue($doses[0]['requires_justification']);
    }

    public function test_a_dose_within_the_maximum_is_not_flagged(): void
    {
        $patient = Patient::factory()->create();
        $drug = Drug::factory()->named('Paracetamol 500mg', 'Paracetamol')->create([
            'max_daily_dose' => 4000,
            'dose_unit' => 'mg',
        ]);

        $findings = $this->engine->check($patient, [
            $this->item($drug, ['dose' => '1000mg', 'frequency' => 'QDS']),
        ]);

        $this->assertSame([], $this->ofCategory($findings, 'dose'));
    }

    public function test_a_free_text_dose_is_not_dose_checked(): void
    {
        $patient = Patient::factory()->create();
        $drug = Drug::factory()->named('Paracetamol 500mg', 'Paracetamol')->create([
            'max_single_dose' => 1,
            'dose_unit' => 'mg',
        ]);

        // "One tablet" carries no number, so there is nothing to compare.
        $findings = $this->engine->check($patient, [
            $this->item($drug, ['dose' => 'One tablet', 'frequency' => 'TDS']),
        ]);

        $this->assertSame([], $this->ofCategory($findings, 'dose'));
    }

    public function test_flags_a_paediatric_dose_above_the_weight_based_maximum(): void
    {
        $patient = Patient::factory()->create(['date_of_birth' => now()->subYears(4)->format('Y-m-d')]);
        $consultation = Consultation::factory()->create(['weight_kg' => 15]);
        $drug = Drug::factory()->named('Amoxil 250mg', 'Amoxicillin')->create([
            'paediatric_max_per_kg' => 15,
            'dose_unit' => 'mg',
        ]);

        // 15kg x 15mg/kg is 225mg; 500mg is over.
        $findings = $this->engine->check($patient, [
            $this->item($drug, ['dose' => '500mg']),
        ], $consultation);

        $doses = $this->ofCategory($findings, 'dose');
        $this->assertCount(1, $doses);
        $this->assertStringContainsString('weight-based maximum', $doses[0]['title']);
    }

    public function test_asks_for_a_weight_when_a_child_is_given_a_weight_based_drug(): void
    {
        $patient = Patient::factory()->create(['date_of_birth' => now()->subYears(3)->format('Y-m-d')]);
        $consultation = Consultation::factory()->create(['weight_kg' => null]);
        $drug = Drug::factory()->named('Amoxil 250mg', 'Amoxicillin')->create([
            'paediatric_max_per_kg' => 15,
            'dose_unit' => 'mg',
        ]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);
        $doses = $this->ofCategory($findings, 'dose');

        $this->assertCount(1, $doses);
        // Missing information is a prompt, not a barrier.
        $this->assertFalse($doses[0]['requires_justification']);
    }

    public function test_blocks_a_first_trimester_drug_when_the_patient_is_in_the_first_trimester(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(8),
        ]);
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
            'pregnancy_guidance' => 'Use quinine plus clindamycin in the first trimester.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);
        $pregnancy = $this->ofCategory($findings, 'pregnancy');

        $this->assertCount(1, $pregnancy);
        $this->assertTrue($pregnancy[0]['requires_justification']);
        $this->assertStringContainsString('first trimester', $pregnancy[0]['title']);
        $this->assertStringContainsString('quinine', $pregnancy[0]['detail']);
    }

    public function test_a_first_trimester_drug_is_only_advisory_later_in_the_pregnancy(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(30),
        ]);
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        $this->assertCount(1, $pregnancy);
        $this->assertFalse($pregnancy[0]['requires_justification']);
    }

    public function test_an_unknown_gestational_age_warns_rather_than_blocking_or_going_quiet(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => null,
            'gestational_age' => null,
        ]);
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_THIRD,
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        // A missing date is not evidence of safety, so the finding still shows.
        $this->assertCount(1, $pregnancy);
        $this->assertFalse($pregnancy[0]['requires_justification']);
        $this->assertStringContainsString('could not be confirmed', $pregnancy[0]['detail']);
    }

    public function test_reads_the_trimester_from_a_written_gestational_age_when_there_is_no_lmp(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => null,
            'gestational_age' => '32 weeks',
        ]);
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_THIRD,
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        $this->assertTrue($pregnancy[0]['requires_justification']);
    }

    public function test_a_contraindicated_drug_blocks_in_every_trimester(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(20),
        ]);
        $drug = Drug::factory()->named('Methotrexate 2.5mg', 'Methotrexate')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_CONTRAINDICATED,
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        $this->assertTrue($pregnancy[0]['requires_justification']);
        $this->assertStringContainsString('contraindicated', $pregnancy[0]['title']);
    }

    public function test_a_drug_with_no_recorded_category_is_not_flagged_in_pregnancy(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(8),
        ]);
        // Silence here means "nothing is recorded", not "this drug is safe" —
        // the same rule the dose checks follow.
        $drug = Drug::factory()->named('Unlisted 100mg', 'Unlisted')->create(['pregnancy_category' => null]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);

        $this->assertSame([], $this->ofCategory($findings, 'pregnancy'));
    }

    public function test_a_patient_who_is_not_pregnant_raises_no_pregnancy_finding(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create(['pregnancy_status' => 'none']);
        $drug = Drug::factory()->named('Coartem', 'Artemether-Lumefantrine')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
        ]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);

        $this->assertSame([], $this->ofCategory($findings, 'pregnancy'));
    }

    public function test_blocks_a_drug_to_be_avoided_while_breastfeeding(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'none',
            'lactation_status' => 'breastfeeding',
        ]);
        $drug = Drug::factory()->named('Cytotoxic 10mg', 'Cytotoxic')->create([
            'lactation_category' => PrescriptionSafetyEngine::LACTATION_AVOID,
            'lactation_guidance' => 'Suspend breastfeeding for the duration of treatment.',
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        $this->assertCount(1, $pregnancy);
        $this->assertTrue($pregnancy[0]['requires_justification']);
        $this->assertSame('lactation:'.$drug->id, $pregnancy[0]['key']);
        $this->assertStringContainsString('Suspend breastfeeding', $pregnancy[0]['detail']);
    }

    public function test_a_pregnant_and_breastfeeding_patient_gets_both_findings(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create([
            'pregnancy_status' => 'pregnant',
            'lmp' => now()->subWeeks(8),
            'lactation_status' => 'breastfeeding',
        ]);
        $drug = Drug::factory()->named('Flagyl 400mg', 'Metronidazole')->create([
            'pregnancy_category' => PrescriptionSafetyEngine::PREGNANCY_AVOID_FIRST,
            'lactation_category' => PrescriptionSafetyEngine::LACTATION_CAUTION,
        ]);

        $pregnancy = $this->ofCategory($this->engine->check($patient, [$this->item($drug)], $consultation), 'pregnancy');

        $this->assertCount(2, $pregnancy);
        $this->assertEqualsCanonicalizing(
            ['pregnancy:'.$drug->id, 'lactation:'.$drug->id],
            array_column($pregnancy, 'key')
        );
    }

    public function test_flags_a_renally_cleared_drug_in_severe_renal_impairment(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create(['renal_impairment' => 'severe']);
        $drug = Drug::factory()->named('Gentamicin 80mg', 'Gentamicin')->create([
            'is_renally_cleared' => true,
            'renal_guidance' => 'Extend the dosing interval and monitor levels.',
        ]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);
        $organ = $this->ofCategory($findings, 'organ');

        $this->assertCount(1, $organ);
        $this->assertTrue($organ[0]['requires_justification']);
        $this->assertStringContainsString('dosing interval', $organ[0]['detail']);
    }

    public function test_infers_renal_impairment_from_egfr_when_no_grade_was_recorded(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create(['renal_impairment' => null, 'egfr' => 18]);
        $drug = Drug::factory()->named('Gentamicin 80mg', 'Gentamicin')->create(['is_renally_cleared' => true]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);

        $this->assertCount(1, $this->ofCategory($findings, 'organ'));
    }

    public function test_normal_renal_function_raises_no_organ_finding(): void
    {
        $patient = Patient::factory()->create();
        $consultation = Consultation::factory()->create(['renal_impairment' => 'none', 'egfr' => 95]);
        $drug = Drug::factory()->named('Gentamicin 80mg', 'Gentamicin')->create(['is_renally_cleared' => true]);

        $findings = $this->engine->check($patient, [$this->item($drug)], $consultation);

        $this->assertSame([], $this->ofCategory($findings, 'organ'));
    }

    public function test_allergy_findings_are_included_alongside_the_other_checks(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Penicillin')->severe()->create();
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $findings = $this->engine->check($patient, [$this->item($drug)]);
        $allergies = $this->ofCategory($findings, 'allergy');

        $this->assertCount(1, $allergies);
        $this->assertTrue($allergies[0]['requires_justification']);
    }

    public function test_blocking_returns_only_the_findings_that_need_a_justification(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Ibuprofen')->create(); // moderate
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create();

        $findings = $this->engine->check($patient, [$this->item($drug)]);

        $this->assertNotEmpty($findings);
        $this->assertSame([], $this->engine->blocking($findings));
    }
}
