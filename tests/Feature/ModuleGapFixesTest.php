<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Department;
use App\Models\GrowthMeasurement;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Rules\GhanaCardPin;
use App\Services\ImmunizationSchedule;
use App\Services\NhisExemption;
use Database\Seeders\DrugsSeeder;
use Database\Seeders\LabTestsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The remaining gaps from the module audit, gathered where they do not belong
 * to a module with a test file of its own.
 */
class ModuleGapFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $slugs
     */
    protected function userWith(string $roleSlug, array $slugs): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);
        $role->permissions()->sync(
            collect($slugs)
                ->map(fn (string $slug) => Permission::updateOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    // ---- Ghana Card ----------------------------------------------------

    public function test_a_ghana_card_pin_is_normalised_from_how_it_is_read_off_the_card(): void
    {
        $this->assertSame('GHA-123456789-0', GhanaCardPin::normalise('1234567890'));
        $this->assertSame('GHA-123456789-0', GhanaCardPin::normalise(' gha-123456789-0 '));
        $this->assertNull(GhanaCardPin::normalise(''));
    }

    public function test_a_malformed_ghana_card_pin_is_refused(): void
    {
        $clerk = $this->userWith('receptionist', ['register_patients', 'view_patients']);

        $this->actingAs($clerk)->post('/patients', [
            'full_name' => 'Kwame Asare',
            'sex' => 'male',
            'estimated_age' => 30,
            'payment_method' => 'cash',
            'ghana_card_pin' => 'GHA-123',
            'confirm_duplicate' => 1,
        ])->assertSessionHasErrors('ghana_card_pin');
    }

    // ---- Staff licence -------------------------------------------------

    public function test_an_expired_staff_licence_is_visible(): void
    {
        $current = User::factory()->create([
            'licence_body' => 'nmc',
            'licence_number' => 'NMC-1234',
            'licence_expires_on' => now()->addYear(),
        ]);

        $expired = User::factory()->create([
            'licence_body' => 'ahpc',
            'licence_expires_on' => now()->subDay(),
        ]);

        // A record with no expiry recorded is not treated as expired.
        $unknown = User::factory()->create(['licence_expires_on' => null]);

        $this->assertFalse($current->licenceHasExpired());
        $this->assertTrue($expired->licenceHasExpired());
        $this->assertFalse($unknown->licenceHasExpired());
        $this->assertSame('Nursing and Midwifery Council', $current->licenceBodyName());
    }

    // ---- Payment settlement -------------------------------------------

    public function test_a_pending_payment_does_not_count_towards_the_invoice(): void
    {
        $visit = Visit::factory()->create();
        Invoice::addCharge($visit, 'Consultation', 'Consultation', 100, false);
        $invoice = Invoice::where('visit_id', $visit->id)->sole();

        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-PENDING',
            'amount' => 100,
            'method' => 'mobile_money',
            'status' => Payment::STATUS_PENDING,
            'received_by' => User::factory()->create()->id,
        ]);

        $invoice->recalculateTotals();

        // The patient has not approved it on their handset yet.
        $this->assertEquals(0.0, $invoice->amount_paid);
        $this->assertSame('open', $invoice->status);
    }

    public function test_confirming_a_pending_payment_settles_the_invoice(): void
    {
        $cashier = $this->userWith('cashier', ['manage_billing', 'view_patients']);
        $visit = Visit::factory()->create();
        Invoice::addCharge($visit, 'Consultation', 'Consultation', 100, false);
        $invoice = Invoice::where('visit_id', $visit->id)->sole();

        $this->actingAs($cashier)->post("/billing/{$invoice->id}/payments", [
            'amount' => 100,
            'method' => 'mobile_money',
            'awaiting_confirmation' => 1,
        ])->assertSessionHas('warning');

        $payment = Payment::where('invoice_id', $invoice->id)->sole();
        $this->assertTrue($payment->isPending());
        $this->assertEquals(0.0, $invoice->refresh()->amount_paid);

        $this->actingAs($cashier)->post("/billing/payments/{$payment->id}/settle", [
            'outcome' => Payment::STATUS_SUCCESSFUL,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(100.0, $invoice->refresh()->amount_paid);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_a_failed_payment_leaves_the_balance_untouched(): void
    {
        $cashier = $this->userWith('cashier', ['manage_billing', 'view_patients']);
        $visit = Visit::factory()->create();
        Invoice::addCharge($visit, 'Consultation', 'Consultation', 100, false);
        $invoice = Invoice::where('visit_id', $visit->id)->sole();

        $this->actingAs($cashier)->post("/billing/{$invoice->id}/payments", [
            'amount' => 100,
            'method' => 'mobile_money',
            'awaiting_confirmation' => 1,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->sole();

        $this->actingAs($cashier)->post("/billing/payments/{$payment->id}/settle", [
            'outcome' => Payment::STATUS_FAILED,
            'failure_reason' => 'Insufficient balance',
        ])->assertSessionHas('warning');

        $this->assertEquals(0.0, $invoice->refresh()->amount_paid);
        $this->assertEquals(100.0, $invoice->balanceDue());
    }

    // ---- NHIS exemptions ----------------------------------------------

    public function test_a_child_under_five_is_flagged_as_exempt(): void
    {
        $child = Patient::factory()->child(2)->create(['payment_method' => 'cash']);
        $visit = Visit::factory()->create(['patient_id' => $child->id, 'payment_method' => 'cash']);

        $item = Invoice::addCharge($visit, 'Malaria RDT', 'Lab', 15, false);

        $this->assertTrue($item->is_exempt);
        $this->assertSame('Child under five', $item->exemption_reason);

        // The exempt groups are exempt from NHIS PREMIUMS and covered by the
        // scheme; that is not the same as every service being free at the desk.
        // This line is cash precisely because NHIS is not paying for it, so
        // zeroing it would mean the facility is never paid at all. Waiving is a
        // facility policy, switched on in config/billing.php.
        $this->assertEquals(15.0, $item->patient_amount);

        config(['billing.waive_exempt_charges' => true]);
        $waived = Invoice::addCharge($visit, 'Malaria RDT', 'Lab', 15, false);
        $this->assertEquals(0.0, $waived->patient_amount);
    }

    public function test_pregnancy_recorded_at_the_visit_exempts_the_mother(): void
    {
        $mother = Patient::factory()->female()->create(['payment_method' => 'cash', 'date_of_birth' => now()->subYears(28)]);
        $visit = Visit::factory()->create(['patient_id' => $mother->id, 'payment_method' => 'cash']);
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        $item = Invoice::addCharge($visit, 'Haemoglobin', 'Lab', 15, false);

        $this->assertTrue($item->is_exempt);
        $this->assertSame('Pregnant woman', $item->exemption_reason);
    }

    public function test_an_ordinary_adult_is_not_exempt(): void
    {
        $adult = Patient::factory()->create(['payment_method' => 'cash', 'date_of_birth' => now()->subYears(35)]);
        $visit = Visit::factory()->create(['patient_id' => $adult->id, 'payment_method' => 'cash']);

        $item = Invoice::addCharge($visit, 'Consultation', 'Consultation', 30, false);

        $this->assertFalse($item->is_exempt);
        $this->assertEquals(30.0, $item->patient_amount);
    }

    public function test_the_system_never_assumes_a_woman_is_pregnant(): void
    {
        $woman = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(28)]);
        $visit = Visit::factory()->create(['patient_id' => $woman->id]);

        $this->assertNull(app(NhisExemption::class)->categoryFor($visit));
    }

    // ---- NHIS tariff ---------------------------------------------------

    public function test_a_lab_test_claims_its_tariff_rather_than_the_facility_price(): void
    {
        $withTariff = LabTest::create([
            'name' => 'Tariffed Test', 'code' => 'TAR-1', 'specimen_type' => 'Blood',
            'price' => 80, 'nhis_tariff' => 45, 'is_nhis_covered' => true,
        ]);

        $withoutTariff = LabTest::create([
            'name' => 'Untariffed Test', 'code' => 'TAR-2', 'specimen_type' => 'Blood',
            'price' => 80, 'is_nhis_covered' => true,
        ]);

        // Claiming the facility price when a G-DRG tariff exists is how claims
        // fail to reconcile with NHIA.
        $this->assertEquals(45.0, $withTariff->reimbursableAmount());
        $this->assertEquals(80.0, $withoutTariff->reimbursableAmount());
    }

    // ---- Radiology radiation safety -----------------------------------

    public function test_an_xray_on_a_pregnant_patient_needs_a_written_reason(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $visit = Visit::factory()->create();
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Cough',
            'priority' => 'routine',
        ])->assertSessionHasErrors('pregnancy_justification');

        $this->assertDatabaseCount('radiology_orders', 0);
    }

    public function test_an_ultrasound_on_a_pregnant_patient_is_unimpeded(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $visit = Visit::factory()->create();
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        // Ultrasound carries no fetal dose, so there is nothing to justify.
        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'ultrasound',
            'body_part' => 'Abdomen',
            'clinical_indication' => 'Fetal growth',
            'priority' => 'routine',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('radiology_orders', 1);
    }

    public function test_a_justified_xray_in_pregnancy_goes_through(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $visit = Visit::factory()->create();
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Suspected pulmonary tuberculosis',
            'priority' => 'urgent',
            'pregnancy_justification' => 'Suspected TB with haemoptysis; abdominal shielding applied.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('radiology_orders', 1);
    }

    // ---- Paediatric growth and EPI ------------------------------------

    public function test_weight_loss_between_visits_is_flagged_as_faltering_growth(): void
    {
        $child = Patient::factory()->child(1)->create();

        GrowthMeasurement::create([
            'patient_id' => $child->id, 'measured_at' => now()->subDays(30),
            'age_months' => 11, 'weight_kg' => 9.0, 'recorded_by' => User::factory()->create()->id,
        ]);

        $latest = GrowthMeasurement::create([
            'patient_id' => $child->id, 'measured_at' => now(),
            'age_months' => 12, 'weight_kg' => 8.4, 'recorded_by' => User::factory()->create()->id,
        ]);

        $this->assertSame('losing_weight', $latest->growthTrend()['grade']);
        $this->assertStringContainsString('Faltering growth', implode(' ', $latest->alerts()));
    }

    public function test_two_weighings_days_apart_are_not_compared(): void
    {
        $child = Patient::factory()->child(1)->create();
        $recorder = User::factory()->create()->id;

        GrowthMeasurement::create([
            'patient_id' => $child->id, 'measured_at' => now()->subDays(3),
            'age_months' => 12, 'weight_kg' => 9.0, 'recorded_by' => $recorder,
        ]);

        $latest = GrowthMeasurement::create([
            'patient_id' => $child->id, 'measured_at' => now(),
            'age_months' => 12, 'weight_kg' => 8.9, 'recorded_by' => $recorder,
        ]);

        // Over three days this says more about the scale than about growth.
        $this->assertNull($latest->growthTrend());
    }

    public function test_the_epi_schedule_carries_vitamin_a_and_the_malaria_vaccine(): void
    {
        $codes = collect(ImmunizationSchedule::SCHEDULE)->pluck('code')->unique();

        $this->assertTrue($codes->contains('VITA'), 'Vitamin A supplementation is missing');
        $this->assertTrue($codes->contains('MAL'), 'The malaria vaccine is missing');
    }

    // ---- Walk-in laboratory visits ------------------------------------

    public function test_a_lab_walk_in_opens_a_direct_lab_visit_not_a_triage_queue_entry(): void
    {
        $clinician = $this->userWith('doctor', ['order_lab_tests', 'view_clinical_history']);
        $patient = Patient::factory()->create();
        Department::create(['name' => 'General OPD', 'slug' => 'opd-general']);
        $test = LabTest::create(['name' => 'Malaria RDT', 'code' => 'MP-W', 'specimen_type' => 'Blood', 'price' => 15]);

        $this->actingAs($clinician)->post('/lab', [
            'patient_id' => $patient->id,
            'test_ids' => [$test->id],
            'priority' => 'routine',
        ])->assertRedirect();

        $visit = Visit::where('patient_id', $patient->id)->sole();

        // A walk-in never sees triage, so parking them in that queue strands them.
        $this->assertSame(Visit::TYPE_DIRECT_LAB, $visit->type);
        $this->assertSame('waiting_lab', $visit->status);
    }

    // ---- Formulary and catalogue --------------------------------------

    public function test_the_formulary_carries_the_obstetric_emergency_drugs(): void
    {
        $this->seed(DrugsSeeder::class);

        foreach (['Oxytocin', 'Magnesium Sulphate', 'Misoprostol'] as $name) {
            $this->assertDatabaseHas('drugs', ['name' => $name]);
        }
    }

    public function test_the_lab_catalogue_carries_the_antenatal_profile(): void
    {
        $this->seed(LabTestsSeeder::class);

        foreach (['ABO-RH', 'VDRL', 'SICKLING', 'HB', 'G6PD'] as $code) {
            $this->assertDatabaseHas('lab_tests', ['code' => $code]);
        }
    }
}
