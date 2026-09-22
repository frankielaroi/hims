<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AncAssessment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const PRESENTATIONS = ['cephalic', 'breech', 'transverse', 'not_assessed'];

    /**
     * Blood pressure at or above this is hypertension in pregnancy and warrants
     * assessment for pre-eclampsia.
     */
    public const HYPERTENSION_SYSTOLIC = 140;

    public const HYPERTENSION_DIASTOLIC = 90;

    /**
     * Haemoglobin below this is anaemia in pregnancy.
     */
    public const ANAEMIA_HB = 11.0;

    /**
     * Urinalysis protein readings that are not significant.
     *
     * @var list<string>
     */
    protected const INSIGNIFICANT_PROTEIN = ['', 'nil', 'negative', 'trace'];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'consultation_id',
        'contact_number',
        'gestational_age_weeks',
        'fundal_height_cm',
        'fetal_heart_rate',
        'presentation',
        'fetal_movements',
        'maternal_weight_kg',
        'systolic_bp',
        'diastolic_bp',
        'haemoglobin',
        'urinalysis_protein',
        'tt_given',
        'iptp_given',
        'itn_given',
        'notes',
        'next_contact_due',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'tt_given' => 'boolean',
            'iptp_given' => 'boolean',
            'itn_given' => 'boolean',
            'next_contact_due' => 'date',
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

    public function isHypertensive(): bool
    {
        return ($this->systolic_bp !== null && $this->systolic_bp >= self::HYPERTENSION_SYSTOLIC)
            || ($this->diastolic_bp !== null && $this->diastolic_bp >= self::HYPERTENSION_DIASTOLIC);
    }

    public function hasSignificantProteinuria(): bool
    {
        if ($this->urinalysis_protein === null) {
            return false;
        }

        return ! in_array(strtolower(trim($this->urinalysis_protein)), self::INSIGNIFICANT_PROTEIN, true);
    }

    /**
     * Findings at this contact that need acting on.
     *
     * @return list<string>
     */
    public function dangerSigns(): array
    {
        $signs = [];

        if ($this->systolic_bp !== null && $this->systolic_bp >= self::HYPERTENSION_SYSTOLIC) {
            $signs[] = 'Raised systolic blood pressure ('.$this->systolic_bp.' mmHg)';
        }

        if ($this->diastolic_bp !== null && $this->diastolic_bp >= self::HYPERTENSION_DIASTOLIC) {
            $signs[] = 'Raised diastolic blood pressure ('.$this->diastolic_bp.' mmHg)';
        }

        if ($this->haemoglobin !== null && (float) $this->haemoglobin < self::ANAEMIA_HB) {
            $signs[] = 'Anaemia (Hb '.$this->haemoglobin.' g/dL)';
        }

        if ($this->hasSignificantProteinuria()) {
            $signs[] = 'Proteinuria ('.$this->urinalysis_protein.')';
        }

        if ($this->fetal_heart_rate !== null && ($this->fetal_heart_rate < 110 || $this->fetal_heart_rate > 160)) {
            $signs[] = 'Fetal heart rate outside 110-160 bpm ('.$this->fetal_heart_rate.')';
        }

        // Hypertension together with proteinuria is the pre-eclampsia pattern.
        if ($this->isHypertensive() && $this->hasSignificantProteinuria()) {
            $signs[] = 'Hypertension with proteinuria — assess for pre-eclampsia';
        }

        return $signs;
    }
}
