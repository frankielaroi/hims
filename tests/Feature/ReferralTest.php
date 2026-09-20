<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $referralPerm = Permission::updateOrCreate(['slug' => 'manage_referrals'], ['name' => 'Manage Referrals']);
        $this->doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $this->doctorRole->permissions()->sync([$referralPerm->id]);

        $this->opd = Department::create(['name' => 'General OPD', 'slug' => 'opd-general']);
        $this->surgery = Department::create(['name' => 'Surgery', 'slug' => 'surgery']);

        $this->doctor = User::factory()->create(['department_id' => $this->opd->id]);
        $this->doctor->roles()->attach($this->doctorRole);

        $this->surgeon = User::factory()->create(['department_id' => $this->surgery->id]);
        $this->surgeon->roles()->attach($this->doctorRole);

        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);
        $this->patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);

        $this->visit = Visit::create([
            'patient_id' => $this->patient->id, 'department_id' => $this->opd->id, 'visit_number' => 'V-001',
            'queue_number' => 1, 'type' => 'OPD', 'payment_method' => 'cash', 'created_by' => $this->doctor->id,
            'status' => 'waiting_doctor',
        ]);
    }

    protected function finalizeAsReferral(array $extra = []): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", array_merge([
            'clinical_notes' => 'Needs specialist input.',
            'diagnoses' => [['code' => 'B54', 'description' => 'Complicated case', 'is_primary' => true]],
            'outcome' => 'refer',
            'outcome_notes' => 'Suspected appendicitis',
        ], $extra));
    }

    public function test_internal_referral_is_created_on_consultation_finalize()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'internal',
            'referral_to_department_id' => $this->surgery->id,
            'referral_urgency' => 'urgent',
        ]);

        $this->assertDatabaseHas('referrals', [
            'visit_id' => $this->visit->id,
            'type' => 'internal',
            'to_department_id' => $this->surgery->id,
            'status' => 'pending',
            'urgency' => 'urgent',
        ]);

        $this->assertEquals('completed', $this->visit->fresh()->status);
    }

    public function test_external_referral_records_the_facility_name()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'external',
            'referral_external_facility_name' => 'Korle-Bu Teaching Hospital',
        ]);

        $this->assertDatabaseHas('referrals', [
            'visit_id' => $this->visit->id,
            'type' => 'external',
            'external_facility_name' => 'Korle-Bu Teaching Hospital',
        ]);
    }

    public function test_receiving_department_can_accept_an_internal_referral_opening_a_new_visit()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'internal',
            'referral_to_department_id' => $this->surgery->id,
        ]);
        $referral = Referral::first();

        $response = $this->actingAs($this->surgeon)->post("/referrals/{$referral->id}/accept");

        $response->assertRedirect();
        $referral->refresh();
        $this->assertEquals('accepted', $referral->status);
        $this->assertNotNull($referral->new_visit_id);

        $newVisit = Visit::find($referral->new_visit_id);
        $this->assertEquals($this->surgery->id, $newVisit->department_id);
        $this->assertEquals('waiting_doctor', $newVisit->status);
        $this->assertEquals($this->patient->id, $newVisit->patient_id);
    }

    public function test_receiving_department_can_decline_with_a_reason()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'internal',
            'referral_to_department_id' => $this->surgery->id,
        ]);
        $referral = Referral::first();

        $response = $this->actingAs($this->surgeon)->post("/referrals/{$referral->id}/decline", [
            'feedback_notes' => 'Patient should go to Medicine, not Surgery.',
        ]);

        $response->assertRedirect();
        $referral->refresh();
        $this->assertEquals('declined', $referral->status);
        $this->assertEquals('Patient should go to Medicine, not Surgery.', $referral->feedback_notes);
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_incoming_referrals_are_scoped_to_the_viewers_department()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'internal',
            'referral_to_department_id' => $this->surgery->id,
        ]);

        $response = $this->actingAs($this->surgeon)->get('/referrals');
        $response->assertSee('Test Patient');

        $response = $this->actingAs($this->doctor)->get('/referrals');
        $response->assertDontSee('Incoming, waiting on you');
    }

    public function test_referral_note_can_be_printed()
    {
        $this->finalizeAsReferral([
            'referral_type' => 'external',
            'referral_external_facility_name' => 'Korle-Bu Teaching Hospital',
        ]);
        $referral = Referral::first();

        $response = $this->actingAs($this->doctor)->get("/referrals/{$referral->id}/print");

        $response->assertOk();
        $response->assertSee('Korle-Bu Teaching Hospital');
        $response->assertSee($this->patient->hospital_id);
    }
}
