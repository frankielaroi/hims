<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'category',
        'quantity',
        'unit_price',
        'total_price',
        'payer',
        'is_exempt',
        'exemption_reason',
        'nhis_amount',
        'patient_amount',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'nhis_amount' => 'decimal:2',
        'patient_amount' => 'decimal:2',
        'is_exempt' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
