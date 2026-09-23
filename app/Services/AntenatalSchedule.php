<?php

namespace App\Services;

use App\Models\AncAssessment;
use App\Models\Patient;
use Illuminate\Support\Collection;

/**
 * What an antenatal programme still owes a woman: IPTp, tetanus, and the
 * booking profile.
 *
 * Like ImmunizationSchedule, nothing here is stored. Counts are derived from the
 * contacts actually recorded, so a corrected contact flows straight through.
 */
class AntenatalSchedule
{
    /**
     * Sulfadoxine-pyrimethamine is not given in the first trimester. Ghana and
     * WHO start IPTp as early as possible in the second trimester, which is
     * quickening at about 13 completed weeks.
     */
    public const IPTP_MIN_GESTATION_WEEKS = 13;

    /**
     * Doses are given monthly, and must be at least a month apart.
     */
    public const IPTP_MIN_INTERVAL_DAYS = 28;

    /**
     * The programme target: three or more doses by delivery.
     */
    public const IPTP_TARGET_DOSES = 3;

    /**
     * TT1-TT5 confers lifelong protection; nothing is given beyond the fifth.
     */
    public const TT_MAX_DOSES = 5;

    /**
     * The WHO/Ghana model is eight antenatal contacts.
     */
    public const TARGET_CONTACTS = 8;

    /**
     * Booking investigations every pregnancy needs, and the column each is
     * recorded in.
     *
     * @var array<string, string>
     */
    public const PROFILE_ITEMS = [
        'blood_group' => 'Blood group',
        'rhesus' => 'Rhesus factor',
        'sickling_status' => 'Sickling',
        'haemoglobin' => 'Haemoglobin',
        'hiv_status' => 'HIV',
        'syphilis_status' => 'Syphilis (VDRL)',
        'hepatitis_b_status' => 'Hepatitis B',
    ];

    /**
     * Contacts for this patient, oldest first.
     *
     * @return Collection<int, AncAssessment>
     */
    protected function contacts(Patient $patient): Collection
    {
        return $patient->ancAssessments()->orderBy('contact_number')->get();
    }

    /**
     * How many IPTp doses have been given, and what number the next one is.
     *
     * @return array{given: int, next_dose: int, target: int, is_complete: bool}
     */
    public function iptpStatus(Patient $patient): array
    {
        $given = (int) $patient->ancAssessments()->whereNotNull('iptp_dose_number')->count();

        return [
            'given' => $given,
            'next_dose' => $given + 1,
            'target' => self::IPTP_TARGET_DOSES,
            'is_complete' => $given >= self::IPTP_TARGET_DOSES,
        ];
    }

    /**
     * @return array{given: int, next_dose: int|null, target: int, is_complete: bool}
     */
    public function tetanusStatus(Patient $patient): array
    {
        $given = (int) $patient->ancAssessments()->whereNotNull('tt_dose_number')->count();
        $complete = $given >= self::TT_MAX_DOSES;

        return [
            'given' => $given,
            'next_dose' => $complete ? null : $given + 1,
            'target' => self::TT_MAX_DOSES,
            'is_complete' => $complete,
        ];
    }

    /**
     * Why IPTp cannot be given at this contact, or null when it can.
     *
     * Gestational age is required: SP in the first trimester is the thing this
     * guards against, so an unknown gestation is refused rather than assumed
     * safe.
     */
    public function iptpRefusalReason(Patient $patient, ?int $gestationWeeks): ?string
    {
        if ($gestationWeeks === null) {
            return 'Record the gestational age before giving IPTp — sulfadoxine-pyrimethamine is not given in the first trimester.';
        }

        if ($gestationWeeks < self::IPTP_MIN_GESTATION_WEEKS) {
            return sprintf(
                'IPTp is not given before %d weeks (this contact is at %d weeks).',
                self::IPTP_MIN_GESTATION_WEEKS,
                $gestationWeeks
            );
        }

        $last = $patient->ancAssessments()
            ->whereNotNull('iptp_dose_number')
            ->orderByDesc('created_at')
            ->first();

        if ($last === null) {
            return null;
        }

        $daysSince = $last->created_at->diffInDays(now());

        if ($daysSince < self::IPTP_MIN_INTERVAL_DAYS) {
            return sprintf(
                'The last IPTp dose was %d day(s) ago; doses must be at least %d days apart.',
                $daysSince,
                self::IPTP_MIN_INTERVAL_DAYS
            );
        }

        return null;
    }

    /**
     * Booking investigations with nothing recorded against them across any
     * contact in this record.
     *
     * @return list<string>
     */
    public function outstandingProfileItems(Patient $patient): array
    {
        $contacts = $this->contacts($patient);

        $outstanding = [];

        foreach (self::PROFILE_ITEMS as $field => $label) {
            $recorded = $contacts->contains(fn (AncAssessment $contact) => filled($contact->{$field}));

            if (! $recorded) {
                $outstanding[] = $label;
            }
        }

        return $outstanding;
    }

    /**
     * A whole-pregnancy summary for the antenatal record screen.
     *
     * @return array<string, mixed>
     */
    public function summaryFor(Patient $patient): array
    {
        return [
            'contacts' => (int) $patient->ancAssessments()->count(),
            'target_contacts' => self::TARGET_CONTACTS,
            'iptp' => $this->iptpStatus($patient),
            'tetanus' => $this->tetanusStatus($patient),
            'outstanding_profile' => $this->outstandingProfileItems($patient),
        ];
    }
}
