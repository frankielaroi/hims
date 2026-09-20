<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientIdentityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'field_name',
        'old_value',
        'new_value',
        'user_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
