<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'department_id',
        'visit_number',
        'queue_number',
        'type',
        'priority',
        'payment_method',
        'nhis_number_snapshot',
        'nhis_expiry_snapshot',
        'nhis_verified',
        'nhis_verified_by',
        'nhis_verified_at',
        'status',
        'created_by',
        'reason_for_visit',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'nhis_expiry_snapshot' => 'date',
        'nhis_verified' => 'boolean',
        'nhis_verified_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs()
    {
        return $this->hasMany(VisitStatusLog::class);
    }

    public function vitals()
    {
        return $this->hasMany(VitalsRecord::class);
    }

    public function latestVitals()
    {
        return $this->hasOne(VitalsRecord::class)->latestOfMany();
    }

    public function triageAssessments()
    {
        return $this->hasMany(TriageAssessment::class);
    }

    public function latestTriage()
    {
        return $this->hasOne(TriageAssessment::class)->latestOfMany();
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function pendingLabOrder()
    {
        return $this->hasOne(LabOrder::class)->whereNotIn('status', ['verified', 'cancelled'])->latestOfMany();
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function latestPrescription()
    {
        return $this->hasOne(Prescription::class)->latestOfMany();
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function nhisVerifiedBy()
    {
        return $this->belongsTo(User::class, 'nhis_verified_by');
    }

    public function admission()
    {
        return $this->hasOne(Admission::class);
    }

    public function updateStatus(string $newStatus)
    {
        $this->update(['status' => $newStatus]);

        VisitStatusLog::create([
            'visit_id' => $this->id,
            'status' => $newStatus,
            'changed_by' => auth()->id() ?? User::first()->id, // Fallback for seeds/CLI
        ]);
    }

    public static function nextQueueNumber(int $departmentId): int
    {
        return static::where('department_id', $departmentId)
            ->whereDate('created_at', date('Y-m-d'))
            ->count() + 1;
    }

    public static function generateVisitNumber(): string
    {
        $date = date('Ymd');
        $count = static::whereDate('created_at', date('Y-m-d'))->count() + 1;

        return "V-{$date}-".str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
