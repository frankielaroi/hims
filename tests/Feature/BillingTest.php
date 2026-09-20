<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $billingPerm = Permission::updateOrCreate(['slug' => 'manage_billing'], ['name' => 'Manage Billing']);
        $this->cashierRole = Role::create(['name' => 'Cashier', 'slug' => 'cashier']);
        $this->cashierRole->permissions()->sync([$billingPerm->id]);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach($this->cashierRole);

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);
    }

    protected function makeVisit(string $paymentMethod): Visit
    {
        return Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-'.uniqid(),
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => $paymentMethod,
            'created_by' => $this->cashier->id,
            'status' => 'waiting_triage',
        ]);
    }

    public function test_nhis_covered_charge_on_an_nhis_visit_is_billed_to_nhis()
    {
        $visit = $this->makeVisit('nhis');

        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 50.00, true);

        $invoice = $visit->invoice;
        $this->assertEquals(50.00, $invoice->nhis_amount);
        $this->assertEquals(0.00, $invoice->patient_amount);
    }

    public function test_non_covered_charge_on_an_nhis_visit_still_falls_to_the_patient()
    {
        // Mixed payment case from the design doc: one NHIS visit can still owe cash for a non-covered item.
        $visit = $this->makeVisit('nhis');

        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 50.00, true);
        Invoice::addCharge($visit, 'Insulin Glargine', 'Pharmacy', 120.00, false);

        $invoice = $visit->invoice;
        $this->assertEquals(50.00, $invoice->nhis_amount);
        $this->assertEquals(120.00, $invoice->patient_amount);
        $this->assertEquals(170.00, $invoice->subtotal);
    }

    public function test_cash_visit_pays_for_everything_regardless_of_nhis_coverage_flag()
    {
        $visit = $this->makeVisit('cash');

        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 50.00, true);

        $invoice = $visit->invoice;
        $this->assertEquals(0.00, $invoice->nhis_amount);
        $this->assertEquals(50.00, $invoice->patient_amount);
    }

    public function test_cashier_can_record_a_partial_then_full_payment()
    {
        $visit = $this->makeVisit('cash');
        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 100.00, false);
        $invoice = $visit->invoice;

        $this->actingAs($this->cashier)->post("/billing/{$invoice->id}/payments", [
            'amount' => 40,
            'method' => 'cash',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('partially_paid', $invoice->status);
        $this->assertEquals(60.00, $invoice->balanceDue());

        $this->actingAs($this->cashier)->post("/billing/{$invoice->id}/payments", [
            'amount' => 60,
            'method' => 'mobile_money',
            'reference_number' => 'MOMO-123',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, $invoice->balanceDue());
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_cannot_pay_more_than_the_balance_due()
    {
        $visit = $this->makeVisit('cash');
        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 100.00, false);
        $invoice = $visit->invoice;

        $response = $this->actingAs($this->cashier)->post("/billing/{$invoice->id}/payments", [
            'amount' => 150,
            'method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_finalizing_a_consultation_charges_the_consultation_fee()
    {
        Service::create(['name' => 'General Consultation', 'category' => 'Consultation', 'price' => 50, 'is_nhis_covered' => true, 'code' => 'CONS-GEN']);

        $doctorRole = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $doctor = User::factory()->create();
        $doctor->roles()->attach($doctorRole);

        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);

        $visit = $this->makeVisit('cash');
        $visit->update(['status' => 'waiting_doctor']);

        $this->actingAs($doctor)->post("/consultations/{$visit->id}", [
            'clinical_notes' => 'Fever.',
            'diagnoses' => [['code' => 'B54', 'description' => 'Malaria', 'is_primary' => true]],
            'outcome' => 'discharge',
        ]);

        $invoice = $visit->fresh()->invoice;
        $this->assertNotNull($invoice);
        $this->assertEquals(50.00, $invoice->patient_amount);
        $this->assertDatabaseHas('invoice_items', ['description' => 'General Consultation', 'category' => 'Consultation']);
    }
}
