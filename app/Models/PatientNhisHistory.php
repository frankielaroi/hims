<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNhisHistory extends Model
{
    protected $table = 'patient_nhis_history';

    protected $fillable = [
        'patient_id',
        'nhis_number',
        'expiry_date',
        'recorded_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
