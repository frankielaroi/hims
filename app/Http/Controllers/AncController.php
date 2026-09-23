<?php

namespace App\Http\Controllers;

use App\Models\AncAssessment;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\AntenatalSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AncController extends Controller
{
    public function __construct(protected AntenatalSchedule $schedule) {}

    /**
     * The antenatal record: every contact for this pregnancy, newest first,
     * with what the programme still owes her.
     */
    public function show(Patient $patient)
    {
        $this->authorizeClinical();

        $assessments = $patient->ancAssessments()
            ->with(['recordedBy', 'visit'])
            ->latest('created_at')
            ->get();

        $summary = $this->schedule->summaryFor($patient);

        return view('anc.show', compact('patient', 'assessments', 'summary'));
    }

    /**
     * Record one antenatal contact against a visit.
     */
    public function store(Request $request, Visit $visit)
    {
        $this->authorizeClinical();

        $validated = $request->validate([
            'gestational_age_weeks' => 'nullable|integer|min:1|max:45',
            'fundal_height_cm' => 'nullable|numeric|min:5|max:50',
            'fetal_heart_rate' => 'nullable|integer|min:40|max:220',
            'presentation' => ['nullable', Rule::in(AncAssessment::PRESENTATIONS)],
            'fetal_movements' => 'nullable|integer|min:0|max:100',
            'maternal_weight_kg' => 'nullable|numeric|min:25|max:250',
            'systolic_bp' => 'nullable|integer|min:50|max:300',
            'diastolic_bp' => 'nullable|integer|min:30|max:200',
            'haemoglobin' => 'nullable|numeric|min:1|max:25',
            'urinalysis_protein' => 'nullable|string|max:32',
            'urinalysis_glucose' => 'nullable|string|max:32',
            'iptp_given' => 'nullable|boolean',
            'tt_given' => 'nullable|boolean',
            'ifa_given' => 'nullable|boolean',
            'deworming_given' => 'nullable|boolean',
            'itn_given' => 'nullable|boolean',
            'gravida' => 'nullable|integer|min:1|max:25',
            'para' => 'nullable|integer|min:0|max:25',
            'abortions' => 'nullable|integer|min:0|max:25',
            'living_children' => 'nullable|integer|min:0|max:25',
            'blood_group' => ['nullable', Rule::in(AncAssessment::BLOOD_GROUPS)],
            'rhesus' => ['nullable', Rule::in(AncAssessment::RHESUS_VALUES)],
            'sickling_status' => ['nullable', Rule::in(AncAssessment::SICKLING_RESULTS)],
            'hiv_status' => ['nullable', Rule::in(AncAssessment::SCREENING_RESULTS)],
            'syphilis_status' => ['nullable', Rule::in(AncAssessment::SCREENING_RESULTS)],
            'hepatitis_b_status' => ['nullable', Rule::in(AncAssessment::SCREENING_RESULTS)],
            'notes' => 'nullable|string',
            'next_contact_due' => 'nullable|date|after:today',
        ]);

        $patient = $visit->patient;

        // Dose numbers are derived, never taken from the form: the record has to
        // be able to prove a third IPTp dose, and a typed number cannot.
        $iptpDoseNumber = null;

        if ($request->boolean('iptp_given')) {
            $refusal = $this->schedule->iptpRefusalReason($patient, $validated['gestational_age_weeks'] ?? null);

            if ($refusal !== null) {
                return back()->withInput()->withErrors(['iptp_given' => $refusal]);
            }

            $iptpDoseNumber = $this->schedule->iptpStatus($patient)['next_dose'];
        }

        $ttDoseNumber = null;

        if ($request->boolean('tt_given')) {
            $ttDoseNumber = $this->schedule->tetanusStatus($patient)['next_dose'];

            if ($ttDoseNumber === null) {
                return back()->withInput()->withErrors([
                    'tt_given' => 'She has already had all '.AntenatalSchedule::TT_MAX_DOSES.' tetanus doses, which gives lifelong protection.',
                ]);
            }
        }

        // Contacts are numbered per patient so the record reads as a sequence.
        $contactNumber = AncAssessment::where('patient_id', $patient->id)->max('contact_number') + 1;

        $assessment = AncAssessment::create(array_merge($validated, [
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'consultation_id' => $visit->consultations()->latest('id')->value('id'),
            'contact_number' => $contactNumber,
            'iptp_dose_number' => $iptpDoseNumber,
            'tt_dose_number' => $ttDoseNumber,
            'ifa_given' => $request->boolean('ifa_given'),
            'deworming_given' => $request->boolean('deworming_given'),
            'itn_given' => $request->boolean('itn_given'),
            'recorded_by' => auth()->id(),
        ]));

        $dangerSigns = $assessment->dangerSigns();

        if ($dangerSigns !== []) {
            return back()->with('warning', 'Contact recorded. Danger signs: '.implode('; ', $dangerSigns));
        }

        return back()->with('success', "Antenatal contact {$contactNumber} recorded.");
    }

    protected function authorizeClinical(): void
    {
        if (! auth()->user()->hasPermission('update_clinical_safety')) {
            abort(403, 'You are not permitted to record antenatal assessments.');
        }
    }
}
