<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'drug_id',
        'drug_batch_id',
        'movement_type',
        'quantity_change',
        'prescription_item_id',
        'performed_by',
        'notes',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function batch()
    {
        return $this->belongsTo(DrugBatch::class, 'drug_batch_id');
    }

    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
