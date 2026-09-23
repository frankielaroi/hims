<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\PostnatalVisit;
use Illuminate\Support\Carbon;

/**
 * Ghana's postnatal schedule: 6 hours, 6 days, 6 weeks.
 *
 * Most maternal and newborn deaths happen in the days right after birth, so a
 * contact that was missed matters as much as one that happened. Due dates are
 * worked out from the delivery rather than stored, so correcting the delivery
 * time moves the whole schedule with it.
 *
 * WHO's own model has four contacts and adds one at day 3; a facility following
 * that adds a row to CONTACTS rather than changing anything else.
 */
class PostnatalSchedule
{
    /**
     * @var list<array{number: int, label: string, hours: int}>
     */
    public const CONTACTS = [
        ['number' => 1, 'label' => '6 hours', 'hours' => 6],
        ['number' => 2, 'label' => '6 days', 'hours' => 144],
        ['number' => 3, 'label' => '6 weeks', 'hours' => 1008],
    ];

    public const STATUS_DONE = 'done';

    public const STATUS_DUE = 'due';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_NOT_YET_DUE = 'not_yet_due';

    /**
     * A contact counts as missed once this much time has passed beyond its due
     * point without it happening.
     */
    public const GRACE_HOURS = 24;

    /**
     * Every scheduled contact with when it is due and whether it happened.
     *
     * @return list<array{number: int, label: string, due_at: Carbon|null, seen_at: Carbon|null, status: string}>
     */
    public function statusFor(Delivery $delivery): array
    {
        $recorded = PostnatalVisit::where('delivery_id', $delivery->id)
            ->get()
            ->keyBy('contact_number');

        $deliveredAt = $delivery->delivered_at;

        return array_map(function (array $contact) use ($recorded, $deliveredAt) {
            $visit = $recorded->get($contact['number']);
            $dueAt = $deliveredAt?->copy()->addHours($contact['hours']);

            return [
                'number' => $contact['number'],
                'label' => $contact['label'],
                'due_at' => $dueAt,
                'seen_at' => $visit?->seen_at,
                'status' => $this->statusOf($visit, $dueAt),
            ];
        }, self::CONTACTS);
    }

    /**
     * Contacts that are due or already missed.
     *
     * @return list<array<string, mixed>>
     */
    public function outstandingFor(Delivery $delivery): array
    {
        return array_values(array_filter(
            $this->statusFor($delivery),
            fn (array $contact) => in_array($contact['status'], [self::STATUS_DUE, self::STATUS_OVERDUE], true)
        ));
    }

    /**
     * The next contact to record, or null when the schedule is complete.
     *
     * @return array<string, mixed>|null
     */
    public function nextContact(Delivery $delivery): ?array
    {
        foreach ($this->statusFor($delivery) as $contact) {
            if ($contact['status'] !== self::STATUS_DONE) {
                return $contact;
            }
        }

        return null;
    }

    public function isComplete(Delivery $delivery): bool
    {
        return $this->nextContact($delivery) === null;
    }

    private function statusOf(?PostnatalVisit $visit, ?Carbon $dueAt): string
    {
        if ($visit !== null) {
            return self::STATUS_DONE;
        }

        if ($dueAt === null || $dueAt->isFuture()) {
            return self::STATUS_NOT_YET_DUE;
        }

        return $dueAt->copy()->addHours(self::GRACE_HOURS)->isPast()
            ? self::STATUS_OVERDUE
            : self::STATUS_DUE;
    }
}
