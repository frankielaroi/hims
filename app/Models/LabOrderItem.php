<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabOrderItem extends Model
{
    protected $fillable = [
        'lab_order_id',
        'lab_test_id',
        'lab_sample_id',
        'specimen_type',
        'specimen_status',
        'specimen_collected_by',
        'specimen_collected_at',
        'result_value',
        'result_flag',
        'entered_by',
        'reference_range',
        'performed_by',
        'performed_at',
        'authorized_by',
        'authorized_at',
        'lab_reagent_id',
        'critical_acknowledged_by',
        'critical_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'specimen_collected_at' => 'datetime',
            'performed_at' => 'datetime',
            'authorized_at' => 'datetime',
            'critical_acknowledged_at' => 'datetime',
        ];
    }

    public function isCritical(): bool
    {
        return $this->result_flag === 'critical';
    }

    public function needsCriticalAcknowledgement(): bool
    {
        return $this->isCritical() && $this->critical_acknowledged_at === null;
    }

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }

    public function labSample()
    {
        return $this->belongsTo(LabSample::class);
    }

    public function specimenCollectedBy()
    {
        return $this->belongsTo(User::class, 'specimen_collected_by');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function reagent()
    {
        return $this->belongsTo(LabReagent::class, 'lab_reagent_id');
    }

    public function criticalAcknowledgedBy()
    {
        return $this->belongsTo(User::class, 'critical_acknowledged_by');
    }
}
