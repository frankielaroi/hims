<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImciAssessment;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\ImciClassifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImciController extends Controller
{
    public function __construct(protected ImciClassifier $classifier) {}

    /**
     * Every IMCI assessment for this child, newest first.
     */
    public function show(Patient $patient)
    {
        $this->authorizeClinical();

        $assessments = $patient->imciAssessments()->with('recordedBy')->latest()->get();

        return view('imci.show', compact('patient', 'assessments'));
    }

    /**
     * Record an IMCI assessment and read the classifications off the chart.
     */
    public function store(Request $request, Visit $visit)
    {
        $this->authorizeClinical();

        $patient = $visit->patient;
        $ageMonths = $this->ageInMonths($patient);

        if ($ageMonths === null || ! $this->classifier->appliesTo($ageMonths)) {
            return back()->withErrors([
                'imci' => 'IMCI applies to children from two months to five years. '
                    .'Below two months the young infant chart applies, which this module does not cover.',
            ]);
        }

        $validated = $request->validate([
            'unable_to_drink' => 'nullable|boolean',
            'vomits_everything' => 'nullable|boolean',
            'convulsions' => 'nullable|boolean',
            'lethargic_or_unconscious' => 'nullable|boolean',
            'convulsing_now' => 'nullable|boolean',

            'has_cough' => 'nullable|boolean',
            'cough_days' => 'nullable|integer|min:0|max:120',
            'respiratory_rate' => 'nullable|integer|min:10|max:150',
            'chest_indrawing' => 'nullable|boolean',
            'stridor' => 'nullable|boolean',

            'has_diarrhoea' => 'nullable|boolean',
            'diarrhoea_days' => 'nullable|integer|min:0|max:120',
            'blood_in_stool' => 'nullable|boolean',
            'sunken_eyes' => 'nullable|boolean',
            'skin_pinch' => ['nullable', Rule::in(ImciAssessment::SKIN_PINCH)],
            'restless_or_irritable' => 'nullable|boolean',
            'drinking' => ['nullable', Rule::in(ImciAssessment::DRINKING)],

            'has_fever' => 'nullable|boolean',
            'fever_days' => 'nullable|integer|min:0|max:120',
            'malaria_test_result' => ['nullable', Rule::in(ImciAssessment::MALARIA_RESULTS)],
            'stiff_neck' => 'nullable|boolean',
            'measles_rash' => 'nullable|boolean',

            'has_ear_problem' => 'nullable|boolean',
            'ear_pain' => 'nullable|boolean',
            'ear_discharge_days' => 'nullable|integer|min:0|max:365',
            'tender_swelling_behind_ear' => 'nullable|boolean',

            'muac_cm' => 'nullable|numeric|between:5,30',
            'oedema_both_feet' => 'nullable|boolean',
            'visible_severe_wasting' => 'nullable|boolean',
            'palmar_pallor' => ['nullable', Rule::in(ImciAssessment::PALLOR)],

            'notes' => 'nullable|string',
        ]);

        $booleans = [
            'unable_to_drink', 'vomits_everything', 'convulsions', 'lethargic_or_unconscious',
            'convulsing_now', 'has_cough', 'chest_indrawing', 'stridor', 'has_diarrhoea',
            'blood_in_stool', 'sunken_eyes', 'restless_or_irritable', 'has_fever', 'stiff_neck',
            'measles_rash', 'has_ear_problem', 'ear_pain', 'tender_swelling_behind_ear',
            'oedema_both_feet', 'visible_severe_wasting',
        ];

        foreach ($booleans as $field) {
            $validated[$field] = $request->boolean($field);
        }

        $assessment = new ImciAssessment(array_merge($validated, [
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'consultation_id' => $visit->consultations()->latest('id')->value('id'),
            'age_months' => $ageMonths,
            'recorded_by' => auth()->id(),
        ]));

        // The chart is read after the findings are set, and the result is kept
        // as a snapshot: what the clinician acted on belongs in the record even
        // if the tables are revised later.
        $classifications = $this->classifier->classify($assessment);
        $assessment->classifications = $classifications;
        $assessment->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'imci_assessment_recorded',
            'description' => "IMCI assessment recorded for {$patient->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'imci_assessment_id' => $assessment->id,
                'classifications' => array_column($classifications, 'classification'),
                'urgent_referral' => $this->classifier->needsUrgentReferral($classifications),
            ],
        ]);

        if ($this->classifier->needsUrgentReferral($classifications)) {
            $pink = implode('; ', array_column($assessment->pinkClassifications(), 'classification'));

            return back()->with('warning', "IMCI: REFER URGENTLY — {$pink}. Give pre-referral treatment and record it on the referral.");
        }

        return back()->with('success', 'IMCI assessment recorded.');
    }

    /**
     * Age in whole months, which every IMCI threshold turns on.
     */
    protected function ageInMonths(Patient $patient): ?int
    {
        if ($patient->date_of_birth !== null) {
            return (int) $patient->date_of_birth->diffInMonths(now());
        }

        return $patient->estimated_age === null ? null : (int) $patient->estimated_age * 12;
    }

    protected function authorizeClinical(): void
    {
        if (! auth()->user()->hasPermission('update_clinical_safety')) {
            abort(403, 'You are not permitted to record IMCI assessments.');
        }
    }
}
