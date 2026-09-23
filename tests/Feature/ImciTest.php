<?php

namespace Tests\Feature;

use App\Models\ImciAssessment;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\ImciClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHO IMCI for children 2 months to 5 years.
 *
 * IMCI is deliberately mechanical — the findings decide the classification with
 * no judgement in between — so these tests walk the published tables.
 */
class ImciTest extends TestCase
{
    use RefreshDatabase;

    protected User $clinician;

    protected ImciClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Nurse', 'slug' => 'nurse']);
        $role->permissions()->sync([
            Permission::updateOrCreate(['slug' => 'update_clinical_safety'], ['name' => 'update_clinical_safety'])->id,
            Permission::updateOrCreate(['slug' => 'view_clinical_history'], ['name' => 'view_clinical_history'])->id,
        ]);

        $this->clinician = User::factory()->create();
        $this->clinician->roles()->attach($role);

        $this->classifier = app(ImciClassifier::class);
    }

    protected function childAged(int $months): Patient
    {
        return Patient::factory()->create([
            'date_of_birth' => now()->subMonths($months)->subDays(2)->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $findings
     * @return list<array<string, string>>
     */
    protected function classify(int $ageMonths, array $findings): array
    {
        $assessment = new ImciAssessment(array_merge(['age_months' => $ageMonths], $findings));

        return $this->classifier->classify($assessment);
    }

    /**
     * @param  list<array<string, string>>  $classifications
     */
    protected function names(array $classifications): array
    {
        return array_column($classifications, 'classification');
    }

    // ---- Fast breathing thresholds -------------------------------------

    public function test_the_fast_breathing_threshold_falls_as_the_child_grows(): void
    {
        // 50 under a year, 40 from one to five. A single number cannot serve both.
        $this->assertSame(50, $this->classifier->fastBreathingThreshold(6));
        $this->assertSame(40, $this->classifier->fastBreathingThreshold(18));
    }

    public function test_an_infant_at_forty_five_breaths_is_not_pneumonia_but_a_toddler_is(): void
    {
        $infant = $this->classify(6, ['has_cough' => true, 'respiratory_rate' => 45]);
        $toddler = $this->classify(18, ['has_cough' => true, 'respiratory_rate' => 45]);

        $this->assertContains('COUGH OR COLD', $this->names($infant));
        $this->assertContains('PNEUMONIA', $this->names($toddler));
    }

    public function test_chest_indrawing_is_severe_whatever_the_rate(): void
    {
        $result = $this->classify(18, [
            'has_cough' => true,
            'respiratory_rate' => 30,
            'chest_indrawing' => true,
        ]);

        $this->assertContains('SEVERE PNEUMONIA OR VERY SEVERE DISEASE', $this->names($result));
        $this->assertTrue($this->classifier->needsUrgentReferral($result));
    }

    public function test_a_cough_without_a_counted_rate_is_not_called_harmless(): void
    {
        $result = $this->classify(18, ['has_cough' => true]);

        // Silence about the rate must not read as "no pneumonia".
        $this->assertContains('NOT CLASSIFIED — count the breaths', $this->names($result));
    }

    // ---- Dehydration ---------------------------------------------------

    public function test_two_severe_signs_make_severe_dehydration(): void
    {
        $result = $this->classify(12, [
            'has_diarrhoea' => true,
            'sunken_eyes' => true,
            'skin_pinch' => 'very_slow',
        ]);

        $this->assertContains('SEVERE DEHYDRATION', $this->names($result));
    }

    public function test_one_sign_alone_is_not_enough(): void
    {
        $result = $this->classify(12, [
            'has_diarrhoea' => true,
            'sunken_eyes' => true,
        ]);

        // IMCI counts signs rather than weighing them: one is not a classification.
        $this->assertContains('NO DEHYDRATION', $this->names($result));
    }

    public function test_some_dehydration_from_the_middle_column(): void
    {
        $result = $this->classify(12, [
            'has_diarrhoea' => true,
            'restless_or_irritable' => true,
            'drinking' => 'eagerly',
        ]);

        $this->assertContains('SOME DEHYDRATION', $this->names($result));
    }

    public function test_long_diarrhoea_with_blood_yields_persistent_and_dysentery(): void
    {
        $result = $this->classify(12, [
            'has_diarrhoea' => true,
            'diarrhoea_days' => 16,
            'blood_in_stool' => true,
        ]);

        $names = $this->names($result);

        $this->assertContains('PERSISTENT DIARRHOEA', $names);
        $this->assertContains('DYSENTERY', $names);
    }

    // ---- Fever ---------------------------------------------------------

    public function test_stiff_neck_with_fever_is_very_severe_febrile_disease(): void
    {
        $result = $this->classify(24, ['has_fever' => true, 'stiff_neck' => true]);

        $this->assertContains('VERY SEVERE FEBRILE DISEASE', $this->names($result));
        $this->assertTrue($this->classifier->needsUrgentReferral($result));
    }

    public function test_an_untested_fever_is_not_cleared_of_malaria(): void
    {
        $result = $this->classify(24, ['has_fever' => true, 'malaria_test_result' => 'not_done']);

        // In an endemic setting, "not tested" is not "no malaria".
        $this->assertContains('FEVER — MALARIA NOT EXCLUDED', $this->names($result));
    }

    public function test_a_positive_test_classifies_malaria(): void
    {
        $result = $this->classify(24, ['has_fever' => true, 'malaria_test_result' => 'positive']);

        $this->assertContains('MALARIA', $this->names($result));
    }

    // ---- Nutrition, anaemia and ears -----------------------------------

    public function test_severe_wasting_or_oedema_is_severe_acute_malnutrition(): void
    {
        $byMuac = $this->classify(24, ['muac_cm' => 11.0]);
        $byOedema = $this->classify(24, ['oedema_both_feet' => true]);

        $this->assertContains('SEVERE ACUTE MALNUTRITION', $this->names($byMuac));
        $this->assertContains('SEVERE ACUTE MALNUTRITION', $this->names($byOedema));
    }

    public function test_severe_palmar_pallor_is_severe_anaemia(): void
    {
        $result = $this->classify(24, ['palmar_pallor' => 'severe']);

        $this->assertContains('SEVERE ANAEMIA', $this->names($result));
    }

    public function test_tender_swelling_behind_the_ear_is_mastoiditis(): void
    {
        $result = $this->classify(24, [
            'has_ear_problem' => true,
            'tender_swelling_behind_ear' => true,
        ]);

        $this->assertContains('MASTOIDITIS', $this->names($result));
    }

    public function test_long_ear_discharge_is_chronic_not_acute(): void
    {
        $acute = $this->classify(24, ['has_ear_problem' => true, 'ear_discharge_days' => 3]);
        $chronic = $this->classify(24, ['has_ear_problem' => true, 'ear_discharge_days' => 20]);

        $this->assertContains('ACUTE EAR INFECTION', $this->names($acute));
        $this->assertContains('CHRONIC EAR INFECTION', $this->names($chronic));
    }

    // ---- General danger signs ------------------------------------------

    public function test_a_general_danger_sign_overrides_everything(): void
    {
        $result = $this->classify(24, [
            'lethargic_or_unconscious' => true,
            'has_cough' => true,
            'respiratory_rate' => 20,
        ]);

        $names = $this->names($result);

        $this->assertContains('VERY SEVERE DISEASE', $names);
        // The cough is re-read as severe once a danger sign is present.
        $this->assertContains('SEVERE PNEUMONIA OR VERY SEVERE DISEASE', $names);
    }

    // ---- The endpoint ---------------------------------------------------

    public function test_an_assessment_is_stored_with_its_classifications(): void
    {
        $child = $this->childAged(18);
        $visit = Visit::factory()->create(['patient_id' => $child->id]);

        $this->actingAs($this->clinician)->post("/visits/{$visit->id}/imci", [
            'has_cough' => 1,
            'respiratory_rate' => 48,
            'has_fever' => 1,
            'malaria_test_result' => 'positive',
        ])->assertSessionHasNoErrors();

        $assessment = ImciAssessment::where('patient_id', $child->id)->sole();
        $names = array_column($assessment->classifications, 'classification');

        $this->assertContains('PNEUMONIA', $names);
        $this->assertContains('MALARIA', $names);
        $this->assertDatabaseHas('audit_logs', ['action' => 'imci_assessment_recorded']);
    }

    public function test_a_severe_classification_warns_to_give_pre_referral_treatment(): void
    {
        $child = $this->childAged(18);
        $visit = Visit::factory()->create(['patient_id' => $child->id]);

        $this->actingAs($this->clinician)->post("/visits/{$visit->id}/imci", [
            'has_cough' => 1,
            'chest_indrawing' => 1,
        ])->assertSessionHas('warning');

        $this->assertTrue(ImciAssessment::where('patient_id', $child->id)->sole()->needsUrgentReferral());
    }

    public function test_imci_is_refused_outside_its_age_range(): void
    {
        $newborn = $this->childAged(1);
        $visit = Visit::factory()->create(['patient_id' => $newborn->id]);

        // Below two months the young infant chart applies, which asks different
        // questions and is not implemented.
        $this->actingAs($this->clinician)->post("/visits/{$visit->id}/imci", ['has_cough' => 1])
            ->assertSessionHasErrors('imci');

        $olderChild = $this->childAged(72);
        $olderVisit = Visit::factory()->create(['patient_id' => $olderChild->id]);

        $this->actingAs($this->clinician)->post("/visits/{$olderVisit->id}/imci", ['has_cough' => 1])
            ->assertSessionHasErrors('imci');
    }
}
