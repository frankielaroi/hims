<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitOutcome extends Model
{
    protected $fillable = [
        'visit_id',
        'outcome',
        'follow_up_date',
        'referral_destination',
        'outcome_notes',
        'doctor_id',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
