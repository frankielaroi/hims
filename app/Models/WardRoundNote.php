<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardRoundNote extends Model
{
    use HasFactory;

    /**
     * An admitted patient is expected to be seen at least daily. Longer than
     * this without a note is a gap the ward should be able to explain.
     */
    public const EXPECTED_INTERVAL_HOURS = 24;

    protected $fillable = [
        'admission_id',
        'rounded_at',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'seen_by_senior',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'rounded_at' => 'datetime',
            'seen_by_senior' => 'boolean',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
