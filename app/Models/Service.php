<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['name', 'category', 'price', 'is_nhis_covered',
        'nhis_tariff', 'code', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_nhis_covered' => 'boolean',
        'is_active' => 'boolean',
    ];
}
