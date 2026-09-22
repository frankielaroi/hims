<?php

namespace Tests\Feature;

use App\Models\ClinicalTask;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Permission;
use App\Models\RadiologyOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitOutcome;
use App\Services\ClinicalTaskGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected Visit $visit;

    protected ClinicalTaskGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::updateOrCreate(
            ['slug' => 'manage_clinical_tasks'],
            ['name' => 'Manage clinical tasks']
        );
        $role = Role::create(['name' => 'Doctor', 'slug' => 'doctor']);
        $role->permissions()->sync([$permission->id]);

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($role);

        $this->visit = Visit::factory()->create(['created_by' => $this->doctor->id]);
        $this->generator = app(ClinicalTaskGenerator::class);
    }

    protected function labTest(): LabTest
    {
        return LabTest::create([
            'name' => 'Potassium', 'code' => 'K', 'category' => 'Chemistry',
            'specimen_type' => 'Blood', 'price' => 30, 'is_nhis_covered' => true, 'is_active' => true,
        ]);
    }

    protected function verifiedOrder(string $flag = 'normal'): LabOrder
    {
        $order = LabOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'routine',
            'status' => 'verified',
            'verified_by' => $this->doctor->id,
            'verified_at' => now()->subHours(2),
        ]);

        LabOrderItem::create([
            'lab_order_id' => $order->id,
            'lab_test_id' => $this->labTest()->id,
            'specimen_type' => 'Blood',
            'result_value' => '7.1',
            'result_flag' => $flag,
            'authorized_by' => $this->doctor->id,
            'authorized_at' => now()->subHours(2),
        ]);

        return $order;
    }

    public function test_opens_a_task_for_verified_results_nobody_has_read(): void
    {
        $order = $this->verifiedOrder();

        $this->generator->run();

        $this->assertDatabaseHas('clinical_tasks', [
            'type' => ClinicalTask::TYPE_PENDING_RESULT,
            'source_id' => $order->id,
            'status' => ClinicalTask::STATUS_OPEN,
            'assigned_to' => $this->doctor->id,
        ]);
    }

    public function test_a_critical_result_opens_a_critical_priority_task(): void
    {
        $this->verifiedOrder('critical');

        $this->generator->run();

        $this->assertDatabaseHas('clinical_tasks', [
            'type' => ClinicalTask::TYPE_CRITICAL_RESULT,
            'priority' => 'critical',
            'status' => ClinicalTask::STATUS_OPEN,
        ]);
    }

    public function test_running_the_generator_twice_does_not_duplicate_tasks(): void
    {
        $this->verifiedOrder();

        $this->generator->run();
        $this->generator->run();

        $this->assertSame(1, ClinicalTask::where('type', ClinicalTask::TYPE_PENDING_RESULT)->count());
    }

    public function test_a_task_closes_itself_once_the_results_have_been_read(): void
    {
        $order = $this->verifiedOrder();
        $this->generator->run();

        $order->update(['reviewed_by' => $this->doctor->id, 'reviewed_at' => now(), 'review_action' => 'acknowledged']);
        $this->generator->run();

        $this->assertDatabaseHas('clinical_tasks', [
            'type' => ClinicalTask::TYPE_PENDING_RESULT,
            'source_id' => $order->id,
            'status' => ClinicalTask::STATUS_DONE,
        ]);
    }

    public function test_opens_a_task_for_an_unread_imaging_report(): void
    {
        $order = RadiologyOrder::create([
            'visit_id' => $this->visit->id,
            'ordered_by' => $this->doctor->id,
            'modality' => 'xray',
            'body_part' => 'Chest',
            'priority' => 'routine',
            'status' => RadiologyOrder::STATUS_REPORTED,
            'impression' => 'Right lower lobe pneumonia.',
            'reported_at' => now()->subHour(),
            'is_critical' => true,
        ]);

        $this->generator->run();

        $this->assertDatabaseHas('clinical_tasks', [
            'type' => ClinicalTask::TYPE_UNREVIEWED_REPORT,
            'source_id' => $order->id,
            'priority' => 'critical',
        ]);
    }

    public function test_a_promised_follow_up_becomes_a_task_due_on_the_day(): void
    {
        $followUp = now()->addWeek()->startOfDay();

        VisitOutcome::create([
            'visit_id' => $this->visit->id,
            'outcome' => 'discharge',
            'follow_up_date' => $followUp->format('Y-m-d'),
            'doctor_id' => $this->doctor->id,
        ]);

        $this->generator->run();

        $task = ClinicalTask::where('type', ClinicalTask::TYPE_FOLLOW_UP)->sole();
        $this->assertSame($followUp->toDateString(), $task->due_at->toDateString());
        $this->assertSame($this->doctor->id, $task->assigned_to);
    }

    public function test_the_worklist_shows_a_clinicians_own_open_tasks(): void
    {
        $this->verifiedOrder();

        $response = $this->actingAs($this->doctor)->get('/tasks');

        $response->assertOk();
        $response->assertSee('Lab results awaiting review');
    }

    public function test_the_worklist_is_closed_to_users_without_the_permission(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get('/tasks')->assertForbidden();
    }

    public function test_a_task_can_be_closed_with_a_note(): void
    {
        $this->verifiedOrder();
        $this->generator->run();
        $task = ClinicalTask::where('type', ClinicalTask::TYPE_PENDING_RESULT)->sole();

        $this->actingAs($this->doctor)
            ->post("/tasks/{$task->id}/complete", ['completion_note' => 'Discussed with the family.'])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(ClinicalTask::STATUS_DONE, $task->status);
        $this->assertSame($this->doctor->id, $task->completed_by);
        $this->assertSame('Discussed with the family.', $task->completion_note);
    }

    public function test_an_already_closed_task_cannot_be_closed_again(): void
    {
        $this->verifiedOrder();
        $this->generator->run();
        $task = ClinicalTask::where('type', ClinicalTask::TYPE_PENDING_RESULT)->sole();
        $task->complete($this->doctor->id);

        $this->actingAs($this->doctor)->post("/tasks/{$task->id}/complete")->assertStatus(422);
    }

    public function test_an_overdue_task_is_reported_as_overdue(): void
    {
        $task = ClinicalTask::create([
            'patient_id' => $this->visit->patient_id,
            'visit_id' => $this->visit->id,
            'type' => ClinicalTask::TYPE_FOLLOW_UP,
            'title' => 'Follow-up due',
            'priority' => 'routine',
            'status' => ClinicalTask::STATUS_OPEN,
            'due_at' => now()->subDays(3),
        ]);

        $this->assertTrue($task->isOverdue());
        $this->assertSame(1, ClinicalTask::overdue()->count());
    }
}
