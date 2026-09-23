<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHO and the Ghana STG require a first dose before a severely ill patient
 * travels. The journey to a referral hospital can take hours, so the receiving
 * facility has to know what was already given and when.
 */
class PreReferralTreatmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $clinician;

    protected Referral $referral;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $role->permissions()->sync([
            Permission::updateOrCreate(['slug' => 'manage_referrals'], ['name' => 'manage_referrals'])->id,
            Permission::updateOrCreate(['slug' => 'view_clinical_history'], ['name' => 'view_clinical_history'])->id,
        ]);

        $this->clinician = User::factory()->create();
        $this->clinician->roles()->attach($role);

        $department = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);

        $this->referral = Referral::create([
            'visit_id' => $visit->id,
            'type' => 'external',
            'from_department_id' => $department->id,
            'external_facility_name' => 'Regional Hospital',
            'reason' => 'Severe malaria with impaired consciousness.',
            'urgency' => 'emergency',
            'status' => 'pending',
            'referred_by' => $this->clinician->id,
        ]);
    }

    public function test_an_urgent_referral_without_pre_referral_treatment_is_flagged(): void
    {
        $this->assertTrue($this->referral->needsPreReferralRecord());
    }

    public function test_a_routine_referral_is_not_chased_for_it(): void
    {
        $this->referral->update(['urgency' => 'routine']);

        // A routine outpatient referral is not travelling with an untreated emergency.
        $this->assertFalse($this->referral->fresh()->needsPreReferralRecord());
    }

    public function test_recording_the_first_dose_names_what_was_given_and_by_whom(): void
    {
        $this->actingAs($this->clinician)
            ->post("/referrals/{$this->referral->id}/pre-referral-treatment", [
                'pre_referral_treatments' => ['artesunate', 'glucose'],
                'pre_referral_notes' => 'Rectal artesunate 10mg/kg given at 14:05.',
            ])->assertSessionHasNoErrors();

        $referral = $this->referral->fresh();

        $this->assertSame(['artesunate', 'glucose'], $referral->pre_referral_treatments);
        $this->assertSame($this->clinician->id, $referral->pre_referral_given_by);
        $this->assertNotNull($referral->pre_referral_given_at);
        $this->assertFalse($referral->needsPreReferralRecord());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pre_referral_treatment_recorded']);
    }

    public function test_an_unknown_treatment_code_is_refused(): void
    {
        $this->actingAs($this->clinician)
            ->post("/referrals/{$this->referral->id}/pre-referral-treatment", [
                'pre_referral_treatments' => ['something_invented'],
            ])->assertSessionHasErrors('pre_referral_treatments.0');
    }

    public function test_the_referral_letter_carries_the_treatment_given(): void
    {
        $this->actingAs($this->clinician)
            ->post("/referrals/{$this->referral->id}/pre-referral-treatment", [
                'pre_referral_treatments' => ['artesunate'],
            ]);

        $this->actingAs($this->clinician)->get("/referrals/{$this->referral->id}/print")
            ->assertOk()
            ->assertSee('Treatment given before referral')
            ->assertSee('Rectal or injectable artesunate (severe malaria)');
    }

    public function test_the_letter_says_plainly_when_nothing_was_recorded(): void
    {
        $this->actingAs($this->clinician)->get("/referrals/{$this->referral->id}/print")
            ->assertOk()
            ->assertSee('NOT RECORDED');
    }
}
