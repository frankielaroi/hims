<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a single chronological view of everything recorded for a patient
 * across every encounter: consultations, investigations, imaging, medicines,
 * admissions, referrals and how each visit ended.
 *
 * Each entry is a plain array so the view does not have to know which table an
 * event came from.
 */
class ClinicalTimeline
{
    /**
     * Assemble the timeline, newest event first.
     *
     * @return Collection<int, array{occurred_at: Carbon, type: string, icon: string, title: string, summary: string, detail: list<string>, visit_id: int|null, visit_number: string|null}>
     */
    public function for(Patient $patient, int $limit = 200): Collection
    {
        $patient->loadMissing([
            'visits.department',
            'visits.consultations.diagnoses',
            'visits.consultations.notes',
            'visits.consultations.signedBy',
            'visits.labOrders.items.labTest',
            'visits.radiologyOrders',
            'visits.prescriptions.items.drug',
            'visits.admission.bed.ward',
            'visits.referral.toDepartment',
            'visits.damaRecord',
            'visits.deathRecord',
            'visits.outcome',
        ]);

        return collect()
            ->concat($this->visitEntries($patient))
            ->concat($this->consultationEntries($patient))
            ->concat($this->labEntries($patient))
            ->concat($this->radiologyEntries($patient))
            ->concat($this->prescriptionEntries($patient))
            ->concat($this->admissionEntries($patient))
            ->concat($this->referralEntries($patient))
            ->concat($this->dispositionEntries($patient))
            ->sortByDesc(fn (array $entry) => $entry['occurred_at']->getTimestamp())
            ->values()
            ->take($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function visitEntries(Patient $patient): array
    {
        return $patient->visits->map(fn ($visit) => $this->entry(
            $visit->opened_at ?? $visit->created_at,
            'visit',
            'door-open',
            'Attendance — '.($visit->department->name ?? 'Unknown department'),
            ucfirst($visit->type).' visit, '.$visit->priority.' priority.',
            array_filter([
                $visit->reason_for_visit ? 'Reason: '.$visit->reason_for_visit : null,
                'Payment: '.strtoupper($visit->payment_method),
            ]),
            $visit
        ))->all();
    }

    /**
     * Only signed notes appear. An unsigned draft is working memory, not a
     * record of what happened.
     *
     * @return list<array<string, mixed>>
     */
    protected function consultationEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            foreach ($visit->consultations as $consultation) {
                if (! $consultation->isSigned()) {
                    continue;
                }

                $primary = $consultation->diagnoses->firstWhere('type', 'primary');
                $others = $consultation->diagnoses->where('type', '!=', 'primary');

                $detail = [];

                if ($primary) {
                    $detail[] = "Primary: {$primary->description} ({$primary->icd10_code}, {$primary->certainty})";
                }

                foreach ($others as $diagnosis) {
                    $detail[] = ucfirst($diagnosis->type).": {$diagnosis->description} ({$diagnosis->icd10_code})";
                }

                if ($consultation->version > 1) {
                    $detail[] = "Amended — now at version {$consultation->version}";
                }

                $entries[] = $this->entry(
                    $consultation->signed_at ?? $consultation->created_at,
                    'consultation',
                    'stethoscope',
                    'Consultation signed'.($consultation->signedBy ? ' by '.$consultation->signedBy->name : ''),
                    $primary->description ?? 'No primary diagnosis recorded',
                    $detail,
                    $visit
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function labEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            foreach ($visit->labOrders as $order) {
                $detail = [];

                foreach ($order->items as $item) {
                    $name = $item->labTest->name ?? 'Test';

                    $detail[] = $item->result_value === null
                        ? "{$name}: awaiting result"
                        : "{$name}: {$item->result_value}".($item->result_flag ? " ({$item->result_flag})" : '');
                }

                if ($order->reviewed_at) {
                    $detail[] = 'Reviewed by clinician: '.$order->review_action;
                } elseif ($order->status === 'verified') {
                    $detail[] = 'Results not yet read by a clinician';
                }

                $entries[] = $this->entry(
                    $order->created_at,
                    'lab',
                    'flask-conical',
                    'Laboratory — '.$order->items->count().' test(s)',
                    ucfirst(str_replace('_', ' ', $order->status)).', '.$order->priority.' priority.',
                    $detail,
                    $visit
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function radiologyEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            foreach ($visit->radiologyOrders as $order) {
                $detail = array_filter([
                    $order->clinical_indication ? 'Indication: '.$order->clinical_indication : null,
                    $order->impression ? 'Impression: '.$order->impression : null,
                    $order->is_critical ? 'Reported as a critical finding' : null,
                    $order->reviewed_at ? 'Report read by the ordering clinician' : null,
                ]);

                $entries[] = $this->entry(
                    $order->reported_at ?? $order->created_at,
                    'radiology',
                    'scan',
                    'Imaging — '.$order->study_label,
                    ucfirst(str_replace('_', ' ', $order->status)).'.',
                    array_values($detail),
                    $visit
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function prescriptionEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            foreach ($visit->prescriptions as $prescription) {
                if ($prescription->status === 'draft') {
                    continue;
                }

                $detail = $prescription->items->map(function ($item) {
                    $line = ($item->drug->name ?? 'Drug')." {$item->dose} {$item->frequency} for {$item->duration_days} day(s)";

                    return $item->chronic_medication_id ? $line.' — continued' : $line;
                })->all();

                $entries[] = $this->entry(
                    $prescription->created_at,
                    'prescription',
                    'pill',
                    'Prescription — '.$prescription->items->count().' item(s)',
                    ucfirst(str_replace('_', ' ', $prescription->status)).'.',
                    $detail,
                    $visit
                );
            }
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function admissionEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            $admission = $visit->admission;

            if ($admission === null) {
                continue;
            }

            $bed = $admission->bed;

            $entries[] = $this->entry(
                $admission->admission_date ?? $admission->created_at,
                'admission',
                'bed',
                'Admitted'.($bed ? " — bed {$bed->bed_number}, ".($bed->ward->name ?? '') : ''),
                $admission->admitting_diagnosis ?? 'No admitting diagnosis recorded',
                array_filter([
                    $admission->discharge_date ? 'Discharged '.$admission->discharge_date->format('d M Y') : 'Still admitted',
                    $admission->discharge_notes,
                ]),
                $visit
            );
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function referralEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            $referral = $visit->referral;

            if ($referral === null) {
                continue;
            }

            $destination = $referral->type === 'internal'
                ? ($referral->toDepartment->name ?? 'another department')
                : ($referral->external_facility_name ?? 'an external facility');

            $entries[] = $this->entry(
                $referral->created_at,
                'referral',
                'share-2',
                'Referred to '.$destination,
                ucfirst($referral->status).', '.$referral->urgency.' urgency.',
                array_filter([$referral->reason]),
                $visit
            );
        }

        return $entries;
    }

    /**
     * How each visit ended, including the two dispositions that carry legal weight.
     *
     * @return list<array<string, mixed>>
     */
    protected function dispositionEntries(Patient $patient): array
    {
        $entries = [];

        foreach ($patient->visits as $visit) {
            if ($dama = $visit->damaRecord) {
                $entries[] = $this->entry(
                    $dama->left_at,
                    'dama',
                    'log-out',
                    'Left against medical advice',
                    $dama->reason_given ?? 'No reason given.',
                    array_filter([
                        'Risks explained: '.$dama->risks_explained,
                        'Witness: '.$dama->witness_name.($dama->witness_relationship ? " ({$dama->witness_relationship})" : ''),
                        $dama->patient_has_capacity ? null : 'Recorded as lacking capacity',
                        $dama->patient_signature_obtained ? 'Signature obtained' : 'No signature obtained',
                    ]),
                    $visit
                );
            }

            if ($death = $visit->deathRecord) {
                $entries[] = $this->entry(
                    $death->died_at,
                    'death',
                    'heart-crack',
                    'Died in care',
                    $death->immediate_cause,
                    array_filter([
                        $death->underlying_cause ? 'Underlying: '.$death->underlying_cause : null,
                        'Manner: '.$death->manner,
                        $death->referred_to_coroner ? 'Referred to the coroner' : null,
                        $death->autopsy_requested ? 'Autopsy requested' : null,
                    ]),
                    $visit
                );
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $detail
     * @return array<string, mixed>
     */
    protected function entry($occurredAt, string $type, string $icon, string $title, string $summary, array $detail, $visit): array
    {
        return [
            'occurred_at' => $occurredAt,
            'type' => $type,
            'icon' => $icon,
            'title' => $title,
            'summary' => $summary,
            'detail' => array_values($detail),
            'visit_id' => $visit?->id,
            'visit_number' => $visit?->visit_number,
        ];
    }
}
