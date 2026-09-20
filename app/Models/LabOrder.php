<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    protected $fillable = [
        'visit_id',
        'consultation_id',
        'ordered_by',
        'priority',
        'clinical_indication',
        'status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function orderedBy()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items()
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function allResultsRecorded(): bool
    {
        return $this->items()->whereNull('performed_at')->doesntExist();
    }
}
