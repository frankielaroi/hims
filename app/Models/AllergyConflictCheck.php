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
        'match_type',
        'severity',
        'was_overridden',
        'doctor_justification',
        'doctor_id',
    ];

    protected function casts(): array
    {
        return [
            'was_overridden' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
