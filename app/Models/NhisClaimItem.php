<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhisClaimItem extends Model
{
    protected $fillable = [
        'nhis_claim_id',
        'invoice_item_id',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function claim()
    {
        return $this->belongsTo(NhisClaim::class, 'nhis_claim_id');
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
