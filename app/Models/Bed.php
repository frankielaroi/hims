<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $fillable = [
        'ward_id',
        'bed_number',
        'status',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function currentAdmission()
    {
        return $this->hasOne(Admission::class)->where('status', 'admitted');
    }
}
