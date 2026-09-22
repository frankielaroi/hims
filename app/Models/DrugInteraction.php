<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugInteraction extends Model
{
    use HasFactory;

    /**
     * Ordered from least to most serious.
     *
     * @var list<string>
     */
    public const SEVERITIES = ['minor', 'moderate', 'major', 'contraindicated'];

    /**
     * Severities a clinician may not prescribe through without a justification.
     *
     * @var list<string>
     */
    public const BLOCKING_SEVERITIES = ['major', 'contraindicated'];

    protected $fillable = [
        'drug_id',
        'drug_class',
        'interacts_with_drug_id',
        'interacts_with_class',
        'severity',
        'effect',
        'management',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function interactsWithDrug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'interacts_with_drug_id');
    }

    public function isBlocking(): bool
    {
        return in_array($this->severity, self::BLOCKING_SEVERITIES, true);
    }

    /**
     * Whether this rule matches an unordered pair of drugs. A rule stated one way
     * round applies both ways, so each side is tried against each drug.
     */
    public function matches(Drug $first, Drug $second): bool
    {
        return ($this->sideMatches($first, $this->drug_id, $this->drug_class)
                && $this->sideMatches($second, $this->interacts_with_drug_id, $this->interacts_with_class))
            || ($this->sideMatches($second, $this->drug_id, $this->drug_class)
                && $this->sideMatches($first, $this->interacts_with_drug_id, $this->interacts_with_class));
    }

    /**
     * Whether one side of the rule describes this drug.
     */
    protected function sideMatches(Drug $drug, ?int $drugId, ?string $class): bool
    {
        if ($drugId !== null) {
            return $drug->id === $drugId;
        }

        if ($class !== null && $drug->drug_class !== null) {
            return strtolower($drug->drug_class) === strtolower($class);
        }

        return false;
    }
}
