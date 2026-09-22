<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientClinicalSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $nurse;

    protected Patient $patient;

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

        $this->patient = Patient::factory()->create();
    }

    public function test_records_an_allergy_with_its_severity_and_reaction(): void
    {
        $response = $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'Penicillin', 'severity' => 'severe', 'reaction' => 'Angioedema'],
            ],
            'blood_group' => 'O+',
        ]);

        $response->assertRedirect(route('patients.show', $this->patient));
        $this->assertDatabaseHas('patient_allergies', [
            'patient_id' => $this->patient->id,
            'allergen' => 'Penicillin',
            'severity' => 'severe',
            'reaction' => 'Angioedema',
            'status' => 'active',
            'recorded_by' => $this->nurse->id,
        ]);
    }

    public function test_keeps_the_legacy_allergy_list_in_step_with_the_structured_table(): void
    {
        $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'Penicillin', 'severity' => 'severe'],
                ['allergen' => 'Sulfa', 'severity' => 'moderate'],
            ],
        ]);

        $this->assertEqualsCanonicalizing(
            ['Penicillin', 'Sulfa'],
            $this->patient->refresh()->allergies
        );
    }

    public function test_updates_an_existing_allergen_rather_than_duplicating_it(): void
    {
        PatientAllergy::factory()->for($this->patient)->to('Penicillin')->create(['severity' => 'mild']);

        $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'penicillin', 'severity' => 'severe', 'reaction' => 'Anaphylaxis'],
            ],
        ]);

        $this->assertDatabaseCount('patient_allergies', 1);
        $this->assertDatabaseHas('patient_allergies', [
            'patient_id' => $this->patient->id,
            'severity' => 'severe',
            'reaction' => 'Anaphylaxis',
        ]);
    }

    public function test_a_removed_allergen_is_marked_inactive_rather_than_deleted(): void
    {
        PatientAllergy::factory()->for($this->patient)->to('Penicillin')->severe()->create();

        $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'Sulfa', 'severity' => 'moderate'],
            ],
        ]);

        $this->assertDatabaseHas('patient_allergies', [
            'patient_id' => $this->patient->id,
            'allergen' => 'Penicillin',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('patient_allergies', [
            'patient_id' => $this->patient->id,
            'allergen' => 'Sulfa',
            'status' => 'active',
        ]);
    }

    public function test_ignores_a_blank_allergen_row(): void
    {
        $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => '', 'severity' => 'unknown'],
                ['allergen' => '  ', 'severity' => 'unknown'],
            ],
        ]);

        $this->assertDatabaseCount('patient_allergies', 0);
    }

    public function test_rejects_a_severity_that_is_not_recognised(): void
    {
        $response = $this->actingAs($this->nurse)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'Penicillin', 'severity' => 'catastrophic'],
            ],
        ]);

        $response->assertSessionHasErrors('allergies.0.severity');
        $this->assertDatabaseCount('patient_allergies', 0);
    }

    public function test_forbids_updating_safety_records_without_the_permission(): void
    {
        $receptionist = User::factory()->create();

        $response = $this->actingAs($receptionist)->patch("/patients/{$this->patient->id}/clinical", [
            'allergies' => [
                ['allergen' => 'Penicillin', 'severity' => 'severe'],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('patient_allergies', 0);
    }

    public function test_the_record_shows_an_allergys_severity_and_reaction(): void
    {
        $viewPermission = Permission::updateOrCreate(
            ['slug' => 'view_clinical_history'],
            ['name' => 'View clinical history']
        );
        $this->nurse->roles->first()->permissions()->syncWithoutDetaching([$viewPermission->id]);

        PatientAllergy::factory()->for($this->patient)->to('Penicillin')->severe()->create([
            'reaction' => 'Angioedema',
        ]);

        $response = $this->actingAs($this->nurse)->get("/patients/{$this->patient->id}");

        $response->assertOk();
        $response->assertSee('Penicillin');
        $response->assertSee('severe');
        $response->assertSee('Angioedema');
    }
}
