<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    protected $fillable = [
        'visit_id',
        'bed_id',
        'admitting_diagnosis',
        'admitted_by',
        'admission_date',
        'status',
        'discharge_date',
        'discharge_notes',
        'discharged_by',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function transfers()
    {
        return $this->hasMany(BedTransfer::class);
    }

    public function lengthOfStayDays(): int
    {
        $end = $this->discharge_date ?? now();

        return max(1, (int) $this->admission_date->diffInDays($end, absolute: true) + 1);
    }
}
