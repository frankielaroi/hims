<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugBatch extends Model
{
    protected $fillable = [
        'drug_id',
        'batch_number',
        'expiry_date',
        'quantity_received',
        'quantity_remaining',
        'received_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'date',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 60): bool
    {
        return ! $this->isExpired() && $this->expiry_date->diffInDays(now()) <= $days;
    }
}
