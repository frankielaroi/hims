<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id',
        'ghana_card_pin',
        'full_name',
        'occupation',
        'religion',
        'marital_status',
        'is_unidentified',
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
        'is_unidentified' => 'boolean',
        'allergies' => 'array',
        'chronic_conditions' => 'array',
    ];

    public function chronicMedications()
    {
        return $this->hasMany(PatientChronicMedication::class);
    }

    public function structuredAllergies()
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function activeAllergies()
    {
        return $this->hasMany(PatientAllergy::class)->where('status', 'active');
    }

    public function activeChronicMedications()
    {
        return $this->hasMany(PatientChronicMedication::class)->where('is_active', true);
    }

    public function imciAssessments()
    {
        return $this->hasMany(ImciAssessment::class);
    }

    public function growthMeasurements()
    {
        return $this->hasMany(GrowthMeasurement::class);
    }

    public function immunizations()
    {
        return $this->hasMany(Immunization::class);
    }

    public function ancAssessments()
    {
        return $this->hasMany(AncAssessment::class);
    }

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

    public static function generateHospitalId(): string
    {
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;

        return "HIMS-{$year}-".str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
