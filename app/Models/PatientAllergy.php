<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    use HasFactory;

    /**
     * Severities in descending clinical seriousness. `unknown` is used for
     * allergens migrated from the legacy free-text list, where no severity was captured.
     *
     * @var list<string>
     */
    public const SEVERITIES = ['life-threatening', 'severe', 'moderate', 'mild', 'unknown'];

    /**
     * @var list<string>
     */
    public const STATUSES = ['active', 'inactive', 'resolved', 'suspected'];

    protected $fillable = [
        'patient_id',
        'allergen',
        'reaction',
        'severity',
        'status',
        'recorded_by',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<PatientAllergy>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Whether prescribing against this allergen requires a written justification.
     */
    public function isCritical(): bool
    {
        return in_array(strtolower((string) $this->severity), ['severe', 'life-threatening'], true);
    }
}
