<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Icd10Code;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach(Role::create(['name' => 'Doctor', 'slug' => 'doctor']));

        $this->visit = Visit::factory()->waitingDoctor()->create(['created_by' => $this->doctor->id]);

        Icd10Code::create(['code' => 'A00', 'name' => 'Cholera']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'clinical_notes' => 'Seen and assessed.',
            'diagnoses' => [
                ['code' => 'A00', 'description' => 'Cholera', 'type' => 'primary', 'certainty' => 'confirmed'],
            ],
        ], $overrides);
    }

    public function test_leaving_against_advice_records_the_counselling_and_the_witness(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'dama',
            'dama_risks_explained' => 'Told that untreated dehydration may be fatal within hours.',
            'dama_witness_name' => 'Kwame Owusu',
            'dama_witness_relationship' => 'Brother',
            'dama_reason_given' => 'Needs to return to work',
            'dama_patient_signature_obtained' => '1',
        ]));

        $response->assertRedirect('/consultations');
        $this->assertDatabaseHas('dama_records', [
            'visit_id' => $this->visit->id,
            'witness_name' => 'Kwame Owusu',
            'witness_relationship' => 'Brother',
            'patient_signature_obtained' => true,
            'counselled_by' => $this->doctor->id,
        ]);
        $this->assertDatabaseHas('visit_outcomes', ['visit_id' => $this->visit->id, 'outcome' => 'dama']);
    }

    public function test_leaving_against_advice_requires_the_risks_and_a_witness(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'dama',
        ]));

        $response->assertSessionHasErrors(['dama_risks_explained', 'dama_witness_name']);
        $this->assertDatabaseCount('dama_records', 0);
    }

    public function test_certifying_a_death_records_the_cause_and_the_certifying_clinician(): void
    {
        $diedAt = now()->subHour();

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'deceased',
            'death_died_at' => $diedAt->format('Y-m-d H:i:s'),
            'death_place' => 'Emergency department',
            'death_immediate_cause' => 'Hypovolaemic shock',
            'death_underlying_cause' => 'Severe cholera',
            'death_manner' => 'natural',
        ]));

        $this->assertDatabaseHas('death_records', [
            'visit_id' => $this->visit->id,
            'patient_id' => $this->visit->patient_id,
            'immediate_cause' => 'Hypovolaemic shock',
            'underlying_cause' => 'Severe cholera',
            'manner' => 'natural',
            'referred_to_coroner' => false,
            'certified_by' => $this->doctor->id,
        ]);
    }

    public function test_certifying_a_death_requires_the_time_cause_and_manner(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'deceased',
        ]));

        $response->assertSessionHasErrors(['death_died_at', 'death_immediate_cause', 'death_manner']);
        $this->assertDatabaseCount('death_records', 0);
    }

    public function test_an_unnatural_death_is_referred_to_the_coroner_automatically(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'deceased',
            'death_died_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'death_immediate_cause' => 'Head injury',
            'death_manner' => 'accident',
        ]));

        $this->assertDatabaseHas('death_records', [
            'visit_id' => $this->visit->id,
            'manner' => 'accident',
            'referred_to_coroner' => true,
        ]);
    }

    public function test_a_death_in_a_bed_closes_the_admission_and_frees_the_bed(): void
    {
        $ward = Ward::create(['name' => 'Medical Ward', 'category' => 'General', 'is_active' => true]);
        $bed = Bed::create(['ward_id' => $ward->id, 'bed_number' => 'M-01', 'status' => 'occupied']);
        $admission = Admission::create([
            'visit_id' => $this->visit->id,
            'bed_id' => $bed->id,
            'admitted_by' => $this->doctor->id,
            'admission_date' => now()->subDays(2),
            'status' => 'admitted',
        ]);

        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'deceased',
            'death_died_at' => now()->format('Y-m-d H:i:s'),
            'death_immediate_cause' => 'Septic shock',
            'death_manner' => 'natural',
        ]));

        $this->assertSame('discharged', $admission->refresh()->status);
        $this->assertNotNull($admission->discharge_date);
        // The bed must not keep showing the patient on the ward board.
        $this->assertSame('cleaning', $bed->refresh()->status);
    }

    public function test_a_normal_discharge_records_no_dama_or_death_paperwork(): void
    {
        $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}", $this->payload([
            'outcome' => 'discharge',
        ]));

        $this->assertDatabaseCount('dama_records', 0);
        $this->assertDatabaseCount('death_records', 0);
    }
}
