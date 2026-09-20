<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    protected $fillable = [
        'name',
        'generic_name',
        'strength',
        'formulation',
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
    ];
}
