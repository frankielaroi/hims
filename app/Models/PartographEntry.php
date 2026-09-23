<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartographEntry extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const LIQUOR_STATES = ['intact', 'clear', 'meconium', 'blood_stained', 'absent'];

    /**
     * @var list<string>
     */
    public const MOULDING_GRADES = ['none', 'plus', 'plus_plus', 'plus_plus_plus'];

    /**
     * @var list<string>
     */
    public const CONTRACTION_STRENGTHS = ['mild', 'moderate', 'strong'];

    /**
     * The normal fetal heart rate window. Outside it is a warning sign.
     */
    public const FHR_MIN = 110;

    public const FHR_MAX = 160;

    protected $fillable = [
        'delivery_id',
        'recorded_at',
        'cervical_dilatation_cm',
        'descent_fifths',
        'contractions_per_ten_minutes',
        'contraction_strength',
        'fetal_heart_rate',
        'liquor',
        'moulding',
        'maternal_pulse',
        'systolic_bp',
        'diastolic_bp',
        'temperature',
        'urine_protein',
        'oxytocin_units',
        'drugs_given',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Observations at this reading that need acting on.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->fetal_heart_rate !== null
            && ($this->fetal_heart_rate < self::FHR_MIN || $this->fetal_heart_rate > self::FHR_MAX)) {
            $warnings[] = 'Fetal heart rate outside '.self::FHR_MIN.'-'.self::FHR_MAX.' bpm ('.$this->fetal_heart_rate.')';
        }

        if ($this->liquor === 'meconium') {
            $warnings[] = 'Meconium-stained liquor';
        }

        if ($this->moulding === 'plus_plus_plus') {
            $warnings[] = 'Severe moulding (+++) — suspect obstruction';
        }

        if ($this->temperature !== null && (float) $this->temperature >= 38.0) {
            $warnings[] = 'Maternal fever ('.$this->temperature.'°C)';
        }

        if ($this->systolic_bp !== null && $this->systolic_bp >= 140) {
            $warnings[] = 'Raised maternal blood pressure ('.$this->systolic_bp.' mmHg systolic)';
        }

        return $warnings;
    }
}
