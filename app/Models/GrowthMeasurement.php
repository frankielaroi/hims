<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthMeasurement extends Model
{
    use HasFactory;

    /**
     * MUAC thresholds for acute malnutrition in children aged 6-59 months, as
     * used by the WHO/UNICEF community management guidance.
     */
    public const MUAC_SEVERE_CM = 11.5;

    public const MUAC_MODERATE_CM = 12.5;

    /**
     * Two weighings closer together than this say more about the scale and the
     * child's last feed than about growth, so they are not compared.
     */
    public const MIN_TREND_INTERVAL_DAYS = 14;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'measured_at',
        'age_months',
        'weight_kg',
        'height_cm',
        'head_circumference_cm',
        'muac_cm',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'age_months' => 'integer',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Body mass index, where both a weight and a height were recorded.
     */
    public function bmi(): ?float
    {
        if ($this->weight_kg === null || $this->height_cm === null || (float) $this->height_cm <= 0) {
            return null;
        }

        $metres = (float) $this->height_cm / 100;

        return round((float) $this->weight_kg / ($metres ** 2), 1);
    }

    /**
     * Acute malnutrition grade from mid-upper arm circumference.
     *
     * MUAC is only interpreted this way between 6 and 59 months; outside that
     * range this returns null rather than a misleading grade.
     */
    public function acuteMalnutritionGrade(): ?string
    {
        if ($this->muac_cm === null || $this->age_months === null) {
            return null;
        }

        if ($this->age_months < 6 || $this->age_months > 59) {
            return null;
        }

        $muac = (float) $this->muac_cm;

        return match (true) {
            $muac < self::MUAC_SEVERE_CM => 'severe',
            $muac < self::MUAC_MODERATE_CM => 'moderate',
            default => 'none',
        };
    }

    /**
     * The child's own trajectory against the measurement before this one.
     *
     * Weight loss, or no gain at all over a meaningful interval, is faltering
     * growth. This compares the child only with themselves — it is not a
     * percentile, and it does not need the WHO reference tables the module
     * deliberately does not carry.
     *
     * @return array{previous_weight_kg: float, change_kg: float, days: int, grade: string}|null
     */
    public function growthTrend(): ?array
    {
        if ($this->weight_kg === null) {
            return null;
        }

        $previous = static::where('patient_id', $this->patient_id)
            ->where('measured_at', '<', $this->measured_at)
            ->whereNotNull('weight_kg')
            ->orderByDesc('measured_at')
            ->first();

        if ($previous === null) {
            return null;
        }

        $days = (int) $previous->measured_at->diffInDays($this->measured_at);

        if ($days < self::MIN_TREND_INTERVAL_DAYS) {
            return null;
        }

        $change = round((float) $this->weight_kg - (float) $previous->weight_kg, 2);

        return [
            'previous_weight_kg' => (float) $previous->weight_kg,
            'change_kg' => $change,
            'days' => $days,
            'grade' => match (true) {
                $change < 0 => 'losing_weight',
                $change == 0.0 => 'static_weight',
                default => 'gaining',
            },
        ];
    }

    /**
     * What this measurement needs acting on.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];

        $grade = $this->acuteMalnutritionGrade();

        if ($grade === 'severe') {
            $alerts[] = 'Severe acute malnutrition by MUAC ('.$this->muac_cm.' cm) — admit to therapeutic feeding';
        } elseif ($grade === 'moderate') {
            $alerts[] = 'Moderate acute malnutrition by MUAC ('.$this->muac_cm.' cm) — start supplementary feeding';
        }

        $trend = $this->growthTrend();

        if ($trend !== null && $trend['grade'] === 'losing_weight') {
            $alerts[] = sprintf(
                'Faltering growth — lost %s kg in %d days (%s kg to %s kg)',
                number_format(abs($trend['change_kg']), 2),
                $trend['days'],
                $trend['previous_weight_kg'],
                $this->weight_kg
            );
        } elseif ($trend !== null && $trend['grade'] === 'static_weight') {
            $alerts[] = sprintf('Faltering growth — no weight gain in %d days', $trend['days']);
        }

        return $alerts;
    }
}
