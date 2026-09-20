<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'full_name',
        'date_of_birth',
        'estimated_age',
        'is_age_estimated',
        'sex',
        'phone_number',
        'address',
        'next_of_kin_name',
        'next_of_kin_contact',
        'nhis_number',
        'nhis_expiry',
        'payment_method',
        'allergies',
        'chronic_conditions',
        'blood_group',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'nhis_expiry' => 'date',
        'is_age_estimated' => 'boolean',
        'allergies' => 'array',
        'chronic_conditions' => 'array',
    ];

    public function nhisHistory()
    {
        return $this->hasMany(PatientNhisHistory::class);
    }

    public function identityLogs()
    {
        return $this->hasMany(PatientIdentityLog::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function lastVisit()
    {
        return $this->hasOne(Visit::class)->latestOfMany();
    }

    public function getAgeAttribute()
    {
        if ($this->date_of_birth) {
            return $this->date_of_birth->age;
        }

        return $this->estimated_age;
    }
}
