<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BedTransfer extends Model
{
    protected $fillable = [
        'admission_id',
        'from_bed_id',
        'to_bed_id',
        'reason',
        'transferred_by',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function fromBed()
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    public function toBed()
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
