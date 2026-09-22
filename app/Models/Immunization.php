<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Immunization extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'vaccine_code',
        'vaccine_name',
        'dose_number',
        'given_at',
        'batch_number',
        'site',
        'adverse_reaction',
        'given_by',
    ];

    protected function casts(): array
    {
        return [
            'given_at' => 'datetime',
            'dose_number' => 'integer',
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

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
