<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AuditLog;
use App\Models\Bed;
use App\Models\BedTransfer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Visit;
use App\Models\Ward;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function index()
    {
        $awaitingAdmission = Visit::with(['patient', 'department'])
            ->where('status', 'awaiting_admission')
            ->orderBy('created_at', 'asc')
            ->get();

        $wards = Ward::with(['beds.currentAdmission.visit.patient'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admissions.index', compact('awaitingAdmission', 'wards'));
    }

    public function assignBed(Request $request, Visit $visit)
    {
        abort_unless($visit->status === 'awaiting_admission', 422, 'This visit is not awaiting admission.');

        $validated = $request->validate([
            'bed_id' => 'required|exists:beds,id',
        ]);

        $bed = Bed::findOrFail($validated['bed_id']);
        abort_unless($bed->status === 'available', 422, 'That bed is not available.');

        $admittingDiagnosis = $visit->consultations()
            ->with('diagnoses')
            ->latest()
            ->first()
            ?->diagnoses
            ?->firstWhere('is_primary', true)
            ?->description;

        $admission = Admission::create([
            'visit_id' => $visit->id,
            'bed_id' => $bed->id,
            'admitting_diagnosis' => $admittingDiagnosis,
            'admitted_by' => auth()->id(),
            'admission_date' => now(),
            'status' => 'admitted',
        ]);

        $bed->update(['status' => 'occupied']);
        $visit->updateStatus('admitted');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'patient_admitted',
            'description' => "Admitted {$visit->patient->full_name} to bed {$bed->bed_number} ({$bed->ward->name})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['visit_id' => $visit->id, 'admission_id' => $admission->id, 'bed_id' => $bed->id],
        ]);

        return redirect()->route('admissions.show', $admission)
            ->with('success', "{$visit->patient->full_name} admitted to bed {$bed->bed_number}.");
    }

    public function show(Admission $admission)
    {
        $admission->load(['visit.patient', 'bed.ward', 'admittedBy', 'transfers.fromBed', 'transfers.toBed']);

        $wards = Ward::with('beds')->where('is_active', true)->orderBy('name')->get();

        return view('admissions.show', compact('admission', 'wards'));
    }

    public function transfer(Request $request, Admission $admission)
    {
        abort_unless($admission->status === 'admitted', 422, 'Only current admissions can be transferred.');

        $validated = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'reason' => 'nullable|string',
        ]);

        $newBed = Bed::findOrFail($validated['bed_id']);
        abort_unless($newBed->status === 'available', 422, 'That bed is not available.');
        abort_if($newBed->id === $admission->bed_id, 422, 'Patient is already in that bed.');

        $oldBed = $admission->bed;

        BedTransfer::create([
            'admission_id' => $admission->id,
            'from_bed_id' => $oldBed->id,
            'to_bed_id' => $newBed->id,
            'reason' => $validated['reason'] ?? null,
            'transferred_by' => auth()->id(),
        ]);

        $oldBed->update(['status' => 'cleaning']);
        $newBed->update(['status' => 'occupied']);
        $admission->update(['bed_id' => $newBed->id]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'patient_transferred',
            'description' => "Transferred admission #{$admission->id} from bed {$oldBed->bed_number} to {$newBed->bed_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['admission_id' => $admission->id, 'from_bed_id' => $oldBed->id, 'to_bed_id' => $newBed->id],
        ]);

        return back()->with('success', "Transferred to bed {$newBed->bed_number}.");
    }

    public function discharge(Request $request, Admission $admission)
    {
        abort_unless($admission->status === 'admitted', 422, 'This admission is already closed.');

        $validated = $request->validate([
            'discharge_notes' => 'nullable|string',
        ]);

        $bed = $admission->bed;
        $days = $admission->lengthOfStayDays();

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => now(),
            'discharge_notes' => $validated['discharge_notes'] ?? null,
            'discharged_by' => auth()->id(),
        ]);

        $bed->update(['status' => 'cleaning']);

        $wardService = Service::where('code', 'WARD-GEN')->first();
        if ($wardService) {
            Invoice::addCharge(
                $admission->visit,
                $wardService->name,
                'Ward',
                (float) $wardService->price,
                $wardService->is_nhis_covered,
                $days,
            );
        }

        $visit = $admission->visit;
        $visit->update(['closed_at' => now()]);
        $visit->updateStatus('completed');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'patient_discharged',
            'description' => "Discharged {$visit->patient->full_name} after {$days} day(s) in bed {$bed->bed_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['admission_id' => $admission->id, 'visit_id' => $visit->id, 'days' => $days],
        ]);

        return redirect()->route('admissions.index')->with('success', "{$visit->patient->full_name} discharged.");
    }

    public function markBedAvailable(Request $request, Bed $bed)
    {
        abort_unless($bed->status === 'cleaning', 422, 'Only beds in cleaning/turnaround can be marked available.');

        $bed->update(['status' => 'available']);

        return back()->with('success', "Bed {$bed->bed_number} is now available.");
    }
}
