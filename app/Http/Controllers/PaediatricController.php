<?php

namespace App\Http\Controllers;

use App\Models\GrowthMeasurement;
use App\Models\Immunization;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\ImmunizationSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaediatricController extends Controller
{
    public function __construct(protected ImmunizationSchedule $schedule) {}

    /**
     * The paediatric view of a patient: growth over time and immunisation status.
     */
    public function show(Patient $patient)
    {
        $this->authorizeClinical();

        $measurements = $patient->growthMeasurements()
            ->with('recordedBy')
            ->orderBy('measured_at')
            ->get();

        $immunizations = $patient->immunizations()->with('givenBy')->orderBy('given_at')->get();
        $scheduleStatus = $this->schedule->statusFor($patient);
        $outstanding = $this->schedule->outstandingFor($patient);

        return view('paediatrics.show', compact(
            'patient', 'measurements', 'immunizations', 'scheduleStatus', 'outstanding'
        ));
    }

    /**
     * Record a set of growth measurements. Age at measurement is stored so the
     * reading can still be interpreted if the date of birth is later corrected.
     */
    public function storeMeasurement(Request $request, Patient $patient)
    {
        $this->authorizeClinical();

        $validated = $request->validate([
            'visit_id' => 'nullable|exists:visits,id',
            'measured_at' => 'nullable|date|before_or_equal:now',
            'weight_kg' => 'nullable|numeric|min:0.2|max:400',
            'height_cm' => 'nullable|numeric|min:10|max:250',
            'head_circumference_cm' => 'nullable|numeric|min:20|max:70',
            'muac_cm' => 'nullable|numeric|min:5|max:50',
        ]);

        if (collect($validated)->except(['visit_id', 'measured_at'])->filter()->isEmpty()) {
            return back()->withErrors(['weight_kg' => 'Record at least one measurement.']);
        }

        $this->assertVisitBelongsToPatient($validated['visit_id'] ?? null, $patient);

        $measuredAt = isset($validated['measured_at']) ? Carbon::parse($validated['measured_at']) : now();

        GrowthMeasurement::create([
            'patient_id' => $patient->id,
            'visit_id' => $validated['visit_id'] ?? null,
            'measured_at' => $measuredAt,
            'age_months' => $patient->date_of_birth
                ? Carbon::parse($patient->date_of_birth)->diffInMonths($measuredAt)
                : null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'height_cm' => $validated['height_cm'] ?? null,
            'head_circumference_cm' => $validated['head_circumference_cm'] ?? null,
            'muac_cm' => $validated['muac_cm'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Growth measurement recorded.');
    }

    /**
     * Record a dose that has been given.
     */
    public function storeImmunization(Request $request, Patient $patient)
    {
        $this->authorizeClinical();

        $validated = $request->validate([
            'visit_id' => 'nullable|exists:visits,id',
            'vaccine_code' => 'required|string|max:32',
            'vaccine_name' => 'required|string|max:255',
            'dose_number' => 'required|integer|min:0|max:10',
            'given_at' => 'nullable|date|before_or_equal:now',
            'batch_number' => 'nullable|string|max:64',
            'site' => 'nullable|string|max:64',
            'adverse_reaction' => 'nullable|string',
        ]);

        $this->assertVisitBelongsToPatient($validated['visit_id'] ?? null, $patient);

        $alreadyGiven = Immunization::where('patient_id', $patient->id)
            ->where('vaccine_code', $validated['vaccine_code'])
            ->where('dose_number', $validated['dose_number'])
            ->exists();

        if ($alreadyGiven) {
            return back()->withErrors([
                'vaccine_code' => 'That dose is already recorded for this patient.',
            ]);
        }

        Immunization::create([
            'patient_id' => $patient->id,
            'visit_id' => $validated['visit_id'] ?? null,
            'vaccine_code' => $validated['vaccine_code'],
            'vaccine_name' => $validated['vaccine_name'],
            'dose_number' => $validated['dose_number'],
            'given_at' => $validated['given_at'] ?? now(),
            'batch_number' => $validated['batch_number'] ?? null,
            'site' => $validated['site'] ?? null,
            'adverse_reaction' => $validated['adverse_reaction'] ?? null,
            'given_by' => auth()->id(),
        ]);

        return back()->with('success', 'Immunisation recorded.');
    }

    /**
     * A visit supplied on the form must belong to this patient, or a measurement
     * could be filed against someone else's encounter.
     */
    protected function assertVisitBelongsToPatient(?int $visitId, Patient $patient): void
    {
        if ($visitId === null) {
            return;
        }

        abort_unless(
            Visit::where('id', $visitId)->where('patient_id', $patient->id)->exists(),
            404
        );
    }

    protected function authorizeClinical(): void
    {
        if (! auth()->user()->hasPermission('update_clinical_safety')) {
            abort(403, 'You are not permitted to record clinical measurements.');
        }
    }
}
