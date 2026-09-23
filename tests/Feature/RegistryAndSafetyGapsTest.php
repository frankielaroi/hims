<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\RadiologyOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Rules\GhanaCardPin;
use App\Services\ImmunizationSchedule;
use Database\Seeders\DrugsSeeder;
use Database\Seeders\LabTestsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryAndSafetyGapsTest extends TestCase
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

    public function test_a_ghana_card_pin_is_stored_and_searchable(): void
    {
        $clerk = $this->userWith('receptionist', ['register_patients', 'view_patients']);

        $this->actingAs($clerk)->post('/patients', [
            'full_name' => 'Kwame Asante',
            'sex' => 'male',
            'date_of_birth' => '1990-04-02',
            'payment_method' => 'cash',
            'ghana_card_pin' => 'GHA-123456789-0',
            'occupation' => 'Farmer',
            'confirm_duplicate' => 1,
        ])->assertSessionHasNoErrors();

        $patient = Patient::where('ghana_card_pin', 'GHA-123456789-0')->sole();

        $this->assertSame('Farmer', $patient->occupation);

        $this->actingAs($clerk)->get('/patients?search=GHA-123456789-0')
            ->assertOk()
            ->assertSee('Kwame Asante');
    }

    public function test_a_pin_typed_as_bare_digits_is_canonicalised(): void
    {
        // This is how it is read off the card.
        $this->assertSame('GHA-123456789-0', GhanaCardPin::normalise('1234567890'));
        $this->assertSame('GHA-123456789-0', GhanaCardPin::normalise(' gha-123456789-0 '));
        $this->assertNull(GhanaCardPin::normalise(''));
    }

    public function test_a_malformed_pin_is_refused(): void
    {
        $clerk = $this->userWith('receptionist', ['register_patients', 'view_patients']);

        $this->actingAs($clerk)->post('/patients', [
            'full_name' => 'Bad Pin',
            'sex' => 'male',
            'payment_method' => 'cash',
            'ghana_card_pin' => 'GHA-12345-0',
            'confirm_duplicate' => 1,
        ])->assertSessionHasErrors('ghana_card_pin');
    }

    public function test_the_same_pin_cannot_be_registered_twice(): void
    {
        $clerk = $this->userWith('receptionist', ['register_patients', 'view_patients']);
        Patient::factory()->create(['ghana_card_pin' => 'GHA-987654321-1']);

        $this->actingAs($clerk)->post('/patients', [
            'full_name' => 'Duplicate Card',
            'sex' => 'female',
            'payment_method' => 'cash',
            'ghana_card_pin' => 'GHA-987654321-1',
            'confirm_duplicate' => 1,
        ])->assertSessionHasErrors('ghana_card_pin');
    }

    public function test_changing_the_pin_is_logged_as_an_identity_change(): void
    {
        $admin = $this->userWith('admin', ['register_patients', 'view_patients', 'edit_patient_identity']);
        $patient = Patient::factory()->create(['ghana_card_pin' => 'GHA-111111111-1']);

        $this->actingAs($admin)->patch("/patients/{$patient->id}", [
            'full_name' => $patient->full_name,
            'sex' => $patient->sex,
            'payment_method' => 'cash',
            'ghana_card_pin' => 'GHA-222222222-2',
        ]);

        $this->assertDatabaseHas('patient_identity_logs', [
            'patient_id' => $patient->id,
            'field_name' => 'ghana_card_pin',
            'old_value' => 'GHA-111111111-1',
            'new_value' => 'GHA-222222222-2',
        ]);
    }

    public function test_an_xray_on_a_pregnant_patient_needs_a_written_reason(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $mother = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(26)->toDateString()]);
        $visit = Visit::factory()->create(['patient_id' => $mother->id]);
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Cough',
            'priority' => 'routine',
        ])->assertSessionHasErrors('pregnancy_justification');

        $this->assertDatabaseCount('radiology_orders', 0);
    }

    public function test_a_justified_xray_on_a_pregnant_patient_goes_through(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $mother = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(26)->toDateString()]);
        $visit = Visit::factory()->create(['patient_id' => $mother->id]);
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

    public function test_ultrasound_on_a_pregnant_patient_needs_no_justification(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $mother = Patient::factory()->female()->create(['date_of_birth' => now()->subYears(26)->toDateString()]);
        $visit = Visit::factory()->create(['patient_id' => $mother->id]);
        Consultation::factory()->create(['visit_id' => $visit->id, 'pregnancy_status' => 'pregnant']);

        // Ultrasound carries no fetal dose — it is the study of choice here.
        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'ultrasound',
            'body_part' => 'Obstetric',
            'clinical_indication' => 'Dating scan',
            'priority' => 'routine',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('radiology_orders', 1);
    }

    public function test_an_unrecorded_pregnancy_status_does_not_block_imaging(): void
    {
        $doctor = $this->userWith('doctor', ['order_imaging', 'view_clinical_history']);
        $visit = Visit::factory()->create();

        // A blank field is not a positive pregnancy test.
        $this->actingAs($doctor)->post("/consultations/{$visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Wrist',
            'clinical_indication' => 'Fall on outstretched hand',
            'priority' => 'routine',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('radiology_orders', 1);
    }

    public function test_the_epi_schedule_covers_vitamin_a_and_the_malaria_vaccine(): void
    {
        $child = Patient::factory()->child(1)->create();

        $due = app(ImmunizationSchedule::class)->statusFor($child)->pluck('code')->unique();

        $this->assertTrue($due->contains('VITA'), 'Vitamin A supplementation is missing from the schedule.');
        $this->assertTrue($due->contains('MAL'), 'The malaria vaccine is missing from the schedule.');
    }

    public function test_the_antenatal_profile_tests_are_on_the_catalogue(): void
    {
        $this->seed(LabTestsSeeder::class);

        foreach (['ABO-RH', 'VDRL', 'SICKLING', 'HB', 'G6PD', 'GENEXPERT', 'CSF'] as $code) {
            $this->assertTrue(
                LabTest::where('code', $code)->exists(),
                "Lab test {$code} is missing from the catalogue."
            );
        }
    }

    public function test_the_obstetric_emergency_drugs_are_on_the_formulary(): void
    {
        $this->seed(DrugsSeeder::class);

        // Without these a postpartum haemorrhage or eclamptic fit cannot be
        // prescribed at all.
        foreach (['Oxytocin', 'Magnesium Sulphate', 'Misoprostol', 'Oral Rehydration Salts', 'Zinc Sulphate'] as $name) {
            $this->assertTrue(
                Drug::where('name', $name)->exists(),
                "Drug {$name} is missing from the formulary."
            );
        }
    }

    public function test_only_ionising_modalities_are_gated(): void
    {
        $this->assertTrue((new RadiologyOrder(['modality' => 'ct']))->usesIonisingRadiation());
        $this->assertFalse((new RadiologyOrder(['modality' => 'mri']))->usesIonisingRadiation());
        $this->assertFalse((new RadiologyOrder(['modality' => 'ultrasound']))->usesIonisingRadiation());
    }
}
