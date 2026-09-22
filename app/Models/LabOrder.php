<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    protected $fillable = [
        'visit_id',
        'consultation_id',
        'ordered_by',
        'priority',
        'clinical_indication',
        'status',
        'verified_by',
        'verified_at',
        'reviewed_by',
        'reviewed_at',
        'review_action',
        'review_note',
    ];

    /**
     * Statuses that mean the laboratory has finished with an order.
     *
     * @var list<string>
     */
    public const RESOLVED_STATUSES = ['verified', 'cancelled'];

    /**
     * @var list<string>
     */
    public const REVIEW_ACTIONS = ['acknowledged', 'repeat', 'escalated', 'treatment_changed'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Orders the laboratory has not finished with yet.
     *
     * @param  Builder<LabOrder>  $query
     */
    public function scopeOutstanding($query): void
    {
        $query->whereNotIn('status', self::RESOLVED_STATUSES);
    }

    /**
     * Verified orders whose results no clinician has read yet.
     *
     * @param  Builder<LabOrder>  $query
     */
    public function scopeAwaitingReview($query): void
    {
        $query->where('status', 'verified')->whereNull('reviewed_at');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * Whether any result on this order came back critical.
     */
    public function hasCriticalResult(): bool
    {
        return $this->items()->where('result_flag', 'critical')->exists();
    }

    /**
     * Critical results still waiting for a named clinician to acknowledge them.
     *
     * @return Collection<int, LabOrderItem>
     */
    public function unacknowledgedCriticalItems()
    {
        return $this->items()
            ->where('result_flag', 'critical')
            ->whereNull('critical_acknowledged_at')
            ->get();
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items()
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function samples()
    {
        return $this->hasMany(LabSample::class);
    }

    public function allResultsAuthorized(): bool
    {
        return $this->items()->whereNull('authorized_at')->doesntExist();
    }
}
