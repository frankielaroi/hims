<?php

namespace Tests\Feature;

use App\Models\Icd10Code;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\Icd10CodesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Icd10CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_loads_a_categorised_catalogue(): void
    {
        $this->seed(Icd10CodesSeeder::class);

        $this->assertGreaterThan(100, Icd10Code::count());
        // Every code must be filed under a chapter, or the picker cannot group it.
        $this->assertSame(0, Icd10Code::whereNull('category')->count());
    }

    public function test_the_seeder_can_be_run_twice_without_duplicating_codes(): void
    {
        $this->seed(Icd10CodesSeeder::class);
        $first = Icd10Code::count();

        $this->seed(Icd10CodesSeeder::class);

        $this->assertSame($first, Icd10Code::count());
    }

    public function test_re_seeding_corrects_a_code_that_was_stored_without_a_category(): void
    {
        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria', 'category' => null]);

        $this->seed(Icd10CodesSeeder::class);

        $this->assertSame('Infectious & parasitic', Icd10Code::where('code', 'B54')->value('category'));
    }

    public function test_the_catalogue_covers_the_common_presentations_a_district_hospital_sees(): void
    {
        $this->seed(Icd10CodesSeeder::class);

        // A spread across chapters, not just one specialty.
        foreach (['B54', 'I10', 'E11', 'J18', 'K35', 'N39.0', 'O14', 'R50.9', 'T63'] as $code) {
            $this->assertDatabaseHas('icd10_codes', ['code' => $code]);
        }
    }

    public function test_the_diagnosis_picker_groups_the_codes_by_chapter(): void
    {
        $this->seed(Icd10CodesSeeder::class);

        $doctor = User::factory()->create();
        $doctor->roles()->attach(Role::create(['name' => 'Doctor', 'slug' => 'doctor']));
        $visit = Visit::factory()->waitingDoctor()->create(['created_by' => $doctor->id]);

        $response = $this->actingAs($doctor)->get("/consultations/{$visit->id}");

        $response->assertOk();
        $response->assertSee('<optgroup label="Respiratory">', false);
        $response->assertSee('B54 - Unspecified malaria');
    }

    public function test_a_seeded_code_can_be_used_as_a_diagnosis(): void
    {
        $this->seed(Icd10CodesSeeder::class);

        $doctor = User::factory()->create();
        $doctor->roles()->attach(Role::create(['name' => 'Doctor', 'slug' => 'doctor']));
        $visit = Visit::factory()->waitingDoctor()->create(['created_by' => $doctor->id]);

        $this->actingAs($doctor)->post("/consultations/{$visit->id}", [
            'clinical_notes' => 'Fever, cough and crackles at the right base.',
            'diagnoses' => [
                ['code' => 'J18', 'description' => 'Community-acquired pneumonia', 'type' => 'primary', 'certainty' => 'probable'],
            ],
            'outcome' => 'discharge',
        ])->assertRedirect('/consultations');

        $this->assertDatabaseHas('diagnoses', [
            'icd10_code' => 'J18',
            'type' => 'primary',
            'is_primary' => true,
        ]);
    }
}
