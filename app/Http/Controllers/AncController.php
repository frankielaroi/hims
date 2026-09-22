<?php

namespace App\Http\Controllers;

use App\Models\AncAssessment;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AncController extends Controller
{
    /**
     * The antenatal record: every contact for this pregnancy, newest first.
     */
    public function show(Patient $patient)
    {
        $this->authorizeClinical();

        $assessments = $patient->ancAssessments()
            ->with(['recordedBy', 'visit'])
            ->latest('created_at')
            ->get();

        return view('anc.show', compact('patient', 'assessments'));
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
            'tt_given' => 'nullable|boolean',
            'iptp_given' => 'nullable|boolean',
            'itn_given' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'next_contact_due' => 'nullable|date|after:today',
        ]);

        $patient = $visit->patient;

        // Contacts are numbered per patient so the record reads as a sequence.
        $contactNumber = AncAssessment::where('patient_id', $patient->id)->max('contact_number') + 1;

        $assessment = AncAssessment::create(array_merge($validated, [
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'consultation_id' => $visit->consultations()->latest('id')->value('id'),
            'contact_number' => $contactNumber,
            'tt_given' => $request->boolean('tt_given'),
            'iptp_given' => $request->boolean('iptp_given'),
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
