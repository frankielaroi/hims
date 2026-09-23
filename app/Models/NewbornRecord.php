<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewbornRecord extends Model
{
    use HasFactory;

    public const OUTCOME_LIVE_BIRTH = 'live_birth';

    public const OUTCOME_FRESH_STILLBIRTH = 'fresh_stillbirth';

    public const OUTCOME_MACERATED_STILLBIRTH = 'macerated_stillbirth';

    /**
     * @var list<string>
     */
    public const OUTCOMES = [
        self::OUTCOME_LIVE_BIRTH,
        self::OUTCOME_FRESH_STILLBIRTH,
        self::OUTCOME_MACERATED_STILLBIRTH,
    ];

    /**
     * Below this is low birth weight, which decides who needs kangaroo care and
     * closer follow-up.
     */
    public const LOW_BIRTH_WEIGHT_GRAMS = 2500;

    public const VERY_LOW_BIRTH_WEIGHT_GRAMS = 1500;

    /**
     * An Apgar below this at five minutes means the baby needs continued
     * support and a ten-minute score.
     */
    public const LOW_APGAR = 7;

    protected $fillable = [
        'delivery_id',
        'patient_id',
        'birth_order',
        'sex',
        'birth_weight_grams',
        'apgar_one_minute',
        'apgar_five_minutes',
        'apgar_ten_minutes',
        'outcome',
        'resuscitation_required',
        'resuscitation_detail',
        'breastfed_within_hour',
        'vitamin_k_given',
        'eye_prophylaxis_given',
        'congenital_anomaly',
        'died_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'resuscitation_required' => 'boolean',
            'breastfed_within_hour' => 'boolean',
            'vitamin_k_given' => 'boolean',
            'eye_prophylaxis_given' => 'boolean',
            'died_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * The baby's own record in the registry, once registered.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isStillbirth(): bool
    {
        return in_array($this->outcome, [
            self::OUTCOME_FRESH_STILLBIRTH,
            self::OUTCOME_MACERATED_STILLBIRTH,
        ], true);
    }

    public function isLiveBirth(): bool
    {
        return $this->outcome === self::OUTCOME_LIVE_BIRTH;
    }

    public function isLowBirthWeight(): bool
    {
        return $this->birth_weight_grams !== null
            && $this->birth_weight_grams < self::LOW_BIRTH_WEIGHT_GRAMS;
    }

    /**
     * What this baby needs attention for.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        if ($this->isStillbirth()) {
            return ['Stillbirth ('.str_replace('_', ' ', $this->outcome).') — record the cause and notify'];
        }

        $alerts = [];

        if ($this->birth_weight_grams !== null && $this->birth_weight_grams < self::VERY_LOW_BIRTH_WEIGHT_GRAMS) {
            $alerts[] = 'Very low birth weight ('.$this->birth_weight_grams.' g) — needs neonatal care';
        } elseif ($this->isLowBirthWeight()) {
            $alerts[] = 'Low birth weight ('.$this->birth_weight_grams.' g) — start kangaroo mother care';
        }

        if ($this->apgar_five_minutes !== null && $this->apgar_five_minutes < self::LOW_APGAR) {
            $alerts[] = 'Apgar '.$this->apgar_five_minutes.' at five minutes — continue support and score again at ten minutes';
        }

        if (! $this->breastfed_within_hour) {
            $alerts[] = 'Not breastfed within the first hour';
        }

        if (! $this->vitamin_k_given) {
            $alerts[] = 'Vitamin K not given';
        }

        if (! $this->eye_prophylaxis_given) {
            $alerts[] = 'Eye prophylaxis not given';
        }

        if ($this->patient_id === null) {
            $alerts[] = 'Baby not yet registered — no record to attach immunisation or growth to';
        }

        if ($this->congenital_anomaly) {
            $alerts[] = 'Congenital anomaly noted';
        }

        return $alerts;
    }
}
