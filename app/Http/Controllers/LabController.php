<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index()
    {
        $orders = LabOrder::with(['visit.patient', 'visit.department', 'items.labTest'])
            ->whereNotIn('status', ['verified', 'cancelled'])
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('lab.index', compact('orders'));
    }

    public function show(LabOrder $labOrder)
    {
        $labOrder->load(['visit.patient', 'items.labTest', 'orderedBy']);

        return view('lab.show', compact('labOrder'));
    }

    public function collectSpecimen(Request $request, LabOrder $labOrder, LabOrderItem $item)
    {
        abort_unless($item->lab_order_id === $labOrder->id, 404);

        $item->update([
            'specimen_status' => 'collected',
            'specimen_collected_by' => auth()->id(),
            'specimen_collected_at' => now(),
        ]);

        if ($labOrder->status === 'ordered') {
            $labOrder->update(['status' => 'specimen_collected']);
        }

        return back()->with('success', 'Specimen marked as collected.');
    }

    public function saveResult(Request $request, LabOrder $labOrder, LabOrderItem $item)
    {
        abort_unless($item->lab_order_id === $labOrder->id, 404);

        $validated = $request->validate([
            'result_value' => 'required|string',
            'result_flag' => 'required|in:normal,abnormal,critical',
        ]);

        $item->update([
            'result_value' => $validated['result_value'],
            'result_flag' => $validated['result_flag'],
            'performed_by' => auth()->id(),
            'performed_at' => now(),
        ]);

        $labOrder->update([
            'status' => $labOrder->allResultsRecorded() ? 'completed' : 'in_progress',
        ]);

        return back()->with('success', 'Result saved.');
    }

    public function verify(Request $request, LabOrder $labOrder)
    {
        abort_unless($labOrder->allResultsRecorded(), 422, 'All results must be recorded before verification.');

        $labOrder->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        $labOrder->visit->updateStatus('waiting_doctor');

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'lab_order_verified',
            'description' => "Lab order #{$labOrder->id} verified for visit {$labOrder->visit->visit_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['visit_id' => $labOrder->visit_id, 'lab_order_id' => $labOrder->id],
        ]);

        return redirect()->route('lab.index')
            ->with('success', "Results verified and sent back to the doctor for {$labOrder->visit->patient->full_name}.");
    }
}
