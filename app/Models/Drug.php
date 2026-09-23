<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'generic_name',
        'strength',
        'formulation',
        'drug_class',
        'max_daily_dose',
        'max_single_dose',
        'dose_unit',
        'paediatric_max_per_kg',
        'is_renally_cleared',
        'is_hepatically_cleared',
        'pregnancy_category',
        'pregnancy_guidance',
        'lactation_category',
        'lactation_guidance',
        'renal_guidance',
        'hepatic_guidance',
        'is_nhis_covered',
        'cash_price',
        'nhis_price',
        'stock_level',
        'is_active',
    ];

    protected $casts = [
        'is_nhis_covered' => 'boolean',
        'cash_price' => 'decimal:2',
        'nhis_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_renally_cleared' => 'boolean',
        'is_hepatically_cleared' => 'boolean',
    ];

    public function batches()
    {
        return $this->hasMany(DrugBatch::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Deduct quantity from the oldest-expiring batches first (FEFO) and record
     * a stock movement per batch drawn from. Returns the quantity actually deducted,
     * which will be less than $quantity if stock ran out.
     */
    public function deductStock(int $quantity, ?int $prescriptionItemId, int $performedBy): int
    {
        $remaining = $quantity;

        $batches = $this->batches()
            ->where('quantity_remaining', '>', 0)
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($batch->quantity_remaining, $remaining);
            $batch->decrement('quantity_remaining', $take);

            StockMovement::create([
                'drug_id' => $this->id,
                'drug_batch_id' => $batch->id,
                'movement_type' => 'dispense',
                'quantity_change' => -$take,
                'prescription_item_id' => $prescriptionItemId,
                'performed_by' => $performedBy,
            ]);

            $remaining -= $take;
        }

        $deducted = $quantity - $remaining;
        $this->decrement('stock_level', $deducted);

        return $deducted;
    }
}
