<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeathRecord extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const MANNERS = ['natural', 'accident', 'suicide', 'homicide', 'undetermined'];

    /**
     * Manners of death that must be referred to a coroner rather than certified
     * by the treating team alone.
     *
     * @var list<string>
     */
    public const CORONER_MANNERS = ['accident', 'suicide', 'homicide', 'undetermined'];

    protected $fillable = [
        'visit_id',
        'patient_id',
        'died_at',
        'place_of_death',
        'immediate_cause',
        'underlying_cause',
        'contributing_factors',
        'manner',
        'referred_to_coroner',
        'autopsy_requested',
        'certificate_number',
        'certified_by',
        'body_released_to',
        'body_released_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'died_at' => 'datetime',
            'body_released_at' => 'datetime',
            'referred_to_coroner' => 'boolean',
            'autopsy_requested' => 'boolean',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Whether this manner of death requires coroner referral.
     */
    public function requiresCoroner(): bool
    {
        return in_array($this->manner, self::CORONER_MANNERS, true);
    }
}
