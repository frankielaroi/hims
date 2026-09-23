<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    protected $fillable = [
        'name',
        'code',
        'category',
        'specimen_type',
        'reference_range',
        'turnaround_minutes',
        'price',
        'is_nhis_covered',
        'nhis_tariff',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'nhis_tariff' => 'decimal:2',
        'is_nhis_covered' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * What NHIA actually reimburses, which is the G-DRG tariff where one is
     * recorded and the facility price otherwise. Claiming the facility price
     * when a tariff exists is how claims fail to reconcile.
     */
    public function reimbursableAmount(): float
    {
        return (float) ($this->nhis_tariff ?? $this->price);
    }
}
