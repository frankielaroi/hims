<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllergyConflictCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'prescription_id',
        'allergy_found',
        'drug_prescribed',
        'doctor_justification',
        'doctor_id',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
