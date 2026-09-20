<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhisClaim extends Model
{
    protected $fillable = [
        'invoice_id',
        'claim_number',
        'status',
        'amount_claimed',
        'amount_paid',
        'submitted_at',
        'vetted_at',
        'paid_at',
        'rejected_at',
        'rejected_reason',
        'created_by',
    ];

    protected $casts = [
        'amount_claimed' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'submitted_at' => 'datetime',
        'vetted_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(NhisClaimItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submit(): void
    {
        $this->update(['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function markVetted(): void
    {
        $this->update(['status' => 'vetted', 'vetted_at' => now()]);
    }

    public function markPaid(?float $amountPaid = null): void
    {
        $this->update([
            'status' => 'paid',
            'amount_paid' => $amountPaid ?? $this->amount_claimed,
            'paid_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_reason' => $reason]);
    }

    public function resubmit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);
    }
}
