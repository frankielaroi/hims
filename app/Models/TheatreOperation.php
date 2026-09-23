<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TheatreOperation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const URGENCIES = ['elective', 'urgent', 'emergency'];

    /**
     * @var list<string>
     */
    public const ANAESTHESIA_TYPES = ['spinal', 'general', 'epidural', 'local', 'ketamine'];

    /**
     * @var list<string>
     */
    public const COMPLICATIONS = [
        'haemorrhage',
        'anaesthetic_complication',
        'bladder_injury',
        'bowel_injury',
        'hysterectomy',
        'return_to_theatre',
        'wound_infection',
    ];

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_THEATRE = 'in_theatre';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Blood loss past this at caesarean is haemorrhage.
     */
    public const CAESAREAN_HAEMORRHAGE_ML = 1000;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'delivery_id',
        'procedure',
        'indication',
        'urgency',
        'anaesthesia_type',
        'started_at',
        'ended_at',
        'surgeon_id',
        'anaesthetist_id',
        'scrub_nurse',
        'findings',
        'procedure_notes',
        'complications',
        'estimated_blood_loss_ml',
        'specimen_sent',
        'swab_count_correct',
        'status',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'complications' => 'array',
            'specimen_sent' => 'boolean',
            'swab_count_correct' => 'boolean',
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

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function anaesthetist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anaesthetist_id');
    }

    public function durationMinutes(): ?int
    {
        if ($this->started_at === null || $this->ended_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    /**
     * What this operation record still needs, and what went wrong.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];

        // A swab left inside a patient is a never-event. An unrecorded count is
        // not the same as a correct one.
        if ($this->status === self::STATUS_COMPLETED && $this->swab_count_correct === null) {
            $alerts[] = 'Swab and instrument count not recorded';
        } elseif ($this->swab_count_correct === false) {
            $alerts[] = 'Swab and instrument count INCORRECT — account for every item before the patient leaves theatre';
        }

        if ($this->estimated_blood_loss_ml !== null
            && $this->estimated_blood_loss_ml >= self::CAESAREAN_HAEMORRHAGE_ML) {
            $alerts[] = 'Haemorrhage ('.$this->estimated_blood_loss_ml.' ml)';
        }

        if ($this->status === self::STATUS_COMPLETED && $this->surgeon_id === null) {
            $alerts[] = 'No surgeon named on a completed operation';
        }

        if ($this->status === self::STATUS_COMPLETED && $this->anaesthetist_id === null) {
            $alerts[] = 'No anaesthetist named on a completed operation';
        }

        foreach ((array) $this->complications as $complication) {
            $alerts[] = 'Complication: '.ucfirst(str_replace('_', ' ', $complication));
        }

        return $alerts;
    }
}
