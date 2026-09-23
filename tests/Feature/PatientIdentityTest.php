<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        // The role needs the permissions the controller actually checks, or
        // every request 403s before reaching the behaviour under test.
        $this->adminRole->permissions()->sync(
            collect(['register_patients', 'view_patients', 'edit_patient_identity', 'merge_patients', 'view_clinical_history'])
                ->map(fn (string $slug) => Permission::updateOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($this->adminRole);
    }

    public function test_sensitive_identity_changes_are_logged()
    {
        $patient = Patient::create([
            'hospital_id' => 'HIMS-001',
            'full_name' => 'Original Name',
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);

        $this->actingAs($this->admin)->patch("/patients/{$patient->id}", [
            'full_name' => 'Updated Name',
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);

        $this->assertDatabaseHas('patient_identity_logs', [
            'patient_id' => $patient->id,
            'field_name' => 'full_name',
            'old_value' => 'Original Name',
            'new_value' => 'Updated Name',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_duplicate_check_triggers_on_matching_name_and_age()
    {
        Patient::create([
            'hospital_id' => 'HIMS-001',
            'full_name' => 'Kofi Mensah',
            'estimated_age' => 45,
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)->post('/patients', [
            'full_name' => 'Kofi Mensah',
            'estimated_age' => 45,
            'sex' => 'male',
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHas('duplicate_found');
    }

    public function test_admin_can_merge_two_patients()
    {
        $keep = Patient::create([
            'hospital_id' => 'KEEP-01',
            'full_name' => 'Keep Me',
            'sex' => 'male',
            'payment_method' => 'cash',
            'allergies' => ['Aspirin'],
        ]);

        $merge = Patient::create([
            'hospital_id' => 'MERGE-01',
            'full_name' => 'Merge Me',
            'sex' => 'male',
            'payment_method' => 'cash',
            'allergies' => ['Peanuts'],
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/patients/merge', [
            'keep_id' => 'KEEP-01',
            'merge_id' => 'MERGE-01',
        ]);

        $this->assertSoftDeleted('patients', ['hospital_id' => 'MERGE-01']);
        $this->assertEquals(['Aspirin', 'Peanuts'], $keep->fresh()->allergies);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'patient_merged',
            'user_id' => $this->admin->id,
        ]);
    }
}
