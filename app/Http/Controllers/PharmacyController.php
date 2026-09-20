<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function index()
    {
        $prescriptions = Prescription::with(['visit.patient', 'visit.department', 'items.drug'])
            ->whereIn('status', ['active', 'partially_dispensed'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('pharmacy.index', compact('prescriptions'));
    }

    public function show(Prescription $prescription)
    {
        $prescription->load(['visit.patient', 'items.drug.batches', 'allergyChecks']);

        return view('pharmacy.show', compact('prescription'));
    }

    public function dispenseItem(Request $request, Prescription $prescription, PrescriptionItem $item)
    {
        abort_unless($item->prescription_id === $prescription->id, 404);

        $remainingToDispense = $item->quantity_to_dispense - $item->quantity_dispensed;

        $validated = $request->validate([
            'quantity' => "required|integer|min:1|max:{$remainingToDispense}",
        ]);

        $drug = $item->drug;
        $available = $drug->stock_level;

        if ($validated['quantity'] > $available) {
            return back()->withErrors(['quantity' => "Only {$available} units of {$drug->name} are in stock."]);
        }

        $dispensed = $drug->deductStock($validated['quantity'], $item->id, auth()->id());

        $item->increment('quantity_dispensed', $dispensed);

        $unitPrice = $item->nhis_covered_snapshot ? $drug->nhis_price : $drug->cash_price;
        Invoice::addCharge($prescription->visit, $drug->name, 'Pharmacy', (float) $unitPrice, $item->nhis_covered_snapshot, $dispensed);

        $this->refreshPrescriptionStatus($prescription->fresh());

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'medication_dispensed',
            'description' => "Dispensed {$dispensed} unit(s) of {$drug->name} for prescription #{$prescription->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['prescription_id' => $prescription->id, 'prescription_item_id' => $item->id, 'quantity' => $dispensed],
        ]);

        return back()->with('success', "Dispensed {$dispensed} unit(s) of {$drug->name}.");
    }

    protected function refreshPrescriptionStatus(Prescription $prescription): void
    {
        $items = $prescription->items;
        $allDispensed = $items->every(fn (PrescriptionItem $item) => $item->quantity_dispensed >= $item->quantity_to_dispense);
        $anyDispensed = $items->contains(fn (PrescriptionItem $item) => $item->quantity_dispensed > 0);

        $prescription->update([
            'status' => $allDispensed ? 'dispensed' : ($anyDispensed ? 'partially_dispensed' : 'active'),
        ]);

        if (! $allDispensed) {
            return;
        }

        $visit = $prescription->visit;

        if ($visit->status !== 'waiting_pharmacy') {
            return;
        }

        $stillWaiting = $visit->prescriptions()
            ->whereIn('status', ['active', 'partially_dispensed'])
            ->exists();

        if (! $stillWaiting) {
            $visit->update(['closed_at' => now()]);
            $visit->updateStatus('completed');
        }
    }
}
