<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\NhisExemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ghana exempts pregnant women and under-fives at the point of service, and
 * NHIA reimburses at the G-DRG tariff rather than at what the facility charges.
 */
class NhisExemptionAndTariffTest extends TestCase
{
    use RefreshDatabase;

    protected function visitFor(Patient $patient, string $paymentMethod = 'cash'): Visit
    {
        return Visit::factory()->create([
            'patient_id' => $patient->id,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function test_a_child_under_five_is_flagged_as_exempt(): void
    {
        $child = Patient::factory()->child(2)->create();
        $visit = $this->visitFor($child);

        $item = Invoice::addCharge($visit, 'Malaria RDT', 'Lab', 15.0, false);

        $this->assertTrue($item->is_exempt);
        $this->assertSame('Child under five', $item->exemption_reason);
        $this->assertEquals(15.0, $item->total_price);
        // `payer` is untouched so existing claim queries keep working.
        $this->assertSame('cash', $item->payer);
        // Exempt from NHIS premiums is not the same as free at the desk, so the
        // charge stands until the facility says otherwise.
        $this->assertEquals(15.0, $item->patient_amount);
    }

    public function test_a_facility_can_switch_on_waiving_for_exempt_groups(): void
    {
        config(['billing.waive_exempt_charges' => true]);

        $child = Patient::factory()->child(2)->create();
        $visit = $this->visitFor($child);

        $item = Invoice::addCharge($visit, 'Malaria RDT', 'Lab', 15.0, false);

        $this->assertTrue($item->is_exempt);
        $this->assertEquals(0, $item->patient_amount);
        // What the facility gave is still recorded, even when nobody pays for it.
        $this->assertEquals(15.0, $item->total_price);
        $this->assertEquals(0, $visit->invoice->refresh()->balanceDue());
    }

    public function test_a_pregnant_woman_is_exempt(): void
    {
        $mother = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(28)->toDateString()]);
        $visit = $this->visitFor($mother);

        Consultation::factory()->create([
            'visit_id' => $visit->id,
            'pregnancy_status' => 'pregnant',
        ]);

        $item = Invoice::addCharge($visit, 'Haemoglobin', 'Lab', 15.0, false);

        $this->assertTrue($item->is_exempt);
        $this->assertSame('Pregnant woman', $item->exemption_reason);
        $this->assertSame(NhisExemption::PREGNANT, $visit->refresh()->exemption_category);
    }

    public function test_an_ordinary_adult_is_not_exempt(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);

        $item = Invoice::addCharge($visit, 'Malaria RDT', 'Lab', 15.0, false);

        $this->assertFalse($item->is_exempt);
        $this->assertEquals(15.0, $item->patient_amount);
    }

    public function test_pregnancy_is_never_assumed_from_age_and_sex_alone(): void
    {
        $woman = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(28)->toDateString()]);
        $visit = $this->visitFor($woman);

        $this->assertNull(app(NhisExemption::class)->categoryFor($visit));
    }

    public function test_an_exemption_recorded_by_a_clerk_is_not_overwritten(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);
        // Indigent status comes from documents the patient brings, not the record.
        $visit->update(['exemption_category' => NhisExemption::INDIGENT]);

        Invoice::addCharge($visit, 'Consultation', 'Consultation', 20.0, false);

        $this->assertSame(NhisExemption::INDIGENT, $visit->refresh()->exemption_category);
    }

    public function test_an_nhis_line_is_claimed_at_the_tariff_not_the_facility_price(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult, 'nhis');

        // The facility charges 40; NHIA reimburses 28.
        $item = Invoice::addCharge($visit, 'Full Blood Count', 'Lab', 40.0, true, 1, 28.0);

        $this->assertSame('nhis', $item->payer);
        $this->assertEquals(28.0, $item->nhis_amount);
        $this->assertEquals(40.0, $item->total_price);
    }

    public function test_a_missing_tariff_falls_back_to_the_facility_price(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult, 'nhis');

        // A claim must never be silently valued at zero.
        $item = Invoice::addCharge($visit, 'Full Blood Count', 'Lab', 40.0, true);

        $this->assertEquals(40.0, $item->nhis_amount);
    }

    public function test_a_pending_payment_does_not_mark_an_invoice_paid(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);
        $item = Invoice::addCharge($visit, 'Consultation', 'Consultation', 50.0, false);
        $invoice = $item->invoice;

        // Mobile money is approved on the handset afterwards, so a pending row
        // is not money in hand.
        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-PENDING-1',
            'amount' => 50.0,
            'method' => 'mobile_money',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mtn',
            'received_by' => User::factory()->create()->id,
        ]);

        $invoice->recalculateTotals();

        $this->assertEquals(0, $invoice->amount_paid);
        $this->assertEquals(50.0, $invoice->balanceDue());
        $this->assertSame('open', $invoice->status);
    }

    public function test_a_payment_settles_the_invoice_once_it_succeeds(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);
        $invoice = Invoice::addCharge($visit, 'Consultation', 'Consultation', 50.0, false)->invoice;

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-PENDING-2',
            'amount' => 50.0,
            'method' => 'mobile_money',
            'status' => Payment::STATUS_PENDING,
            'received_by' => User::factory()->create()->id,
        ]);

        $payment->update(['status' => Payment::STATUS_SUCCESSFUL, 'confirmed_at' => now()]);
        $invoice->recalculateTotals();

        $this->assertEquals(50.0, $invoice->amount_paid);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_a_failed_payment_never_counts(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);
        $invoice = Invoice::addCharge($visit, 'Consultation', 'Consultation', 50.0, false)->invoice;

        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-FAILED-1',
            'amount' => 50.0,
            'method' => 'mobile_money',
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'Insufficient balance',
            'received_by' => User::factory()->create()->id,
        ]);

        $invoice->recalculateTotals();

        $this->assertEquals(0, $invoice->amount_paid);
    }

    public function test_a_cash_payment_still_settles_immediately(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(35)->toDateString()]);
        $visit = $this->visitFor($adult);
        $invoice = Invoice::addCharge($visit, 'Consultation', 'Consultation', 50.0, false)->invoice;

        // Cash across the desk defaults to successful, so existing behaviour holds.
        Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-CASH-1',
            'amount' => 50.0,
            'method' => 'cash',
            'received_by' => User::factory()->create()->id,
        ]);

        $invoice->recalculateTotals();

        $this->assertEquals(50.0, $invoice->amount_paid);
    }
}
