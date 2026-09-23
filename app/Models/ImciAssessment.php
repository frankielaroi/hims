<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImciAssessment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const SKIN_PINCH = ['normal', 'slow', 'very_slow'];

    /**
     * @var list<string>
     */
    public const DRINKING = ['normal', 'eagerly', 'poorly', 'not_able'];

    /**
     * @var list<string>
     */
    public const MALARIA_RESULTS = ['positive', 'negative', 'not_done'];

    /**
     * @var list<string>
     */
    public const PALLOR = ['none', 'some', 'severe'];

    protected $fillable = [
        'patient_id',
        'visit_id',
        'consultation_id',
        'age_months',
        'unable_to_drink',
        'vomits_everything',
        'convulsions',
        'lethargic_or_unconscious',
        'convulsing_now',
        'has_cough',
        'cough_days',
        'respiratory_rate',
        'chest_indrawing',
        'stridor',
        'has_diarrhoea',
        'diarrhoea_days',
        'blood_in_stool',
        'sunken_eyes',
        'skin_pinch',
        'restless_or_irritable',
        'drinking',
        'has_fever',
        'fever_days',
        'malaria_test_result',
        'stiff_neck',
        'measles_rash',
        'has_ear_problem',
        'ear_pain',
        'ear_discharge_days',
        'tender_swelling_behind_ear',
        'muac_cm',
        'oedema_both_feet',
        'visible_severe_wasting',
        'palmar_pallor',
        'classifications',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'unable_to_drink' => 'boolean',
            'vomits_everything' => 'boolean',
            'convulsions' => 'boolean',
            'lethargic_or_unconscious' => 'boolean',
            'convulsing_now' => 'boolean',
            'has_cough' => 'boolean',
            'chest_indrawing' => 'boolean',
            'stridor' => 'boolean',
            'has_diarrhoea' => 'boolean',
            'blood_in_stool' => 'boolean',
            'sunken_eyes' => 'boolean',
            'restless_or_irritable' => 'boolean',
            'has_fever' => 'boolean',
            'stiff_neck' => 'boolean',
            'measles_rash' => 'boolean',
            'has_ear_problem' => 'boolean',
            'ear_pain' => 'boolean',
            'tender_swelling_behind_ear' => 'boolean',
            'oedema_both_feet' => 'boolean',
            'visible_severe_wasting' => 'boolean',
            'classifications' => 'array',
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
     * @return list<array<string, string>>
     */
    public function pinkClassifications(): array
    {
        return array_values(array_filter(
            (array) $this->classifications,
            fn (array $row) => ($row['colour'] ?? null) === 'pink'
        ));
    }

    public function needsUrgentReferral(): bool
    {
        return $this->pinkClassifications() !== [];
    }
}
