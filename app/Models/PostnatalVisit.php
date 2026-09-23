<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostnatalVisit extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const LOCHIA_STATES = ['normal', 'heavy', 'offensive', 'absent'];

    /**
     * @var list<string>
     */
    public const UTERUS_STATES = ['well_contracted', 'poorly_contracted', 'tender'];

    /**
     * @var list<string>
     */
    public const PERINEUM_STATES = ['healing', 'infected', 'breakdown', 'not_applicable'];

    /**
     * @var list<string>
     */
    public const BREAST_STATES = ['normal', 'engorged', 'cracked_nipples', 'mastitis'];

    /**
     * @var list<string>
     */
    public const EMOTIONAL_STATES = ['well', 'low_mood', 'very_distressed'];

    /**
     * Fever at or above this after delivery is puerperal sepsis until proven
     * otherwise.
     */
    public const FEVER_TEMPERATURE = 38.0;

    public const HYPERTENSION_SYSTOLIC = 140;

    public const HYPERTENSION_DIASTOLIC = 90;

    public const ANAEMIA_HB = 11.0;

    protected $fillable = [
        'delivery_id',
        'patient_id',
        'visit_id',
        'contact_number',
        'seen_at',
        'temperature',
        'pulse',
        'systolic_bp',
        'diastolic_bp',
        'haemoglobin',
        'lochia',
        'uterus_involution',
        'perineum_state',
        'breast_state',
        'emotional_wellbeing',
        'family_planning_counselled',
        'family_planning_method',
        'ifa_given',
        'vitamin_a_given',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
            'family_planning_counselled' => 'boolean',
            'ifa_given' => 'boolean',
            'vitamin_a_given' => 'boolean',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function newbornChecks(): HasMany
    {
        return $this->hasMany(PostnatalNewbornCheck::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Findings at this contact that need acting on.
     *
     * @return list<string>
     */
    public function dangerSigns(): array
    {
        $signs = [];

        if ($this->temperature !== null && (float) $this->temperature >= self::FEVER_TEMPERATURE) {
            $signs[] = 'Fever ('.$this->temperature.'°C) — assess for puerperal sepsis';
        }

        if ($this->lochia === 'offensive') {
            $signs[] = 'Offensive lochia — assess for puerperal sepsis';
        }

        if ($this->lochia === 'heavy') {
            $signs[] = 'Heavy lochia — assess for secondary postpartum haemorrhage';
        }

        if ($this->uterus_involution === 'poorly_contracted') {
            $signs[] = 'Poorly contracted uterus — risk of postpartum haemorrhage';
        }

        if (($this->systolic_bp !== null && $this->systolic_bp >= self::HYPERTENSION_SYSTOLIC)
            || ($this->diastolic_bp !== null && $this->diastolic_bp >= self::HYPERTENSION_DIASTOLIC)) {
            $signs[] = 'Raised blood pressure — postpartum pre-eclampsia can present after delivery';
        }

        if (in_array($this->perineum_state, ['infected', 'breakdown'], true)) {
            $signs[] = 'Perineal wound '.$this->perineum_state;
        }

        if ($this->breast_state === 'mastitis') {
            $signs[] = 'Mastitis — treat and support continued feeding';
        }

        if ($this->haemoglobin !== null && (float) $this->haemoglobin < self::ANAEMIA_HB) {
            $signs[] = 'Anaemia (Hb '.$this->haemoglobin.' g/dL)';
        }

        if ($this->emotional_wellbeing === 'very_distressed') {
            $signs[] = 'Very distressed — screen for postnatal depression';
        } elseif ($this->emotional_wellbeing === 'low_mood') {
            $signs[] = 'Low mood — review at the next contact';
        }

        return $signs;
    }
}
