<?php

namespace App\Services;

use App\Models\ClinicalTask;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\RadiologyOrder;
use App\Models\VisitOutcome;
use Illuminate\Support\Carbon;

/**
 * Derives outstanding clinical work from the state of the record.
 *
 * Tasks are keyed on their source row, so running this repeatedly reconciles
 * rather than duplicates: work that has since been done is closed, and work that
 * has appeared since the last run is opened.
 */
class ClinicalTaskGenerator
{
    /**
     * Reconcile every task type.
     *
     * @return array{opened: int, closed: int}
     */
    public function run(): array
    {
        $opened = 0;
        $closed = 0;

        foreach ([
            'syncCriticalResults',
            'syncUnreadLabResults',
            'syncUnreadImagingReports',
            'syncFollowUps',
        ] as $step) {
            $result = $this->{$step}();
            $opened += $result['opened'];
            $closed += $result['closed'];
        }

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * A critical result nobody has acknowledged is the most urgent thing in the
     * system, so it gets its own task per result rather than per order.
     *
     * @return array{opened: int, closed: int}
     */
    protected function syncCriticalResults(): array
    {
        $opened = 0;

        $items = LabOrderItem::with(['labOrder.visit.patient', 'labTest'])
            ->where('result_flag', 'critical')
            ->whereNull('critical_acknowledged_at')
            ->whereNotNull('authorized_at')
            ->get();

        foreach ($items as $item) {
            $visit = $item->labOrder?->visit;

            if ($visit === null) {
                continue;
            }

            $opened += $this->openTask(
                type: ClinicalTask::TYPE_CRITICAL_RESULT,
                sourceType: LabOrderItem::class,
                sourceId: $item->id,
                attributes: [
                    'patient_id' => $visit->patient_id,
                    'visit_id' => $visit->id,
                    'title' => 'Critical result: '.($item->labTest->name ?? 'laboratory test'),
                    'description' => 'Result '.$item->result_value.' flagged critical and not yet acknowledged.',
                    'priority' => 'critical',
                    'due_at' => now(),
                    'assigned_to' => $item->labOrder->ordered_by,
                ]
            );
        }

        $closed = $this->closeResolved(
            ClinicalTask::TYPE_CRITICAL_RESULT,
            LabOrderItem::query()
                ->where('result_flag', 'critical')
                ->whereNull('critical_acknowledged_at')
                ->pluck('id')
                ->all(),
            LabOrderItem::class
        );

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * @return array{opened: int, closed: int}
     */
    protected function syncUnreadLabResults(): array
    {
        $opened = 0;

        $orders = LabOrder::with('visit.patient')->awaitingReview()->get();

        foreach ($orders as $order) {
            if ($order->visit === null) {
                continue;
            }

            $opened += $this->openTask(
                type: ClinicalTask::TYPE_PENDING_RESULT,
                sourceType: LabOrder::class,
                sourceId: $order->id,
                attributes: [
                    'patient_id' => $order->visit->patient_id,
                    'visit_id' => $order->visit_id,
                    'title' => 'Lab results awaiting review',
                    'description' => 'Order #'.$order->id.' was verified on '
                        .($order->verified_at?->format('d M Y H:i') ?? 'an earlier date').' and has not been read.',
                    'priority' => $order->hasCriticalResult() ? 'urgent' : 'routine',
                    'due_at' => $order->verified_at?->addDay(),
                    'assigned_to' => $order->ordered_by,
                ]
            );
        }

        $closed = $this->closeResolved(
            ClinicalTask::TYPE_PENDING_RESULT,
            LabOrder::query()->awaitingReview()->pluck('id')->all(),
            LabOrder::class
        );

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * @return array{opened: int, closed: int}
     */
    protected function syncUnreadImagingReports(): array
    {
        $opened = 0;

        $orders = RadiologyOrder::with('visit.patient')->awaitingReview()->get();

        foreach ($orders as $order) {
            if ($order->visit === null) {
                continue;
            }

            $opened += $this->openTask(
                type: ClinicalTask::TYPE_UNREVIEWED_REPORT,
                sourceType: RadiologyOrder::class,
                sourceId: $order->id,
                attributes: [
                    'patient_id' => $order->visit->patient_id,
                    'visit_id' => $order->visit_id,
                    'title' => 'Imaging report awaiting review — '.$order->study_label,
                    'description' => $order->impression ?: 'A report has been filed and not yet read.',
                    'priority' => $order->is_critical ? 'critical' : 'routine',
                    'due_at' => $order->reported_at?->addDay(),
                    'assigned_to' => $order->ordered_by,
                ]
            );
        }

        $closed = $this->closeResolved(
            ClinicalTask::TYPE_UNREVIEWED_REPORT,
            RadiologyOrder::query()->awaitingReview()->pluck('id')->all(),
            RadiologyOrder::class
        );

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * Follow-ups promised at discharge. The task falls due on the follow-up date
     * itself, so it surfaces on the day rather than the moment it is promised.
     *
     * @return array{opened: int, closed: int}
     */
    protected function syncFollowUps(): array
    {
        $opened = 0;

        $outcomes = VisitOutcome::with('visit.patient')
            ->whereNotNull('follow_up_date')
            ->get();

        foreach ($outcomes as $outcome) {
            if ($outcome->visit === null) {
                continue;
            }

            $opened += $this->openTask(
                type: ClinicalTask::TYPE_FOLLOW_UP,
                sourceType: VisitOutcome::class,
                sourceId: $outcome->id,
                attributes: [
                    'patient_id' => $outcome->visit->patient_id,
                    'visit_id' => $outcome->visit_id,
                    'title' => 'Follow-up due',
                    'description' => 'Follow-up agreed at '.$outcome->outcome.' on visit '.$outcome->visit->visit_number.'.',
                    'priority' => 'routine',
                    'due_at' => Carbon::parse($outcome->follow_up_date)->startOfDay(),
                    'assigned_to' => $outcome->doctor_id,
                ]
            );
        }

        return ['opened' => $opened, 'closed' => 0];
    }

    /**
     * Create the task if it is not already on the list. An existing open task is
     * left untouched so a clinician's own edits are not overwritten.
     *
     * @param  array<string, mixed>  $attributes
     * @return int 1 when a task was created, 0 otherwise
     */
    protected function openTask(string $type, string $sourceType, int $sourceId, array $attributes): int
    {
        $existing = ClinicalTask::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('type', $type)
            ->first();

        if ($existing !== null) {
            return 0;
        }

        ClinicalTask::create(array_merge($attributes, [
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => ClinicalTask::STATUS_OPEN,
        ]));

        return 1;
    }

    /**
     * Close open tasks of this type whose underlying work is no longer outstanding.
     *
     * @param  list<int>  $stillOutstandingIds
     */
    protected function closeResolved(string $type, array $stillOutstandingIds, string $sourceType): int
    {
        return ClinicalTask::open()
            ->where('type', $type)
            ->where('source_type', $sourceType)
            ->when(
                $stillOutstandingIds !== [],
                fn ($query) => $query->whereNotIn('source_id', $stillOutstandingIds)
            )
            ->update([
                'status' => ClinicalTask::STATUS_DONE,
                'completed_at' => now(),
                'completion_note' => 'Closed automatically: the underlying work was completed.',
            ]);
    }
}
