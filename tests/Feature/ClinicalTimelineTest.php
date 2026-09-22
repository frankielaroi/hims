<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Icd10Code;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\RadiologyOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicalTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ClinicalTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Patient $patient;

    protected ClinicalTimeline $timeline;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::updateOrCreate(
            ['slug' => 'view_clinical_history'],
            ['name' => 'View clinical history']
        );
        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $role->permissions()->sync([$permission->id]);

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($role);

        $this->patient = Patient::factory()->create();
        $this->timeline = app(ClinicalTimeline::class);

        Icd10Code::create(['code' => 'B54', 'name' => 'Malaria']);
    }

    protected function visit(array $overrides = []): Visit
    {
        return Visit::factory()->create(array_merge([
            'patient_id' => $this->patient->id,
            'created_by' => $this->doctor->id,
        ], $overrides));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    protected function ofType($entries, string $type): array
    {
        return $entries->where('type', $type)->values()->all();
    }

    public function test_every_attendance_appears_on_the_timeline(): void
    {
        $this->visit();
        $this->visit();

        $entries = $this->timeline->for($this->patient);

        $this->assertCount(2, $this->ofType($entries, 'visit'));
    }

    public function test_a_signed_consultation_appears_with_its_primary_diagnosis(): void
    {
        $visit = $this->visit();
        $consultation = Consultation::factory()->signedBy($this->doctor)->create(['visit_id' => $visit->id]);
        Diagnosis::create([
            'consultation_id' => $consultation->id,
            'icd10_code' => 'B54',
            'description' => 'Uncomplicated malaria',
            'type' => 'primary',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ]);

        $entries = $this->timeline->for($this->patient);
        $consultations = $this->ofType($entries, 'consultation');

        $this->assertCount(1, $consultations);
        $this->assertSame('Uncomplicated malaria', $consultations[0]['summary']);
    }

    public function test_an_unsigned_draft_is_left_off_the_timeline(): void
    {
        $visit = $this->visit();
        Consultation::factory()->draft()->create(['visit_id' => $visit->id]);

        $entries = $this->timeline->for($this->patient);

        // A draft is working memory, not a record of what happened.
        $this->assertSame([], $this->ofType($entries, 'consultation'));
    }

    public function test_lab_results_appear_with_their_values(): void
    {
        $visit = $this->visit();
        $test = LabTest::create([
            'name' => 'Malaria RDT', 'code' => 'MRDT', 'category' => 'Parasitology',
            'specimen_type' => 'Blood', 'price' => 20, 'is_nhis_covered' => true, 'is_active' => true,
        ]);
        $order = LabOrder::create([
            'visit_id' => $visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'routine',
            'status' => 'verified',
        ]);
        LabOrderItem::create([
            'lab_order_id' => $order->id,
            'lab_test_id' => $test->id,
            'specimen_type' => 'Blood',
            'result_value' => 'Positive',
            'result_flag' => 'abnormal',
        ]);

        $entries = $this->timeline->for($this->patient);
        $lab = $this->ofType($entries, 'lab');

        $this->assertCount(1, $lab);
        $this->assertContains('Malaria RDT: Positive (abnormal)', $lab[0]['detail']);
        // An unread result is called out as such.
        $this->assertContains('Results not yet read by a clinician', $lab[0]['detail']);
    }

    public function test_imaging_appears_with_its_impression(): void
    {
        $visit = $this->visit();
        RadiologyOrder::create([
            'visit_id' => $visit->id,
            'ordered_by' => $this->doctor->id,
            'modality' => 'xray',
            'body_part' => 'Chest',
            'priority' => 'routine',
            'status' => RadiologyOrder::STATUS_REPORTED,
            'impression' => 'Right lower lobe pneumonia.',
            'reported_at' => now(),
        ]);

        $entries = $this->timeline->for($this->patient);
        $imaging = $this->ofType($entries, 'radiology');

        $this->assertCount(1, $imaging);
        $this->assertSame('Imaging — XRAY — Chest', $imaging[0]['title']);
        $this->assertContains('Impression: Right lower lobe pneumonia.', $imaging[0]['detail']);
    }

    public function test_entries_are_ordered_newest_first(): void
    {
        $this->visit(['opened_at' => now()->subYear()]);
        $this->visit(['opened_at' => now()->subDay()]);

        $entries = $this->timeline->for($this->patient);
        $timestamps = $entries->pluck('occurred_at')->map->getTimestamp()->all();

        $sorted = $timestamps;
        rsort($sorted);
        $this->assertSame($sorted, $timestamps);
    }

    public function test_the_timeline_page_renders_for_a_clinician(): void
    {
        $visit = $this->visit();
        $consultation = Consultation::factory()->signedBy($this->doctor)->create(['visit_id' => $visit->id]);
        Diagnosis::create([
            'consultation_id' => $consultation->id,
            'icd10_code' => 'B54',
            'description' => 'Uncomplicated malaria',
            'type' => 'primary',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->doctor)->get("/patients/{$this->patient->id}/timeline");

        $response->assertOk();
        $response->assertSee('Uncomplicated malaria');
        $response->assertSee('Clinical timeline');
    }

    public function test_the_timeline_is_closed_to_users_without_clinical_history_access(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get("/patients/{$this->patient->id}/timeline")->assertForbidden();
    }

    public function test_a_patient_with_no_record_yields_an_empty_timeline(): void
    {
        $this->assertTrue($this->timeline->for($this->patient)->isEmpty());
    }
}
