<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientMergeController extends Controller
{
    public function show()
    {
        return view('admin.patients.merge');
    }

    public function merge(Request $request)
    {
        $request->validate([
            'keep_id' => 'required|exists:patients,hospital_id',
            'merge_id' => 'required|exists:patients,hospital_id|different:keep_id',
        ]);

        $keep = Patient::where('hospital_id', $request->keep_id)->first();
        $merge = Patient::where('hospital_id', $request->merge_id)->first();

        // Merge logic: Combine allergies and chronic conditions
        $allergies = array_unique(array_merge($keep->allergies ?? [], $merge->allergies ?? []));
        $conditions = array_unique(array_merge($keep->chronic_conditions ?? [], $merge->chronic_conditions ?? []));

        $keep->update([
            'allergies' => $allergies,
            'chronic_conditions' => $conditions,
        ]);

        // Log the action
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'patient_merged',
            'description' => "Merged patient {$merge->hospital_id} into {$keep->hospital_id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['merged_id' => $merge->id, 'kept_id' => $keep->id],
        ]);

        $merge->delete(); // Soft delete

        return redirect()->route('patients.show', $keep)->with('success', "Patient records successfully merged. {$merge->hospital_id} has been deactivated.");
    }
}
