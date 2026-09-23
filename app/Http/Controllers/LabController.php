<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lab\CollectSpecimenRequest;
use App\Http\Requests\Lab\RejectSampleRequest;
use App\Http\Requests\Lab\SaveResultRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReagent;
use App\Models\LabSample;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LabController extends Controller
{
    public function index()
    {
        $orders = LabOrder::with(['visit.patient', 'visit.department', 'items.labTest', 'samples'])
            ->outstanding()
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('lab.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $patients = Patient::orderBy('full_name')->get();
        $tests = LabTest::where('is_active', true)->get();

        return view('lab.create', compact('patients', 'tests'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'test_ids' => 'required|array',
            'test_ids.*' => 'exists:lab_tests,id',
            'priority' => 'required|in:routine,urgent,stat',
            'clinical_indication' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);

        // A walk-in comes straight to the laboratory for a named test and never
        // sees triage or a doctor, so the visit opens as direct_lab rather than
        // being parked in the triage queue nobody will call them from.
        $visit = $patient->lastVisit;

        if (! $visit || $visit->closed_at) {
            $department = Department::where('slug', 'opd-general')->first()
                ?? Department::orderBy('id')->firstOrFail();

            $visit = $patient->visits()->create([
                'department_id' => $department->id,
                'visit_number' => Visit::generateVisitNumber(),
                'queue_number' => Visit::nextQueueNumber($department->id),
                'type' => Visit::TYPE_DIRECT_LAB,
                'payment_method' => $patient->payment_method ?? 'cash',
                'priority' => $validated['priority'],
                'status' => 'waiting_lab',
                'created_by' => auth()->id(),
            ]);
        }

        $order = LabOrder::create([
            'visit_id' => $visit->id,
            'ordered_by' => auth()->id(),
            'priority' => $validated['priority'],
            'clinical_indication' => $validated['clinical_indication'] ?? null,
            'status' => 'ordered',
        ]);

        foreach ($validated['test_ids'] as $testId) {
            $test = LabTest::find($testId);
            $order->items()->create([
                'lab_test_id' => $testId,
                'specimen_type' => $test->specimen_type,
                'specimen_status' => 'pending_collection',
                'reference_range' => $test->reference_range,
            ]);
        }

        return redirect()->route('lab.show', $order)->with('success', 'Lab order created successfully.');
    }

    public function show(LabOrder $labOrder)
    {
        $this->authorize('view', $labOrder);

        $labOrder->load(['visit.patient', 'items.labTest', 'orderedBy', 'samples.items.labTest', 'items.labSample']);
        $reagents = LabReagent::where('current_stock', '>', 0)->get();

        return view('lab.show', compact('labOrder', 'reagents'));
    }

    public function collectSpecimen(CollectSpecimenRequest $request, LabOrder $labOrder)
    {
        $validated = $request->validated();

        $sample = LabSample::create([
            'lab_order_id' => $labOrder->id,
            'barcode_number' => 'SAMP-'.strtoupper(Str::random(8)),
            'specimen_type' => $validated['specimen_type'],
            'collected_by' => auth()->id(),
            'collected_at' => now(),
            'status' => 'collected',
        ]);

        LabOrderItem::whereIn('id', $validated['item_ids'])->update([
            'lab_sample_id' => $sample->id,
            'specimen_status' => 'collected',
            'specimen_collected_by' => auth()->id(),
            'specimen_collected_at' => now(),
        ]);

        if ($labOrder->status === 'ordered') {
            $labOrder->update(['status' => 'specimen_collected']);
        }

        return back()->with('success', 'Specimen collected and barcode generated: '.$sample->barcode_number);
    }

    public function receiveSample(LabSample $sample)
    {
        $this->authorize('receive', $sample);

        $sample->update([
            'status' => 'received',
            'received_at' => now(),
        ]);

        return back()->with('success', 'Sample marked as received in the laboratory.');
    }

    public function rejectSample(RejectSampleRequest $request, LabSample $sample)
    {
        $sample->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('rejection_reason'),
        ]);

        // Reset items to pending_collection
        $sample->items()->update([
            'lab_sample_id' => null,
            'specimen_status' => 'pending_collection',
            'specimen_collected_by' => null,
            'specimen_collected_at' => null,
        ]);

        return back()->with('warning', 'Sample rejected. Items have been reset for re-collection.');
    }

    public function saveResult(SaveResultRequest $request, LabOrder $labOrder, LabOrderItem $item)
    {
        abort_unless($item->lab_order_id === $labOrder->id, 404);

        $validated = $request->validated();

        if (! empty($validated['lab_reagent_id'])) {
            $reagent = LabReagent::find($validated['lab_reagent_id']);
            if ($reagent->current_stock <= 0) {
                return back()->withErrors(['lab_reagent_id' => 'Selected reagent is out of stock.']);
            }
            $reagent->decrement('current_stock');

            if ($reagent->current_stock <= $reagent->min_stock_level) {
                // In a real app, trigger a notification here
                session()->flash('warning', "Reagent {$reagent->name} is running low ({$reagent->current_stock} left).");
            }
        }

        $item->update([
            'result_value' => $validated['result_value'],
            'result_flag' => $validated['result_flag'],
            'entered_by' => auth()->id(),
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'lab_reagent_id' => $validated['lab_reagent_id'] ?? null,
        ]);

        $labOrder->update([
            'status' => 'in_progress',
        ]);

        return back()->with('success', 'Result saved and pending authorization.');
    }

    /**
     * Release results under a licensed signature. Only a Medical Laboratory
     * Scientist or Pathologist may do this. Where the facility has switched on
     * `lab.require_second_person`, whoever entered a value may not sign it off,
     * and the order waits for a colleague rather than releasing in part.
     */
    public function authorizeResults(Request $request, LabOrder $labOrder)
    {
        $this->authorize('authorizeResults', $labOrder);

        $items = $labOrder->items()->with('labTest')->whereNull('authorized_at')->whereNotNull('performed_at')->get();

        $blocked = $items->reject(fn (LabOrderItem $item) => Gate::allows('authorizeResult', $item));

        if ($blocked->isNotEmpty()) {
            $names = $blocked->map(fn (LabOrderItem $item) => $item->labTest->name)->implode(', ');

            return back()->withErrors([
                'authorize' => "You entered the result(s) for {$names}, so a second authorised person must sign them off.",
            ]);
        }

        foreach ($items as $item) {
            $item->update([
                'authorized_by' => auth()->id(),
                'authorized_at' => now(),
            ]);
        }

        if ($labOrder->allResultsAuthorized()) {
            $labOrder->update(['status' => 'completed']);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'lab_results_authorized',
            'description' => "{$items->count()} result(s) authorised on lab order #{$labOrder->id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $labOrder->visit_id,
                'lab_order_id' => $labOrder->id,
                'lab_order_item_ids' => $items->pluck('id')->all(),
            ],
        ]);

        return back()->with('success', 'Results authorized.');
    }

    public function verify(Request $request, LabOrder $labOrder)
    {
        $this->authorize('verify', $labOrder);

        abort_unless($labOrder->allResultsAuthorized(), 422, 'All results must be authorized before final verification.');

        $labOrder->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        foreach ($labOrder->items as $item) {
            Invoice::addCharge(
                $labOrder->visit,
                $item->labTest->name,
                'Lab',
                (float) $item->labTest->price,
                $item->labTest->is_nhis_covered,
                1,
                $item->labTest->nhis_tariff !== null ? (float) $item->labTest->nhis_tariff : null,
            );
        }

        // A visit may have several orders in flight. Only call the patient back to
        // the doctor once the laboratory has finished with all of them, otherwise
        // the clinician is handed a partial picture.
        $stillOutstanding = $labOrder->visit->outstandingLabOrders()->count();

        if ($stillOutstanding === 0) {
            $labOrder->visit->updateStatus('waiting_doctor');
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'lab_order_verified',
            'description' => "Lab order #{$labOrder->id} verified for visit {$labOrder->visit->visit_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $labOrder->visit_id,
                'lab_order_id' => $labOrder->id,
                'orders_still_outstanding' => $stillOutstanding,
            ],
        ]);

        $message = $stillOutstanding === 0
            ? "Results verified and sent back to the doctor for {$labOrder->visit->patient->full_name}."
            : "Results verified. {$stillOutstanding} other order(s) for {$labOrder->visit->patient->full_name} are still in the laboratory.";

        return redirect()->route('lab.index')->with('success', $message);
    }

    /**
     * The ordering clinician records that they have read the results and what
     * they are doing about them. Any critical result must be acknowledged first.
     */
    public function review(Request $request, LabOrder $labOrder)
    {
        $this->authorize('review', $labOrder);

        abort_unless($labOrder->status === 'verified', 422, 'Results can only be reviewed once the laboratory has verified them.');

        $validated = $request->validate([
            'review_action' => ['required', Rule::in(LabOrder::REVIEW_ACTIONS)],
            'review_note' => 'nullable|string',
        ]);

        $unacknowledged = $labOrder->unacknowledgedCriticalItems();

        if ($unacknowledged->isNotEmpty()) {
            return back()->withInput()->withErrors([
                'review_action' => 'Acknowledge the critical result(s) on this order before recording a review.',
            ]);
        }

        $labOrder->update([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_action' => $validated['review_action'],
            'review_note' => $validated['review_note'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'lab_results_reviewed',
            'description' => "Lab order #{$labOrder->id} results reviewed ({$validated['review_action']}).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $labOrder->visit_id,
                'lab_order_id' => $labOrder->id,
                'review_action' => $validated['review_action'],
            ],
        ]);

        return back()->with('success', 'Results marked as reviewed.');
    }

    /**
     * Acknowledge a critical result by name. This is the read-back record.
     */
    public function acknowledgeCritical(Request $request, LabOrder $labOrder, LabOrderItem $item)
    {
        $this->authorize('acknowledgeCritical', $labOrder);

        abort_unless($item->lab_order_id === $labOrder->id, 404);
        abort_unless($item->isCritical(), 422, 'Only a critical result needs acknowledgement.');

        if ($item->critical_acknowledged_at !== null) {
            return back()->with('success', 'That critical result was already acknowledged.');
        }

        $item->update([
            'critical_acknowledged_by' => auth()->id(),
            'critical_acknowledged_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'critical_result_acknowledged',
            'description' => "Critical result for {$item->labTest->name} acknowledged on lab order #{$labOrder->id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $labOrder->visit_id,
                'lab_order_id' => $labOrder->id,
                'lab_order_item_id' => $item->id,
            ],
        ]);

        return back()->with('success', 'Critical result acknowledged.');
    }
}
