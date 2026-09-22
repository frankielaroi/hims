<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RadiologyOrder;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadiologyTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $radiographer;

    protected Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = $this->userWith('doctor', ['order_imaging']);
        $this->radiographer = $this->userWith('radiographer', ['process_radiology_orders']);

        $this->visit = Visit::factory()->waitingDoctor()->create(['created_by' => $this->doctor->id]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userWith(string $roleSlug, array $permissions): User
    {
        $role = Role::create(['name' => ucfirst($roleSlug), 'slug' => $roleSlug]);

        $ids = collect($permissions)->map(fn (string $slug) => Permission::updateOrCreate(
            ['slug' => $slug],
            ['name' => $slug]
        )->id);

        $role->permissions()->sync($ids);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    protected function orderFor(array $overrides = []): RadiologyOrder
    {
        return RadiologyOrder::create(array_merge([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Cough with fever',
            'priority' => 'routine',
            'status' => RadiologyOrder::STATUS_ORDERED,
        ], $overrides));
    }

    public function test_a_clinician_can_request_imaging_from_a_consultation(): void
    {
        $response = $this->actingAs($this->doctor)->post("/consultations/{$this->visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Cough with fever for five days',
            'priority' => 'urgent',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('radiology_orders', [
            'visit_id' => $this->visit->id,
            'modality' => 'xray',
            'body_part' => 'Chest',
            'priority' => 'urgent',
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_by' => $this->doctor->id,
        ]);
    }

    public function test_the_consultation_workspace_offers_the_doctor_an_imaging_request_form(): void
    {
        $response = $this->actingAs($this->doctor)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertSee('Request Imaging');
        $response->assertSee(route('radiology.store', $this->visit), false);
    }

    public function test_a_clinician_without_the_imaging_permission_is_not_shown_the_request_form(): void
    {
        $response = $this->actingAs($this->radiographer)->get("/consultations/{$this->visit->id}");

        $response->assertOk();
        $response->assertDontSee('Request Imaging');
    }

    public function test_requesting_imaging_without_the_permission_is_forbidden(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->post("/consultations/{$this->visit->id}/radiology", [
            'modality' => 'xray',
            'body_part' => 'Chest',
            'clinical_indication' => 'Cough',
            'priority' => 'routine',
        ])->assertForbidden();

        $this->assertDatabaseCount('radiology_orders', 0);
    }

    public function test_filing_a_report_charges_the_study_once(): void
    {
        Service::create([
            'name' => 'X-Ray', 'category' => 'Radiology', 'price' => 120,
            'is_nhis_covered' => true, 'code' => 'RAD-XRAY', 'is_active' => true,
        ]);

        $order = $this->orderFor();

        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/report", [
            'findings' => 'Right basal consolidation.',
            'impression' => 'Right lower lobe pneumonia.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(RadiologyOrder::STATUS_REPORTED, $order->status);
        $this->assertSame($this->radiographer->id, $order->reported_by);
        $this->assertDatabaseCount('invoice_items', 1);

        // Amending the report must not bill the patient a second time.
        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/report", [
            'findings' => 'Right basal consolidation, slightly larger.',
            'impression' => 'Right lower lobe pneumonia.',
        ]);

        $this->assertDatabaseCount('invoice_items', 1);
    }

    public function test_amending_a_report_clears_the_previous_review(): void
    {
        $order = $this->orderFor([
            'status' => RadiologyOrder::STATUS_REPORTED,
            'findings' => 'Clear.',
            'impression' => 'Normal.',
            'reported_by' => $this->radiographer->id,
            'reported_at' => now()->subHour(),
            'reviewed_by' => $this->doctor->id,
            'reviewed_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/report", [
            'findings' => 'On review, there is a small effusion.',
            'impression' => 'Small pleural effusion.',
        ]);

        $order->refresh();
        $this->assertNull($order->reviewed_at);
        $this->assertNull($order->reviewed_by);
    }

    public function test_a_report_can_be_marked_as_read_by_the_ordering_clinician(): void
    {
        $order = $this->orderFor([
            'status' => RadiologyOrder::STATUS_REPORTED,
            'findings' => 'Clear.',
            'impression' => 'Normal.',
            'reported_by' => $this->radiographer->id,
            'reported_at' => now(),
        ]);

        $this->actingAs($this->doctor)->post("/radiology/{$order->id}/review", [
            'review_note' => 'No action needed.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(RadiologyOrder::STATUS_REVIEWED, $order->status);
        $this->assertSame($this->doctor->id, $order->reviewed_by);
    }

    public function test_a_study_cannot_be_reviewed_before_it_is_reported(): void
    {
        $order = $this->orderFor();

        $this->actingAs($this->doctor)->post("/radiology/{$order->id}/review")->assertStatus(422);
        $this->assertNull($order->refresh()->reviewed_at);
    }

    public function test_a_reported_study_cannot_be_cancelled(): void
    {
        $order = $this->orderFor([
            'status' => RadiologyOrder::STATUS_REPORTED,
            'reported_at' => now(),
            'reported_by' => $this->radiographer->id,
        ]);

        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/cancel", [
            'cancellation_reason' => 'Requested in error',
        ])->assertStatus(422);
    }

    public function test_an_image_can_be_attached_and_is_kept_off_the_public_disk(): void
    {
        Storage::fake('local');
        $order = $this->orderFor();

        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/attachments", [
            'file' => UploadedFile::fake()->image('chest.jpg'),
            'kind' => 'image',
        ])->assertRedirect();

        $attachment = $order->attachments()->sole();
        $this->assertSame('local', $attachment->disk);
        $this->assertSame('image', $attachment->kind);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_an_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        $order = $this->orderFor();

        $this->actingAs($this->radiographer)->post("/radiology/{$order->id}/attachments", [
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            'kind' => 'image',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('radiology_attachments', 0);
    }

    public function test_attachments_are_not_uploadable_without_the_radiology_permission(): void
    {
        Storage::fake('local');
        $order = $this->orderFor();

        $this->actingAs($this->doctor)->post("/radiology/{$order->id}/attachments", [
            'file' => UploadedFile::fake()->image('chest.jpg'),
            'kind' => 'image',
        ])->assertForbidden();

        $this->assertDatabaseCount('radiology_attachments', 0);
    }
}
