<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientIdentityLog;
use App\Models\PatientNhisHistory;
use Illuminate\Http\Request;

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
        ]);

        $validated['hospital_id'] = $this->generateHospitalId();
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

    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient)
    {
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
        ]);

        // Log sensitive identity changes
        foreach (['full_name', 'date_of_birth', 'estimated_age', 'sex'] as $field) {
            if ($request->has($field) && $patient->{$field} != $validated[$field]) {
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

        $validated = $request->validate([
            'blood_group' => 'nullable|string',
        ]);

        // Process comma-separated strings into arrays
        $allergies = $request->allergies ? array_map('trim', explode(',', $request->allergies)) : [];
        $conditions = $request->chronic_conditions ? array_map('trim', explode(',', $request->chronic_conditions)) : [];

        $patient->update([
            'blood_group' => $request->blood_group,
            'allergies' => array_filter($allergies),
            'chronic_conditions' => array_filter($conditions),
        ]);

        $this->logAction('clinical_data_updated', "Updated clinical safety data for: {$patient->full_name}", $patient->id);

        return redirect()->route('patients.show', $patient)->with('success', 'Clinical safety records updated.');
    }

    protected function generateHospitalId()
    {
        $year = date('Y');
        $count = Patient::whereYear('created_at', $year)->count() + 1;

        return "HIMS-{$year}-".str_pad($count, 5, '0', STR_PAD_LEFT);
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
