<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalTask extends Model
{
    use HasFactory;

    public const TYPE_PENDING_RESULT = 'pending_result';

    public const TYPE_CRITICAL_RESULT = 'critical_result';

    public const TYPE_UNREVIEWED_REPORT = 'unreviewed_report';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    public const PRIORITIES = ['routine', 'urgent', 'critical'];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'type',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'source_type',
        'source_id',
        'assigned_to',
        'completed_by',
        'completed_at',
        'completion_note',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @param  Builder<ClinicalTask>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Open tasks whose due date has passed.
     *
     * @param  Builder<ClinicalTask>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function complete(int $userId, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'completed_by' => $userId,
            'completed_at' => now(),
            'completion_note' => $note,
        ]);
    }

    /**
     * Ordering weight for a worklist: most serious first.
     */
    public function getPriorityRankAttribute(): int
    {
        return match ($this->priority) {
            'critical' => 0,
            'urgent' => 1,
            default => 2,
        };
    }
}
