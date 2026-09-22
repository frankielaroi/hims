<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadiologyOrder extends Model
{
    use HasFactory;

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PERFORMED = 'performed';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses that mean the imaging department has finished with an order.
     *
     * @var list<string>
     */
    public const RESOLVED_STATUSES = [self::STATUS_REVIEWED, self::STATUS_CANCELLED];

    /**
     * @var list<string>
     */
    public const MODALITIES = ['xray', 'ultrasound', 'ct', 'mri', 'fluoroscopy'];

    /**
     * @var list<string>
     */
    public const PRIORITIES = ['routine', 'urgent', 'stat'];

    protected $fillable = [
        'visit_id',
        'consultation_id',
        'ordered_by',
        'modality',
        'body_part',
        'clinical_indication',
        'priority',
        'status',
        'contrast_required',
        'performed_by',
        'performed_at',
        'findings',
        'impression',
        'is_critical',
        'reported_by',
        'reported_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'contrast_required' => 'boolean',
            'is_critical' => 'boolean',
            'performed_at' => 'datetime',
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RadiologyAttachment::class);
    }

    /**
     * @param  Builder<RadiologyOrder>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNotIn('status', self::RESOLVED_STATUSES);
    }

    /**
     * Reported studies the ordering clinician has not read yet.
     *
     * @param  Builder<RadiologyOrder>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('status', self::STATUS_REPORTED)->whereNull('reviewed_at');
    }

    public function isReported(): bool
    {
        return $this->reported_at !== null;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function getStudyLabelAttribute(): string
    {
        return strtoupper($this->modality).' — '.$this->body_part;
    }
}
