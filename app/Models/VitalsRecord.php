<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalsRecord extends Model
{
    protected $fillable = [
        'visit_id',
        'temperature',
        'systolic_bp',
        'diastolic_bp',
        'pulse_rate',
        'respiratory_rate',
        'spo2',
        'weight',
        'height',
        'bmi',
        'recorded_by',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($vitals) {
            if ($vitals->weight && $vitals->height) {
                // BMI = kg / (m^2)
                $heightInMeters = $vitals->height / 100;
                $vitals->bmi = round($vitals->weight / ($heightInMeters * $heightInMeters), 1);
            }
        });
    }
}
