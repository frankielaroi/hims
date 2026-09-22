<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationAmendment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'consultation_id',
        'user_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
