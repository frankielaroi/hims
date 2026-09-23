<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESSFUL = 'successful';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUCCESSFUL,
        self::STATUS_FAILED,
        self::STATUS_REVERSED,
    ];

    /**
     * Mobile money providers a walk-in would use in Ghana.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['mtn', 'telecel', 'airteltigo'];

    /**
     * Cash and card are settled the moment they are taken at the desk. Mobile
     * money is not: the patient approves on their handset afterwards.
     *
     * @var list<string>
     */
    public const ASYNCHRONOUS_METHODS = ['mobile_money'];

    protected $fillable = [
        'invoice_id',
        'receipt_number',
        'amount',
        'method',
        'status',
        'provider',
        'reference_number',
        'payer_msisdn',
        'failure_reason',
        'confirmed_at',
        'received_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_SUCCESSFUL,
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Only money that actually arrived counts against an invoice.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeSettled($query): void
    {
        $query->where('status', self::STATUS_SUCCESSFUL);
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
