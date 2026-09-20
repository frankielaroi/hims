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
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_nhis_covered' => 'boolean',
        'is_active' => 'boolean',
    ];
}
