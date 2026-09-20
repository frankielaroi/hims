<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrderItem extends Model
{
    protected $fillable = [
        'lab_order_id',
        'lab_test_id',
        'specimen_type',
        'specimen_status',
        'specimen_collected_by',
        'specimen_collected_at',
        'result_value',
        'result_flag',
        'reference_range',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'specimen_collected_at' => 'datetime',
        'performed_at' => 'datetime',
    ];

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }

    public function specimenCollectedBy()
    {
        return $this->belongsTo(User::class, 'specimen_collected_by');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
