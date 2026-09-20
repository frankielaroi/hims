<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $dispensePerm = Permission::updateOrCreate(['slug' => 'dispense_medication'], ['name' => 'Dispense Medication']);
        $this->pharmacistRole = Role::create(['name' => 'Pharmacist', 'slug' => 'pharmacist']);
        $this->pharmacistRole->permissions()->sync([$dispensePerm->id]);

        $this->pharmacist = User::factory()->create();
        $this->pharmacist->roles()->attach($this->pharmacistRole);

        $this->doctor = User::factory()->create();

        $this->dept = Department::create(['name' => 'OPD', 'slug' => 'opd']);
        $this->patient = Patient::create(['full_name' => 'Test Patient', 'sex' => 'male', 'hospital_id' => 'P-001', 'payment_method' => 'cash']);

        $this->visit = Visit::create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->dept->id,
            'visit_number' => 'V-001',
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->doctor->id,
            'status' => 'waiting_pharmacy',
        ]);

        $this->drug = Drug::create([
            'name' => 'Paracetamol', 'generic_name' => 'Acetaminophen', 'strength' => '500mg',
            'formulation' => 'Tablet', 'is_nhis_covered' => true, 'stock_level' => 30,
        ]);

        // Near-expiry batch should be drawn from first (FEFO).
        $this->expiringBatch = DrugBatch::create([
            'drug_id' => $this->drug->id, 'batch_number' => 'B-EXP', 'expiry_date' => now()->addDays(10),
            'quantity_received' => 10, 'quantity_remaining' => 10, 'received_at' => now()->subDays(60),
        ]);
        DrugBatch::create([
            'drug_id' => $this->drug->id, 'batch_number' => 'B-FRESH', 'expiry_date' => now()->addDays(300),
            'quantity_received' => 20, 'quantity_remaining' => 20, 'received_at' => now()->subDays(5),
        ]);

        $this->prescription = Prescription::create([
            'visit_id' => $this->visit->id, 'doctor_id' => $this->doctor->id, 'status' => 'active',
        ]);

        $this->item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id, 'drug_id' => $this->drug->id,
            'dose' => '500mg', 'route' => 'Oral', 'frequency' => 'TDS', 'duration_days' => 7,
            'quantity_to_dispense' => 21, 'nhis_covered_snapshot' => true,
        ]);
    }

    public function test_pharmacist_can_partially_dispense_and_draws_from_expiring_batch_first()
    {
        $response = $this->actingAs($this->pharmacist)
            ->post("/pharmacy/{$this->prescription->id}/items/{$this->item->id}/dispense", ['quantity' => 12]);

        $response->assertRedirect();
        $this->item->refresh();
        $this->assertEquals(12, $this->item->quantity_dispensed);

        $this->drug->refresh();
        $this->assertEquals(18, $this->drug->stock_level);

        // 10 from the expiring batch first, then 2 from the fresh batch.
        $this->expiringBatch->refresh();
        $this->assertEquals(0, $this->expiringBatch->quantity_remaining);
        $this->assertDatabaseHas('stock_movements', [
            'drug_batch_id' => $this->expiringBatch->id, 'quantity_change' => -10, 'movement_type' => 'dispense',
        ]);

        $this->prescription->refresh();
        $this->assertEquals('partially_dispensed', $this->prescription->status);

        $this->visit->refresh();
        $this->assertEquals('waiting_pharmacy', $this->visit->status);
    }

    public function test_fully_dispensing_all_items_completes_the_visit()
    {
        $this->actingAs($this->pharmacist)
            ->post("/pharmacy/{$this->prescription->id}/items/{$this->item->id}/dispense", ['quantity' => 21]);

        $this->prescription->refresh();
        $this->assertEquals('dispensed', $this->prescription->status);

        $this->visit->refresh();
        $this->assertEquals('completed', $this->visit->status);
    }

    public function test_cannot_dispense_more_than_available_stock()
    {
        $this->item->update(['quantity_to_dispense' => 50]);

        $response = $this->actingAs($this->pharmacist)
            ->post("/pharmacy/{$this->prescription->id}/items/{$this->item->id}/dispense", ['quantity' => 31]);

        $response->assertSessionHasErrors('quantity');
        $this->item->refresh();
        $this->assertEquals(0, $this->item->quantity_dispensed);
    }

    public function test_cannot_dispense_more_than_the_prescribed_quantity()
    {
        $response = $this->actingAs($this->pharmacist)
            ->post("/pharmacy/{$this->prescription->id}/items/{$this->item->id}/dispense", ['quantity' => 22]);

        $response->assertSessionHasErrors('quantity');
    }
}
