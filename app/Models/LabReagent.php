<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabReagent extends Model
{
    protected $fillable = [
        'name',
        'code',
        'batch_number',
        'current_stock',
        'min_stock_level',
        'expiry_date',
        'unit',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(LabOrderItem::class);
    }
}
