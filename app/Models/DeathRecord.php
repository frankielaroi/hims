<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeathRecord extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const MANNERS = ['natural', 'accident', 'suicide', 'homicide', 'undetermined'];

    /**
     * Manners of death that must be referred to a coroner rather than certified
     * by the treating team alone.
     *
     * @var list<string>
     */
    public const CORONER_MANNERS = ['accident', 'suicide', 'homicide', 'undetermined'];

    /**
     * When, relative to the pregnancy, a maternal death occurred.
     *
     * @var list<string>
     */
    public const MATERNAL_TIMINGS = ['during_pregnancy', 'during_delivery', 'within_42_days'];

    /**
     * The WHO window: a death within 42 days of the end of a pregnancy is a
     * maternal death when it is related to or aggravated by the pregnancy.
     */
    public const MATERNAL_WINDOW_DAYS = 42;

    /**
     * Ghana requires a maternal death to be notified within this many hours.
     */
    public const NOTIFICATION_DEADLINE_HOURS = 24;

    /**
     * The database default does not reach a newly created instance, and a null
     * here would make the surveillance helpers read as "not yet decided" when
     * the answer is "nobody has said it is maternal".
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_maternal_death' => false,
    ];

    protected $fillable = [
        'visit_id',
        'patient_id',
        'died_at',
        'place_of_death',
        'immediate_cause',
        'underlying_cause',
        'contributing_factors',
        'manner',
        'is_maternal_death',
        'maternal_death_timing',
        'notified_at',
        'notified_by',
        'reviewed_at',
        'review_findings',
        'referred_to_coroner',
        'autopsy_requested',
        'certificate_number',
        'certified_by',
        'body_released_to',
        'body_released_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'died_at' => 'datetime',
            'body_released_at' => 'datetime',
            'notified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'referred_to_coroner' => 'boolean',
            'autopsy_requested' => 'boolean',
            'is_maternal_death' => 'boolean',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Whether this manner of death requires coroner referral.
     */
    public function requiresCoroner(): bool
    {
        return in_array($this->manner, self::CORONER_MANNERS, true);
    }

    public function notifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_by');
    }

    public function isNotified(): bool
    {
        return $this->notified_at !== null;
    }

    /**
     * Whether the 24-hour notification window has passed with nothing sent.
     */
    public function notificationIsOverdue(): bool
    {
        return $this->is_maternal_death
            && ! $this->isNotified()
            && $this->died_at->copy()->addHours(self::NOTIFICATION_DEADLINE_HOURS)->isPast();
    }

    public function hoursUntilNotificationDeadline(): ?float
    {
        if (! $this->is_maternal_death || $this->isNotified()) {
            return null;
        }

        $deadline = $this->died_at->copy()->addHours(self::NOTIFICATION_DEADLINE_HOURS);

        return round($deadline->isPast() ? 0.0 : now()->floatDiffInHours($deadline), 1);
    }

    /**
     * What this death still owes the surveillance system.
     *
     * @return list<string>
     */
    public function outstandingActions(): array
    {
        $actions = [];

        if ($this->is_maternal_death && ! $this->isNotified()) {
            $actions[] = $this->notificationIsOverdue()
                ? 'Maternal death NOT notified and the '.self::NOTIFICATION_DEADLINE_HOURS.'-hour window has passed — notify now'
                : 'Maternal death must be notified within '.self::NOTIFICATION_DEADLINE_HOURS.' hours ('.$this->hoursUntilNotificationDeadline().' hours left)';
        }

        if ($this->is_maternal_death && $this->reviewed_at === null) {
            $actions[] = 'Maternal death review outstanding';
        }

        if ($this->requiresCoroner() && ! $this->referred_to_coroner) {
            $actions[] = 'Manner of death requires coroner referral';
        }

        return $actions;
    }
}
