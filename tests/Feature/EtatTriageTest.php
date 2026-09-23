<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Patient;
use App\Models\Role;
use App\Models\TriageAssessment;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalsRecord;
use App\Services\EtatTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHO Emergency Triage Assessment and Treatment, as Ghana applies it to
 * under-fives. The point of ETAT is that the signs decide the priority, so
 * these tests are mostly about the nurse's choice not being the last word.
 */
class EtatTriageTest extends TestCase
{
    use RefreshDatabase;

    protected User $nurse;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Nurse', 'slug' => 'nurse']);
        $this->nurse = User::factory()->create();
        $this->nurse->roles()->attach($role);

        $this->department = Department::create(['name' => 'OPD', 'slug' => 'opd']);
    }

    protected function visitFor(Patient $patient): Visit
    {
        return Visit::create([
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
            'visit_number' => 'V-'.$patient->id,
            'queue_number' => 1,
            'type' => 'OPD',
            'payment_method' => 'cash',
            'created_by' => $this->nurse->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'chief_complaint' => 'Fever and not feeding',
            'priority_level' => 'routine',
        ], $overrides);
    }

    public function test_an_emergency_sign_raises_the_priority_the_nurse_chose(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        // The nurse chose routine; a convulsing child is an emergency whatever
        // the dropdown says.
        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'priority_level' => 'routine',
            'etat_emergency_signs' => ['convulsing'],
            'blood_glucose' => 3.2,
        ]));

        $this->assertSame('emergency', $visit->refresh()->priority);
        $this->assertSame('emergency', TriageAssessment::where('visit_id', $visit->id)->value('priority_level'));
    }

    public function test_a_priority_sign_moves_a_routine_child_up_the_queue(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'priority_level' => 'routine',
            'etat_priority_signs' => ['malnutrition', 'pallor'],
        ]));

        $this->assertSame('urgent', $visit->refresh()->priority);
    }

    public function test_the_protocol_never_lowers_a_priority_the_nurse_judged_higher(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        // A nurse may see an emergency ETAT does not list. The protocol must
        // not talk them down to urgent.
        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'priority_level' => 'emergency',
            'etat_priority_signs' => ['pain'],
        ]));

        $this->assertSame('emergency', $visit->refresh()->priority);
    }

    public function test_a_child_with_convulsions_needs_a_blood_glucose_before_triage_completes(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'etat_emergency_signs' => ['convulsing'],
        ]))->assertSessionHasErrors('blood_glucose');

        $this->assertDatabaseCount('triage_assessments', 0);
        $this->assertDatabaseCount('vitals_records', 0);
    }

    public function test_coma_also_requires_a_blood_glucose(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'etat_emergency_signs' => ['coma'],
            'avpu' => 'unresponsive',
        ]))->assertSessionHasErrors('blood_glucose');
    }

    public function test_the_signs_and_observations_are_kept_on_the_record(): void
    {
        $child = Patient::factory()->child()->create();
        $visit = $this->visitFor($child);

        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'etat_priority_signs' => ['tiny_baby', 'oedema'],
            'blood_glucose' => 5.1,
            'muac_cm' => 11.0,
            'avpu' => 'alert',
        ]))->assertSessionHasNoErrors();

        $assessment = TriageAssessment::where('visit_id', $visit->id)->sole();
        $vitals = VitalsRecord::where('visit_id', $visit->id)->sole();

        $this->assertEqualsCanonicalizing(['tiny_baby', 'oedema'], $assessment->etat_priority_signs);
        $this->assertSame([], $assessment->etat_emergency_signs);
        $this->assertEquals(5.1, $vitals->blood_glucose);
        $this->assertEquals(11.0, $vitals->muac_cm);
        $this->assertSame('alert', $vitals->avpu);
    }

    public function test_etat_signs_are_ignored_for_an_adult(): void
    {
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(40)->format('Y-m-d')]);
        $visit = $this->visitFor($adult);

        // ETAT is a protocol for young children. An adult keeps the ordinary
        // categories even if the field is somehow posted.
        $this->actingAs($this->nurse)->post("/triage/{$visit->id}", $this->payload([
            'priority_level' => 'routine',
            'etat_emergency_signs' => ['convulsing'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('routine', $visit->refresh()->priority);
        $this->assertSame([], TriageAssessment::where('visit_id', $visit->id)->value('etat_emergency_signs'));
    }

    public function test_the_etat_checklists_only_appear_for_a_child(): void
    {
        $child = Patient::factory()->child()->create();
        $adult = Patient::factory()->create(['date_of_birth' => now()->subYears(30)->format('Y-m-d')]);

        $this->actingAs($this->nurse)->get("/triage/{$this->visitFor($child)->id}")
            ->assertOk()
            ->assertSee('ETAT — emergency signs');

        $this->actingAs($this->nurse)->get("/triage/{$this->visitFor($adult)->id}")
            ->assertOk()
            ->assertDontSee('ETAT — emergency signs');
    }

    public function test_the_age_boundary_follows_the_protocol(): void
    {
        $etat = app(EtatTriage::class);

        $this->assertTrue($etat->appliesTo(Patient::factory()->child(4)->create()));
        $this->assertFalse($etat->appliesTo(Patient::factory()->child(6)->create()));
    }
}
