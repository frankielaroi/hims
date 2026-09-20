<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NhisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $claimsPerm = Permission::updateOrCreate(['slug' => 'manage_nhis_claims'], ['name' => 'Manage NHIS Claims']);
        $this->cashierRole = Role::create(['name' => 'Cashier', 'slug' => 'cashier']);
        $this->cashierRole->permissions()->sync([$claimsPerm->id]);

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach($this->cashierRole);

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create([
            'full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001',
            'payment_method' => 'nhis', 'nhis_number' => 'NHIS-001', 'nhis_expiry' => now()->addYear(),
        ]);
    }

    protected function makeVerifiedNhisInvoice(bool $verified = true): Invoice
    {
        $visit = Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-'.uniqid(),
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'nhis',
            'nhis_verified' => $verified,
            'created_by' => $this->cashier->id,
            'status' => 'waiting_triage',
        ]);

        Invoice::addCharge($visit, 'General Consultation', 'Consultation', 50.00, true);

        return $visit->invoice;
    }

    public function test_cannot_generate_claim_for_unverified_nhis_visit()
    {
        $invoice = $this->makeVerifiedNhisInvoice(verified: false);

        $response = $this->actingAs($this->cashier)->post("/nhis/invoices/{$invoice->id}/claim");

        $response->assertStatus(422);
        $this->assertDatabaseCount('nhis_claims', 0);
    }

    public function test_can_generate_claim_once_verified()
    {
        $invoice = $this->makeVerifiedNhisInvoice();

        $response = $this->actingAs($this->cashier)->post("/nhis/invoices/{$invoice->id}/claim");

        $response->assertRedirect();
        $this->assertDatabaseHas('nhis_claims', ['invoice_id' => $invoice->id, 'status' => 'draft', 'amount_claimed' => 50.00]);
        $this->assertDatabaseHas('nhis_claim_items', ['description' => 'General Consultation', 'amount' => 50.00]);
    }

    public function test_claim_moves_through_the_full_lifecycle()
    {
        $invoice = $this->makeVerifiedNhisInvoice();
        $this->actingAs($this->cashier)->post("/nhis/invoices/{$invoice->id}/claim");
        $claim = $invoice->fresh()->claim;

        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/submit")->assertRedirect();
        $this->assertEquals('submitted', $claim->fresh()->status);

        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/vet")->assertRedirect();
        $this->assertEquals('vetted', $claim->fresh()->status);

        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/pay")->assertRedirect();
        $claim->refresh();
        $this->assertEquals('paid', $claim->status);
        $this->assertEquals(50.00, $claim->amount_paid);
        $this->assertNotNull($claim->paid_at);
    }

    public function test_cannot_mark_a_draft_claim_as_paid()
    {
        $invoice = $this->makeVerifiedNhisInvoice();
        $this->actingAs($this->cashier)->post("/nhis/invoices/{$invoice->id}/claim");
        $claim = $invoice->fresh()->claim;

        $response = $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/pay");

        $response->assertStatus(422);
        $this->assertEquals('draft', $claim->fresh()->status);
    }

    public function test_rejected_claim_can_be_resubmitted()
    {
        $invoice = $this->makeVerifiedNhisInvoice();
        $this->actingAs($this->cashier)->post("/nhis/invoices/{$invoice->id}/claim");
        $claim = $invoice->fresh()->claim;
        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/submit");

        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/reject", ['reason' => 'Missing NHIS card scan'])->assertRedirect();
        $claim->refresh();
        $this->assertEquals('rejected', $claim->status);
        $this->assertEquals('Missing NHIS card scan', $claim->rejected_reason);

        $this->actingAs($this->cashier)->post("/nhis/{$claim->id}/resubmit")->assertRedirect();
        $claim->refresh();
        $this->assertEquals('submitted', $claim->status);
        $this->assertNull($claim->rejected_reason);
    }
}
