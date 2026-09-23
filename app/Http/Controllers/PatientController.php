<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientIdentityLog;
use App\Models\PatientNhisHistory;
use App\Rules\GhanaCardPin;
use App\Services\ClinicalTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('hospital_id', 'like', "%{$search}%")
                    ->orWhere('ghana_card_pin', 'like', '%'.GhanaCardPin::normalise($search).'%')
                    ->orWhere('nhis_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });

            $this->logAction('patient_search', "Searched for: {$search}");
        }

        $patients = $query->with('lastVisit')->latest()->paginate(15);

        return view('patients.index', compact('patients'));
    }

    public function create()
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized. Only Receptionists and Nurses can register new patients.');
        }

        return view('patients.create');
    }

    public function store(Request $request)
    {
        if (! auth()->user()->hasPermission('register_patients')) {
            abort(403, 'Unauthorized.');
        }

        // Force duplicate check if not already confirmed
        if (! $request->has('confirm_duplicate')) {
            $duplicate = Patient::where('full_name', 'like', "%{$request->full_name}%")
                ->where(function ($q) use ($request) {
                    if ($request->date_of_birth) {
                        $q->where('date_of_birth', $request->date_of_birth);
                    } elseif ($request->estimated_age) {
                        $q->where('estimated_age', $request->estimated_age);
                    }
                })->first();

            if ($duplicate) {
                return back()->withInput()->with('duplicate_found', $duplicate);
            }
        }

        // Canonicalise before validating, so a PIN read straight off the card
        // as ten digits is accepted rather than rejected at the desk.
        $request->merge(['ghana_card_pin' => GhanaCardPin::normalise($request->input('ghana_card_pin'))]);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'sex' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'estimated_age' => 'nullable|integer|min:0',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'next_of_kin_name' => 'nullable|string',
            'next_of_kin_contact' => 'nullable|string',
            'nhis_number' => 'nullable|string|unique:patients,nhis_number',
            'nhis_expiry' => 'nullable|date',
            'payment_method' => 'required|string',
            'blood_group' => 'nullable|string',
            'ghana_card_pin' => ['nullable', new GhanaCardPin, 'unique:patients,ghana_card_pin'],
            'occupation' => 'nullable|string|max:120',
            'religion' => 'nullable|string|max:120',
        ]);

        $validated['hospital_id'] = Patient::generateHospitalId();
        $validated['is_age_estimated'] = empty($request->date_of_birth);

        $patient = Patient::create($validated);

        if ($patient->nhis_number) {
            PatientNhisHistory::create([
                'patient_id' => $patient->id,
                'nhis_number' => $patient->nhis_number,
                'expiry_date' => $patient->nhis_expiry,
                'recorded_by' => auth()->id(),
            ]);
        }

        $this->logAction('patient_created', "Created patient: {$patient->full_name} ({$patient->hospital_id})", $patient->id);

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient registered successfully.');
    }

    public function show(Patient $patient)
    {
        $this->logAction('patient_viewed', "Viewed record for: {$patient->full_name}", $patient->id);

        return view('patients.show', compact('patient'));
    }

    /**
     * The patient's whole record as one chronological story, across every visit.
     */
    public function timeline(Patient $patient, ClinicalTimeline $timeline)
    {
        if (! auth()->user()->hasPermission('view_clinical_history')) {
            abort(403, 'You are not permitted to view clinical history.');
        }

        $entries = $timeline->for($patient);

        $this->logAction('clinical_timeline_viewed', "Viewed the clinical timeline for: {$patient->full_name}", $patient->id);

        return view('patients.timeline', compact('patient', 'entries'));
    }

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
        $request->merge(['ghana_card_pin' => GhanaCardPin::normalise($request->input('ghana_card_pin'))]);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'sex' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'estimated_age' => 'nullable|integer|min:0',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'next_of_kin_name' => 'nullable|string',
            'next_of_kin_contact' => 'nullable|string',
            'nhis_number' => 'nullable|string|unique:patients,nhis_number,'.$patient->id,
            'nhis_expiry' => 'nullable|date',
            'payment_method' => 'required|string',
            'ghana_card_pin' => ['nullable', new GhanaCardPin, 'unique:patients,ghana_card_pin,'.$patient->id],
            'occupation' => 'nullable|string|max:120',
            'religion' => 'nullable|string|max:120',
        ]);

        // Log sensitive identity changes. The Ghana Card PIN belongs here: it is
        // a national identifier, and changing it changes who the record is.
        foreach (['full_name', 'date_of_birth', 'estimated_age', 'sex', 'ghana_card_pin'] as $field) {
            if ($request->has($field) && $patient->{$field} != ($validated[$field] ?? null)) {
                PatientIdentityLog::create([
                    'patient_id' => $patient->id,
                    'field_name' => $field,
                    'old_value' => $patient->{$field},
                    'new_value' => $validated[$field],
                    'user_id' => auth()->id(),
                ]);
            }
        }

        // Handle NHIS history
        if ($request->nhis_number && $request->nhis_number != $patient->nhis_number) {
            PatientNhisHistory::create([
                'patient_id' => $patient->id,
                'nhis_number' => $request->nhis_number,
                'expiry_date' => $request->nhis_expiry,
                'recorded_by' => auth()->id(),
            ]);
        }

        $patient->update($validated);
        $this->logAction('patient_updated', "Updated demographics for: {$patient->full_name}", $patient->id);

        return redirect()->route('patients.show', $patient)->with('success', 'Patient information updated.');
    }

    public function updateClinical(Request $request, Patient $patient)
    {
        // Granular permission check
        if (! auth()->user()->hasPermission('update_clinical_safety')) {
            abort(403, 'Only clinical staff can update safety records.');
        }

        $request->validate([
            'blood_group' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
            'allergies' => 'nullable|array',
            'allergies.*.allergen' => 'nullable|string|max:255',
            'allergies.*.reaction' => 'nullable|string|max:255',
            'allergies.*.severity' => ['nullable', Rule::in(PatientAllergy::SEVERITIES)],
            'allergies.*.status' => ['nullable', Rule::in(PatientAllergy::STATUSES)],
        ]);

        $conditions = $request->chronic_conditions ? array_map('trim', explode(',', $request->chronic_conditions)) : [];

        DB::transaction(function () use ($request, $patient, $conditions) {
            $this->syncStructuredAllergies($patient, $request->input('allergies', []));

            $patient->update([
                'blood_group' => $request->blood_group,
                'chronic_conditions' => array_filter($conditions),
                // Deprecated mirror of the structured table, kept in step so older
                // readers (and patient merging) do not silently lose allergens.
                'allergies' => $patient->activeAllergies()->pluck('allergen')->all(),
            ]);
        });

        $this->logAction('clinical_data_updated', "Updated clinical safety data for: {$patient->full_name}", $patient->id);

        return redirect()->route('patients.show', $patient)->with('success', 'Clinical safety records updated.');
    }

    /**
     * Write the submitted allergy rows to `patient_allergies`.
     *
     * An allergen the clinician removed is marked inactive rather than deleted,
     * because an allergy that was once recorded is clinical history.
     *
     * @param  array<int, array<string, string|null>>  $rows
     */
    protected function syncStructuredAllergies(Patient $patient, array $rows): void
    {
        $submitted = [];

        foreach ($rows as $row) {
            $allergen = trim((string) ($row['allergen'] ?? ''));

            if ($allergen === '') {
                continue;
            }

            $submitted[] = strtolower($allergen);

            $existing = $patient->structuredAllergies()
                ->whereRaw('LOWER(allergen) = ?', [strtolower($allergen)])
                ->first();

            $attributes = [
                'allergen' => $allergen,
                'reaction' => $row['reaction'] ?? null,
                'severity' => $row['severity'] ?? 'unknown',
                'status' => $row['status'] ?? 'active',
                'recorded_by' => auth()->id(),
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                $patient->structuredAllergies()->create($attributes);
            }
        }

        $patient->structuredAllergies()
            ->where('status', 'active')
            ->get()
            ->reject(fn (PatientAllergy $allergy) => in_array(strtolower($allergy->allergen), $submitted, true))
            ->each(fn (PatientAllergy $allergy) => $allergy->update(['status' => 'inactive']));
    }

    protected function logAction(string $action, string $description, $patientId = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $patientId ? ['patient_id' => $patientId] : null,
        ]);
    }
}
