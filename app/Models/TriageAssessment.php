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
        'nurse_id',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }
}
