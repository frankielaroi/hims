<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResuscitationRecord extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const ARREST_TYPES = ['cardiac', 'respiratory', 'cardiorespiratory'];

    /**
     * Shockable rhythms are the ones where a defibrillator changes the outcome,
     * which is why the initial rhythm is recorded rather than inferred.
     *
     * @var list<string>
     */
    public const SHOCKABLE_RHYTHMS = ['vf', 'pulseless_vt'];

    /**
     * @var array<string, string>
     */
    public const RHYTHMS = [
        'vf' => 'Ventricular fibrillation',
        'pulseless_vt' => 'Pulseless ventricular tachycardia',
        'pea' => 'Pulseless electrical activity',
        'asystole' => 'Asystole',
        'unknown' => 'Not determined',
    ];

    /**
     * @var array<string, string>
     */
    public const INTERVENTIONS = [
        'chest_compressions' => 'Chest compressions',
        'bag_mask' => 'Bag-mask ventilation',
        'intubation' => 'Intubation',
        'defibrillation' => 'Defibrillation',
        'adrenaline' => 'Adrenaline',
        'iv_access' => 'Intravenous access',
        'io_access' => 'Intraosseous access',
        'fluids' => 'Intravenous fluids',
        'glucose' => 'Glucose',
    ];

    public const OUTCOME_ROSC = 'rosc';

    public const OUTCOME_DIED = 'died';

    public const OUTCOME_TRANSFERRED = 'transferred';

    /**
     * @var list<string>
     */
    public const OUTCOMES = [self::OUTCOME_ROSC, self::OUTCOME_DIED, self::OUTCOME_TRANSFERRED];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'arrest_type',
        'was_witnessed',
        'initial_rhythm',
        'started_at',
        'ended_at',
        'interventions',
        'shocks_delivered',
        'adrenaline_doses',
        'outcome',
        'team_lead_id',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'was_witnessed' => 'boolean',
            'interventions' => 'array',
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

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function durationMinutes(): ?int
    {
        if ($this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public function isShockableRhythm(): bool
    {
        return in_array($this->initial_rhythm, self::SHOCKABLE_RHYTHMS, true);
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * @return list<string>
     */
    public function interventionLabels(): array
    {
        return array_map(
            fn (string $code) => self::INTERVENTIONS[$code] ?? $code,
            (array) $this->interventions
        );
    }

    /**
     * What the record is missing or what it shows went wrong. A resuscitation
     * is the most scrutinised half hour in a hospital, so gaps matter.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];

        if ($this->isOngoing()) {
            return ['Resuscitation still open — record the outcome when it ends'];
        }

        if ($this->team_lead_id === null) {
            $alerts[] = 'No team lead named';
        }

        $interventions = (array) $this->interventions;

        // A shockable rhythm where nobody defibrillated is the single most
        // important thing a review would look for.
        if ($this->isShockableRhythm() && ! in_array('defibrillation', $interventions, true)) {
            $alerts[] = 'Shockable rhythm recorded but no defibrillation — explain in the notes';
        }

        if ($this->outcome === null) {
            $alerts[] = 'No outcome recorded';
        }

        return $alerts;
    }
}
