<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamaRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'reason_given',
        'risks_explained',
        'patient_has_capacity',
        'patient_signature_obtained',
        'witness_name',
        'witness_relationship',
        'counselled_by',
        'recorded_by',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_has_capacity' => 'boolean',
            'patient_signature_obtained' => 'boolean',
            'left_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function counselledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselled_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
