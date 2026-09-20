<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'drug_id',
        'dose',
        'route',
        'frequency',
        'duration_days',
        'quantity_to_dispense',
        'quantity_dispensed',
        'nhis_covered_snapshot',
        'instructions',
    ];

    protected $casts = [
        'nhis_covered_snapshot' => 'boolean',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
