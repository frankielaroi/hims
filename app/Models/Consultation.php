<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Consultation extends Model
{
    use HasFactory;

    /**
     * Minutes an editing lock survives without a heartbeat.
     */
    public const LOCK_TIMEOUT_MINUTES = 3;

    /**
     * Seconds between client heartbeats. Comfortably inside the lock timeout so a
     * single dropped beat does not surrender the lock.
     */
    public const HEARTBEAT_SECONDS = 60;

    /**
     * Seconds between server-side draft autosaves.
     */
    public const AUTOSAVE_SECONDS = 30;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY_FOR_SIGN_OFF = 'ready_for_sign_off';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_FINALIZED = 'finalized';

    /**
     * Permitted status transitions. Amendments do not appear here because they
     * raise the version of a signed note rather than moving its status.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_QUEUED => [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS],
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_DRAFT],
        self::STATUS_IN_PROGRESS => [self::STATUS_DRAFT, self::STATUS_READY_FOR_SIGN_OFF, self::STATUS_SIGNED],
        self::STATUS_DRAFT => [self::STATUS_IN_PROGRESS, self::STATUS_DRAFT, self::STATUS_READY_FOR_SIGN_OFF, self::STATUS_SIGNED],
        self::STATUS_READY_FOR_SIGN_OFF => [self::STATUS_IN_PROGRESS, self::STATUS_DRAFT, self::STATUS_SIGNED],
        self::STATUS_SIGNED => [self::STATUS_FINALIZED],
        self::STATUS_FINALIZED => [],
    ];

    protected $fillable = [
        'visit_id',
        'doctor_id',
        'status',
        'version',
        'locked_by',
        'locked_at',
        'signed_by',
        'signed_at',
        'autosaved_at',
        'started_at',
        'finished_at',
        'pregnancy_status',
        'lmp',
        'edd',
        'gestational_age',
        'lactation_status',
        'renal_impairment',
        'hepatic_impairment',
        'egfr',
        'weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'locked_at' => 'datetime',
            'signed_at' => 'datetime',
            'autosaved_at' => 'datetime',
            'lmp' => 'date',
            'edd' => 'date',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ConsultationAmendment::class);
    }

    public function primaryDiagnosis(): HasMany
    {
        return $this->diagnoses()->where('type', Diagnosis::TYPE_PRIMARY);
    }

    /**
     * Whether the clinical note has been signed and is therefore append-only.
     */
    public function isSigned(): bool
    {
        return in_array($this->status, [self::STATUS_SIGNED, self::STATUS_FINALIZED], true);
    }

    /**
     * Whether the note may still be edited in place.
     */
    public function isEditable(): bool
    {
        return ! $this->isSigned();
    }

    /**
     * Whether an editing lock is currently held and still warm.
     */
    public function hasActiveLock(): bool
    {
        if ($this->locked_by === null || $this->locked_at === null) {
            return false;
        }

        return $this->locked_at->addMinutes(self::LOCK_TIMEOUT_MINUTES)->isFuture();
    }

    /**
     * Whether someone other than the given user holds a warm lock.
     */
    public function isLockedByAnother(?int $userId): bool
    {
        return $this->hasActiveLock() && $this->locked_by !== $userId;
    }

    /**
     * Take the editing lock for a user.
     */
    public function claimLock(int $userId): void
    {
        $this->update(['locked_by' => $userId, 'locked_at' => now()]);
    }

    /**
     * Keep a held lock warm. Returns false when the caller no longer holds it.
     */
    public function refreshLock(int $userId): bool
    {
        if ($this->locked_by !== $userId) {
            return false;
        }

        $this->update(['locked_at' => now()]);

        return true;
    }

    public function releaseLock(): void
    {
        $this->update(['locked_by' => null, 'locked_at' => null]);
    }

    /**
     * Move the consultation to a new lifecycle status, recording the transition.
     *
     * @throws RuntimeException when the transition is not permitted
     */
    public function transitionTo(string $status, ?string $reason = null): void
    {
        if ($this->status === $status && ! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            return;
        }

        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw new RuntimeException("Cannot move consultation #{$this->id} from {$this->status} to {$status}.");
        }

        $previous = $this->status;
        $this->update(['status' => $status]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'consultation_status_changed',
            'description' => "Consultation #{$this->id} moved from {$previous} to {$status}".($reason ? ": {$reason}" : '.'),
            'metadata' => [
                'consultation_id' => $this->id,
                'visit_id' => $this->visit_id,
                'from' => $previous,
                'to' => $status,
                'version' => $this->version,
            ],
        ]);
    }

    /**
     * Reasons this consultation cannot be signed yet.
     *
     * @return list<string>
     */
    public function signOffBlockers(): array
    {
        $blockers = [];

        if (trim((string) $this->notes()->value('content')) === '') {
            $blockers[] = 'A clinical note is required before sign-off.';
        }

        $primaryCount = $this->diagnoses()->where('type', Diagnosis::TYPE_PRIMARY)->count();

        if ($primaryCount === 0) {
            $blockers[] = 'Exactly one primary diagnosis is required before sign-off.';
        } elseif ($primaryCount > 1) {
            $blockers[] = 'Only one diagnosis may be marked primary.';
        }

        return $blockers;
    }

    /**
     * Sign the clinical note. The note becomes append-only: later changes go
     * through amendments, which raise the version.
     */
    public function sign(int $userId): void
    {
        $this->transitionTo(self::STATUS_SIGNED);
        $this->update(['signed_by' => $userId, 'signed_at' => now(), 'finished_at' => now()]);
    }

    /**
     * Record an amendment to a signed note. One amendment event raises the
     * version once, however many fields it touched.
     *
     * @param  array<string, array{0: string|null, 1: string|null}>  $changes  field => [old, new]
     * @return int the new version number
     */
    public function applyAmendment(int $userId, array $changes, string $reason): int
    {
        foreach ($changes as $field => [$oldValue, $newValue]) {
            $this->amendments()->create([
                'user_id' => $userId,
                'field_name' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
            ]);
        }

        $this->increment('version');

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'consultation_amended',
            'description' => "Consultation #{$this->id} amended to version {$this->version}.",
            'metadata' => [
                'consultation_id' => $this->id,
                'visit_id' => $this->visit_id,
                'version' => $this->version,
                'fields' => array_keys($changes),
                'reason' => $reason,
            ],
        ]);

        return $this->version;
    }

    /**
     * Human-readable status for queues and banners.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_READY_FOR_SIGN_OFF => 'Ready for Sign-off',
            self::STATUS_SIGNED => 'Signed',
            self::STATUS_FINALIZED => 'Finalized',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }
}
