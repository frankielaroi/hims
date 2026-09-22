<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    public const TYPE_PRIMARY = 'primary';

    public const TYPE_SECONDARY = 'secondary';

    public const TYPE_COMORBIDITY = 'comorbidity';

    public const TYPE_DIFFERENTIAL = 'differential';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_PRIMARY,
        self::TYPE_SECONDARY,
        self::TYPE_COMORBIDITY,
        self::TYPE_DIFFERENTIAL,
    ];

    /**
     * @var list<string>
     */
    public const CERTAINTIES = ['confirmed', 'probable', 'possible', 'rule-out'];

    protected $fillable = [
        'consultation_id',
        'icd10_code',
        'description',
        'type',
        'certainty',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function icd10(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class, 'icd10_code', 'code');
    }
}
