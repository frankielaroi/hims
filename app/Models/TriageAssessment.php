<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TriageAssessment extends Model
{
    protected $fillable = [
        'visit_id',
        'chief_complaint',
        'history_presenting_illness',
        'priority_level',
        'etat_emergency_signs',
        'etat_priority_signs',
        'nurse_id',
    ];

    protected function casts(): array
    {
        return [
            'etat_emergency_signs' => 'array',
            'etat_priority_signs' => 'array',
        ];
    }

    public function hasEtatEmergencySign(): bool
    {
        return ! empty($this->etat_emergency_signs);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
