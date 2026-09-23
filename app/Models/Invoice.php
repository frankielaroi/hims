<?php

namespace App\Models;

use App\Services\NhisExemption;
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

    /**
     * Only money that actually arrived. A pending mobile money authorisation is
     * not payment, and counting it would release goods and services against a
     * transaction the patient has not approved yet.
     */
    public function settledPayments()
    {
        return $this->hasMany(Payment::class)->settled();
    }

    public function claim()
    {
        return $this->hasOne(NhisClaim::class);
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
    public static function addCharge(Visit $visit, string $description, string $category, float $unitPrice, bool $isNhisCovered, int $quantity = 1, ?float $nhisTariff = null): InvoiceItem
    {
        $invoice = static::firstOrCreate(['visit_id' => $visit->id]);

        $total = round($unitPrice * $quantity, 2);
        $payer = ($visit->payment_method === 'nhis' && $isNhisCovered) ? 'nhis' : 'cash';

        // NHIA reimburses at the G-DRG tariff, not at what the facility charges.
        // Where no tariff has been loaded the facility price stands in, so a
        // claim is never silently valued at zero.
        $claimable = round(($nhisTariff ?? $unitPrice) * $quantity, 2);

        // Ghana's exempt groups — pregnant women, under-fives, the elderly —
        // are exempt from NHIS premiums and covered by the scheme. That is not
        // the same as every service being free at the desk, so the exemption is
        // recorded and the line flagged, but the money is only waived where the
        // facility has said to (config/billing.php). Waiving by default would
        // quietly cost a facility its revenue.
        $exemption = app(NhisExemption::class)->applyTo($visit);
        $isExempt = $exemption !== null && $payer === 'cash';
        $isWaived = $isExempt && config('billing.waive_exempt_charges', false);

        $item = $invoice->items()->create([
            'description' => $description,
            'category' => $category,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $total,
            // `payer` stays cash/nhis: the exemption is expressed by is_exempt
            // and a zero patient amount, so existing claim queries keep working.
            'payer' => $payer,
            'is_exempt' => $isExempt,
            'exemption_reason' => $isExempt ? app(NhisExemption::class)->label($exemption) : null,
            'nhis_amount' => $payer === 'nhis' ? $claimable : 0,
            'patient_amount' => ($payer === 'cash' && ! $isWaived) ? $total : 0,
        ]);

        $invoice->recalculateTotals();

        return $item;
    }

    /**
     * What NHIA will actually reimburse, as opposed to what the facility
     * charges. Where no G-DRG tariff has been loaded for a line the facility
     * price stands in, so a claim is never silently valued at zero — but the
     * two numbers are not the same and should not be conflated.
     */
    public function claimableAmount(): float
    {
        return (float) $this->items()->where('payer', 'nhis')->sum('nhis_amount');
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('total_price');
        $this->nhis_amount = $this->items()->sum('nhis_amount');
        $this->patient_amount = $this->items()->sum('patient_amount');
        $this->amount_paid = $this->settledPayments()->sum('amount');

        $this->status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->amount_paid >= $this->patient_amount => 'paid',
            $this->amount_paid > 0 => 'partially_paid',
            default => 'open',
        };

        $this->save();
    }
}
