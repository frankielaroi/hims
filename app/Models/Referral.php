<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'visit_id',
        'type',
        'from_department_id',
        'to_department_id',
        'external_facility_name',
        'external_facility_contact',
        'reason',
        'urgency',
        'status',
        'referred_by',
        'accepted_by',
        'accepted_at',
        'completed_at',
        'feedback_notes',
        'new_visit_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function newVisit()
    {
        return $this->belongsTo(Visit::class, 'new_visit_id');
    }
}
