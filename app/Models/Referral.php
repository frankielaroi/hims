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
        'pre_referral_treatments',
        'pre_referral_notes',
        'pre_referral_given_at',
        'pre_referral_given_by',
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
        'pre_referral_given_at' => 'datetime',
        'pre_referral_treatments' => 'array',
    ];

    /**
     * First doses the Ghana STG and WHO expect before a severely ill patient
     * travels. The journey to a referral hospital can take hours.
     *
     * @var array<string, string>
     */
    public const PRE_REFERRAL_TREATMENTS = [
        'artesunate' => 'Rectal or injectable artesunate (severe malaria)',
        'antibiotic' => 'First dose of an appropriate antibiotic',
        'glucose' => 'Glucose for hypoglycaemia',
        'oxygen' => 'Oxygen',
        'iv_fluids' => 'Intravenous fluids',
        'anticonvulsant' => 'Anticonvulsant',
        'magnesium_sulphate' => 'Magnesium sulphate (eclampsia or severe pre-eclampsia)',
        'uterotonic' => 'Uterotonic (postpartum haemorrhage)',
        'warmth' => 'Kept warm / kangaroo care',
        'none_needed' => 'None clinically indicated',
    ];

    /**
     * Urgencies where travelling without a first dose is the thing to avoid.
     *
     * @var list<string>
     */
    public const URGENCIES_NEEDING_PRE_REFERRAL = ['urgent', 'emergency'];

    public function needsPreReferralRecord(): bool
    {
        return in_array($this->urgency, self::URGENCIES_NEEDING_PRE_REFERRAL, true)
            && empty($this->pre_referral_treatments);
    }

    /**
     * @return list<string>
     */
    public function preReferralTreatmentLabels(): array
    {
        return array_map(
            fn (string $code) => self::PRE_REFERRAL_TREATMENTS[$code] ?? $code,
            (array) $this->pre_referral_treatments
        );
    }

    public function preReferralGivenBy()
    {
        return $this->belongsTo(User::class, 'pre_referral_given_by');
    }

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
