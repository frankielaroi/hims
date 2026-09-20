<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'visit_id',
        'status',
        'subtotal',
        'nhis_amount',
        'patient_amount',
        'amount_paid',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'nhis_amount' => 'decimal:2',
        'patient_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function balanceDue(): float
    {
        return round($this->patient_amount - $this->amount_paid, 2);
    }

    /**
     * Add a charge line to the visit's invoice, splitting it between NHIS and
     * the patient based on the visit's payment method and whether this
     * particular item is NHIS-covered (per Ghana_HMS_System_Design_v2 §5.3:
     * a single visit can mix NHIS-covered and cash-payable items).
     */
    public static function addCharge(Visit $visit, string $description, string $category, float $unitPrice, bool $isNhisCovered, int $quantity = 1): InvoiceItem
    {
        $invoice = static::firstOrCreate(['visit_id' => $visit->id]);

        $total = round($unitPrice * $quantity, 2);
        $payer = ($visit->payment_method === 'nhis' && $isNhisCovered) ? 'nhis' : 'cash';

        $item = $invoice->items()->create([
            'description' => $description,
            'category' => $category,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $total,
            'payer' => $payer,
            'nhis_amount' => $payer === 'nhis' ? $total : 0,
            'patient_amount' => $payer === 'cash' ? $total : 0,
        ]);

        $invoice->recalculateTotals();

        return $item;
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_price');
        $this->nhis_amount = $this->items()->sum('nhis_amount');
        $this->patient_amount = $this->items()->sum('patient_amount');
        $this->amount_paid = $this->payments()->sum('amount');

        $this->status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->amount_paid >= $this->patient_amount => 'paid',
            $this->amount_paid > 0 => 'partially_paid',
            default => 'open',
        };

        $this->save();
    }
}
