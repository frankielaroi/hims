<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSample extends Model
{
    protected $fillable = [
        'lab_order_id',
        'barcode_number',
        'specimen_type',
        'collected_by',
        'collected_at',
        'received_at',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function items()
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
