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
}
