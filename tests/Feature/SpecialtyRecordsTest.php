<?php

namespace Tests\Feature;

use App\Models\AncAssessment;
use App\Models\GrowthMeasurement;
use App\Models\Immunization;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\AntenatalSchedule;
use App\Services\ImmunizationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::updateOrCreate(
            ['slug' => 'update_clinical_safety'],
            ['name' => 'Update clinical safety']
        );
        $role = Role::create(['name' => 'Nurse', 'slug' => 'nurse']);
        $role->permissions()->sync([$permission->id]);

        $this->nurse = User::factory()->create();
        $this->nurse->roles()->attach($role);
    }

    protected function child(int $ageMonths = 24): Patient
    {
        return Patient::factory()->create([
            'date_of_birth' => now()->subMonths($ageMonths)->format('Y-m-d'),
        ]);
    }

    public function test_a_growth_measurement_records_the_age_at_the_time(): void
    {
        $patient = $this->child(18);

        $this->actingAs($this->nurse)->post("/patients/{$patient->id}/growth", [
            'weight_kg' => 9.8,
            'height_cm' => 78,
            'muac_cm' => 13.5,
        ])->assertRedirect();

        $measurement = GrowthMeasurement::where('patient_id', $patient->id)->sole();
        $this->assertSame(18, $measurement->age_months);
        $this->assertSame($this->nurse->id, $measurement->recorded_by);
    }

    public function test_a_measurement_with_nothing_filled_in_is_rejected(): void
    {
        $patient = $this->child();

        $this->actingAs($this->nurse)
            ->post("/patients/{$patient->id}/growth", [])
            ->assertSessionHasErrors('weight_kg');

        $this->assertDatabaseCount('growth_measurements', 0);
    }

    public function test_muac_below_the_severe_threshold_is_graded_severe(): void
    {
        $measurement = new GrowthMeasurement(['muac_cm' => 11.0, 'age_months' => 24]);

        $this->assertSame('severe', $measurement->acuteMalnutritionGrade());
    }

    public function test_muac_between_the_thresholds_is_graded_moderate(): void
    {
        $measurement = new GrowthMeasurement(['muac_cm' => 12.0, 'age_months' => 24]);

        $this->assertSame('moderate', $measurement->acuteMalnutritionGrade());
    }

    public function test_muac_is_not_graded_outside_six_to_fifty_nine_months(): void
    {
        $tooYoung = new GrowthMeasurement(['muac_cm' => 11.0, 'age_months' => 3]);
        $tooOld = new GrowthMeasurement(['muac_cm' => 11.0, 'age_months' => 72]);

        $this->assertNull($tooYoung->acuteMalnutritionGrade());
        $this->assertNull($tooOld->acuteMalnutritionGrade());
    }

    public function test_bmi_is_computed_from_weight_and_height(): void
    {
        $measurement = new GrowthMeasurement(['weight_kg' => 70, 'height_cm' => 175]);

        $this->assertSame(22.9, $measurement->bmi());
    }

    public function test_an_immunisation_dose_is_recorded_against_the_patient(): void
    {
        $patient = $this->child(3);

        $this->actingAs($this->nurse)->post("/patients/{$patient->id}/immunizations", [
            'vaccine_code' => 'PENTA',
            'vaccine_name' => 'Pentavalent (DTP-HepB-Hib)',
            'dose_number' => 1,
            'batch_number' => 'B-2026-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('immunizations', [
            'patient_id' => $patient->id,
            'vaccine_code' => 'PENTA',
            'dose_number' => 1,
            'given_by' => $this->nurse->id,
        ]);
    }

    public function test_the_same_dose_cannot_be_recorded_twice(): void
    {
        $patient = $this->child(3);
        Immunization::create([
            'patient_id' => $patient->id,
            'vaccine_code' => 'PENTA',
            'vaccine_name' => 'Pentavalent',
            'dose_number' => 1,
            'given_at' => now()->subWeek(),
            'given_by' => $this->nurse->id,
        ]);

        $this->actingAs($this->nurse)->post("/patients/{$patient->id}/immunizations", [
            'vaccine_code' => 'PENTA',
            'vaccine_name' => 'Pentavalent',
            'dose_number' => 1,
        ])->assertSessionHasErrors('vaccine_code');

        $this->assertDatabaseCount('immunizations', 1);
    }

    public function test_the_schedule_marks_a_long_overdue_dose_as_overdue(): void
    {
        $patient = $this->child(12);
        $status = app(ImmunizationSchedule::class)->statusFor($patient);

        $bcg = $status->firstWhere('code', 'BCG');
        $this->assertSame('overdue', $bcg['status']);
    }

    public function test_the_schedule_marks_a_dose_already_given_as_given(): void
    {
        $patient = $this->child(12);
        Immunization::create([
            'patient_id' => $patient->id,
            'vaccine_code' => 'BCG',
            'vaccine_name' => 'BCG',
            'dose_number' => 1,
            'given_at' => now()->subMonths(12),
            'given_by' => $this->nurse->id,
        ]);

        $status = app(ImmunizationSchedule::class)->statusFor($patient);

        $this->assertSame('given', $status->firstWhere('code', 'BCG')['status']);
    }

    public function test_a_dose_not_yet_due_is_marked_scheduled(): void
    {
        $newborn = $this->child(0);
        $status = app(ImmunizationSchedule::class)->statusFor($newborn);

        $measles = $status->first(fn ($entry) => $entry['code'] === 'MR' && $entry['dose'] === 1);
        $this->assertSame('scheduled', $measles['status']);
    }

    public function test_the_paediatric_record_renders_growth_and_immunisation(): void
    {
        $patient = $this->child(18);
        GrowthMeasurement::create([
            'patient_id' => $patient->id,
            'measured_at' => now(),
            'age_months' => 18,
            'weight_kg' => 9.8,
            'muac_cm' => 11.2,
            'recorded_by' => $this->nurse->id,
        ]);

        $response = $this->actingAs($this->nurse)->get("/patients/{$patient->id}/paediatrics");

        $response->assertOk();
        $response->assertSee('Severe acute malnutrition by MUAC');
        $response->assertSee('Pentavalent (DTP-HepB-Hib)');
    }

    public function test_an_antenatal_contact_flags_the_pre_eclampsia_pattern(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        $response = $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 32,
            'systolic_bp' => 150,
            'diastolic_bp' => 100,
            'urinalysis_protein' => '++',
            'haemoglobin' => 12.5,
        ]);

        $response->assertSessionHas('warning');
        $assessment = AncAssessment::where('patient_id', $patient->id)->sole();
        $this->assertContains('Hypertension with proteinuria — assess for pre-eclampsia', $assessment->dangerSigns());
    }

    public function test_a_normal_antenatal_contact_raises_no_danger_signs(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 28,
            'systolic_bp' => 118,
            'diastolic_bp' => 74,
            'urinalysis_protein' => 'Nil',
            'haemoglobin' => 12.2,
            'fetal_heart_rate' => 140,
        ])->assertSessionHas('success');

        $this->assertSame([], AncAssessment::where('patient_id', $patient->id)->sole()->dangerSigns());
    }

    public function test_antenatal_contacts_are_numbered_in_sequence(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", ['gestational_age_weeks' => 12]);
        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", ['gestational_age_weeks' => 20]);

        $this->assertSame(
            [1, 2],
            AncAssessment::where('patient_id', $patient->id)->orderBy('contact_number')->pluck('contact_number')->all()
        );
    }

    public function test_iptp_doses_are_numbered_so_the_record_can_prove_a_third_dose(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        // Doses must be at least a month apart, so travel between them.
        foreach ([16, 20, 24] as $index => $weeks) {
            $this->travelTo(now()->addDays($index * 30));

            $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
                'gestational_age_weeks' => $weeks,
                'iptp_given' => 1,
            ])->assertSessionHasNoErrors();
        }

        $this->travelBack();

        $this->assertSame(
            [1, 2, 3],
            AncAssessment::where('patient_id', $patient->id)
                ->whereNotNull('iptp_dose_number')
                ->orderBy('iptp_dose_number')
                ->pluck('iptp_dose_number')
                ->all()
        );

        $this->assertTrue(app(AntenatalSchedule::class)->iptpStatus($patient->refresh())['is_complete']);
    }

    public function test_iptp_is_refused_in_the_first_trimester(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        // Sulfadoxine-pyrimethamine is not given before 13 weeks.
        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 10,
            'iptp_given' => 1,
        ])->assertSessionHasErrors('iptp_given');

        $this->assertDatabaseCount('anc_assessments', 0);
    }

    public function test_iptp_is_refused_when_the_gestational_age_is_unknown(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        // An unrecorded gestation cannot be assumed to be past the first trimester.
        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'iptp_given' => 1,
        ])->assertSessionHasErrors('iptp_given');
    }

    public function test_iptp_doses_closer_than_a_month_apart_are_refused(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 16,
            'iptp_given' => 1,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 17,
            'iptp_given' => 1,
        ])->assertSessionHasErrors('iptp_given');

        $this->assertSame(1, AncAssessment::whereNotNull('iptp_dose_number')->count());
    }

    public function test_tetanus_doses_stop_at_the_fifth(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        for ($dose = 1; $dose <= AntenatalSchedule::TT_MAX_DOSES; $dose++) {
            $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", ['tt_given' => 1])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", ['tt_given' => 1])
            ->assertSessionHasErrors('tt_given');

        $this->assertSame(
            AntenatalSchedule::TT_MAX_DOSES,
            AncAssessment::whereNotNull('tt_dose_number')->count()
        );
    }

    public function test_the_antenatal_record_lists_the_outstanding_booking_profile(): void
    {
        $patient = Patient::factory()->female()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id, 'created_by' => $this->nurse->id]);

        $this->actingAs($this->nurse)->post("/visits/{$visit->id}/anc", [
            'gestational_age_weeks' => 14,
            'haemoglobin' => 11.5,
            'blood_group' => 'O',
            'rhesus' => 'negative',
        ]);

        $response = $this->actingAs($this->nurse)->get("/patients/{$patient->id}/anc");

        $response->assertOk();
        $response->assertSee('Booking profile outstanding');
        $response->assertSee('Syphilis (VDRL)');
        // Rhesus negative needs anti-D, so it must surface as a danger sign.
        $this->assertContains(
            'Rhesus negative — anti-D prophylaxis and partner typing needed',
            AncAssessment::where('patient_id', $patient->id)->sole()->dangerSigns()
        );
    }

    public function test_specialty_records_are_closed_to_users_without_the_permission(): void
    {
        $patient = $this->child();
        $other = User::factory()->create();

        $this->actingAs($other)->get("/patients/{$patient->id}/paediatrics")->assertForbidden();
        $this->actingAs($other)->post("/patients/{$patient->id}/growth", ['weight_kg' => 10])->assertForbidden();
    }
}
