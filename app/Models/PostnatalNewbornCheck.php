<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostnatalNewbornCheck extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const CORD_STATES = ['clean_dry', 'infected', 'bleeding'];

    /**
     * @var list<string>
     */
    public const FEEDING_STATES = ['exclusive_breastfeeding', 'mixed', 'not_feeding'];

    /**
     * WHO danger signs in a young infant. Any one means urgent referral.
     *
     * @var array<string, string>
     */
    public const DANGER_SIGNS = [
        'not_feeding' => 'Not feeding well',
        'convulsions' => 'Convulsions',
        'fast_breathing' => 'Fast breathing (60 or more per minute)',
        'chest_indrawing' => 'Severe chest indrawing',
        'movement_on_stimulation' => 'Moves only when stimulated',
        'fever' => 'Temperature 37.5°C or above',
        'hypothermia' => 'Temperature below 35.5°C',
    ];

    public const FEVER_TEMPERATURE = 37.5;

    public const HYPOTHERMIA_TEMPERATURE = 35.5;

    /**
     * Weight loss past this share of birth weight is not normal newborn loss.
     */
    public const SIGNIFICANT_WEIGHT_LOSS = 0.10;

    protected $fillable = [
        'postnatal_visit_id',
        'newborn_record_id',
        'weight_grams',
        'temperature',
        'cord_state',
        'feeding',
        'jaundice',
        'danger_signs',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'jaundice' => 'boolean',
            'danger_signs' => 'array',
        ];
    }

    public function postnatalVisit(): BelongsTo
    {
        return $this->belongsTo(PostnatalVisit::class);
    }

    public function newbornRecord(): BelongsTo
    {
        return $this->belongsTo(NewbornRecord::class);
    }

    /**
     * Everything wrong with this baby at this contact: the signs recorded by
     * hand, plus the ones the numbers show on their own.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];

        foreach ((array) $this->danger_signs as $sign) {
            $alerts[] = self::DANGER_SIGNS[$sign] ?? $sign;
        }

        if ($this->temperature !== null) {
            $temperature = (float) $this->temperature;

            if ($temperature >= self::FEVER_TEMPERATURE) {
                $alerts[] = 'Fever ('.$this->temperature.'°C)';
            } elseif ($temperature < self::HYPOTHERMIA_TEMPERATURE) {
                $alerts[] = 'Hypothermia ('.$this->temperature.'°C) — warm the baby now';
            }
        }

        if ($this->cord_state === 'infected') {
            $alerts[] = 'Infected cord — treat for omphalitis';
        }

        if ($this->cord_state === 'bleeding') {
            $alerts[] = 'Bleeding cord';
        }

        if ($this->feeding === 'not_feeding') {
            $alerts[] = 'Not feeding';
        }

        if ($this->jaundice) {
            $alerts[] = 'Jaundice — check bilirubin and the age it appeared';
        }

        $loss = $this->weightLossFraction();

        if ($loss !== null && $loss > self::SIGNIFICANT_WEIGHT_LOSS) {
            $alerts[] = sprintf(
                'Lost %s%% of birth weight — assess feeding',
                number_format($loss * 100, 1)
            );
        }

        return array_values(array_unique($alerts));
    }

    /**
     * How much of its birth weight this baby has lost, as a fraction. Null when
     * either weight is missing, or when the baby has gained.
     */
    public function weightLossFraction(): ?float
    {
        $birthWeight = $this->newbornRecord?->birth_weight_grams;

        if ($birthWeight === null || $this->weight_grams === null || $birthWeight <= 0) {
            return null;
        }

        $lost = $birthWeight - $this->weight_grams;

        return $lost > 0 ? $lost / $birthWeight : null;
    }
}
