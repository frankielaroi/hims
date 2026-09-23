<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Admission extends Model
{
    protected $fillable = [
        'visit_id',
        'bed_id',
        'admitting_diagnosis',
        'admitted_by',
        'admission_date',
        'status',
        'discharge_date',
        'discharge_notes',
        'discharged_by',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function transfers()
    {
        return $this->hasMany(BedTransfer::class);
    }

    public function roundNotes()
    {
        return $this->hasMany(WardRoundNote::class)->orderByDesc('rounded_at');
    }

    /**
     * Hours since this patient was last seen on a round, or null when nobody
     * has written a note yet.
     */
    public function hoursSinceLastRound(): ?float
    {
        $last = $this->roundNotes()->max('rounded_at');

        if ($last === null) {
            return null;
        }

        return round(Carbon::parse($last)->diffInMinutes(now()) / 60, 1);
    }

    /**
     * Whether this patient is overdue a ward round.
     *
     * An admission with no note at all counts as overdue only once the expected
     * interval has passed since admission — a patient admitted an hour ago has
     * not been neglected.
     */
    public function isOverdueForRound(): bool
    {
        if ($this->status !== 'admitted') {
            return false;
        }

        $since = $this->hoursSinceLastRound();

        if ($since === null) {
            return $this->admission_date->diffInHours(now()) >= WardRoundNote::EXPECTED_INTERVAL_HOURS;
        }

        return $since >= WardRoundNote::EXPECTED_INTERVAL_HOURS;
    }

    public function lengthOfStayDays(): int
    {
        $end = $this->discharge_date ?? now();

        return max(1, (int) $this->admission_date->diffInDays($end, absolute: true) + 1);
    }
}
