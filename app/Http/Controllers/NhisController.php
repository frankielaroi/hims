<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\NhisClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NhisController extends Controller
{
    public function index()
    {
        $readyToClaim = Invoice::with(['visit.patient'])
            ->where('nhis_amount', '>', 0)
            ->whereDoesntHave('claim')
            ->get();

        $claims = NhisClaim::with(['invoice.visit.patient'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('nhis.index', compact('readyToClaim', 'claims'));
    }

    public function generateClaim(Request $request, Invoice $invoice)
    {
        abort_if($invoice->claim, 422, 'A claim already exists for this invoice.');
        abort_unless($invoice->nhis_amount > 0, 422, 'This invoice has no NHIS-covered charges.');
        abort_unless($invoice->visit->nhis_verified, 422, 'NHIS eligibility for this visit has not been verified yet.');

        $claim = DB::transaction(function () use ($invoice) {
            $claim = NhisClaim::create([
                'invoice_id' => $invoice->id,
                'claim_number' => $this->generateClaimNumber(),
                'status' => 'draft',
                'amount_claimed' => $invoice->nhis_amount,
                'created_by' => auth()->id(),
            ]);

            foreach ($invoice->items()->where('payer', 'nhis')->get() as $item) {
                $claim->items()->create([
                    'invoice_item_id' => $item->id,
                    'description' => $item->description,
                    'amount' => $item->nhis_amount,
                ]);
            }

            return $claim;
        });

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'nhis_claim_generated',
            'description' => "Claim {$claim->claim_number} generated for invoice #{$invoice->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['invoice_id' => $invoice->id, 'claim_id' => $claim->id],
        ]);

        return redirect()->route('nhis.show', $claim)->with('success', "Claim {$claim->claim_number} created.");
    }

    public function show(NhisClaim $claim)
    {
        $claim->load(['invoice.visit.patient', 'items']);

        return view('nhis.show', compact('claim'));
    }

    public function submit(Request $request, NhisClaim $claim)
    {
        abort_unless($claim->status === 'draft', 422, 'Only draft claims can be submitted.');

        $claim->submit();
        $this->logStatusChange($request, $claim, 'nhis_claim_submitted');

        return back()->with('success', "Claim {$claim->claim_number} submitted to NHIA.");
    }

    public function markVetted(Request $request, NhisClaim $claim)
    {
        abort_unless($claim->status === 'submitted', 422, 'Only submitted claims can be vetted.');

        $claim->markVetted();
        $this->logStatusChange($request, $claim, 'nhis_claim_vetted');

        return back()->with('success', "Claim {$claim->claim_number} marked as vetted.");
    }

    public function markPaid(Request $request, NhisClaim $claim)
    {
        abort_unless($claim->status === 'vetted', 422, 'Only vetted claims can be marked paid.');

        $validated = $request->validate([
            'amount_paid' => 'nullable|numeric|min:0|max:'.$claim->amount_claimed,
        ]);

        $claim->markPaid($validated['amount_paid'] ?? null);
        $this->logStatusChange($request, $claim, 'nhis_claim_paid');

        return back()->with('success', "Claim {$claim->claim_number} marked as paid.");
    }

    public function reject(Request $request, NhisClaim $claim)
    {
        abort_unless(in_array($claim->status, ['submitted', 'vetted'], true), 422, 'Only submitted or vetted claims can be rejected.');

        $validated = $request->validate(['reason' => 'required|string']);

        $claim->reject($validated['reason']);
        $this->logStatusChange($request, $claim, 'nhis_claim_rejected');

        return back()->with('success', "Claim {$claim->claim_number} marked as rejected.");
    }

    public function resubmit(Request $request, NhisClaim $claim)
    {
        abort_unless($claim->status === 'rejected', 422, 'Only rejected claims can be resubmitted.');

        $claim->resubmit();
        $this->logStatusChange($request, $claim, 'nhis_claim_resubmitted');

        return back()->with('success', "Claim {$claim->claim_number} resubmitted to NHIA.");
    }

    public function export(NhisClaim $claim)
    {
        $claim->load(['invoice.visit.patient', 'items']);

        $payload = [
            'claim_number' => $claim->claim_number,
            'facility' => config('app.name'),
            'patient' => [
                'name' => $claim->invoice->visit->patient->full_name,
                'nhis_number' => $claim->invoice->visit->nhis_number_snapshot,
                'hospital_id' => $claim->invoice->visit->patient->hospital_id,
            ],
            'visit_number' => $claim->invoice->visit->visit_number,
            'amount_claimed' => (string) $claim->amount_claimed,
            'items' => $claim->items->map(fn ($item) => [
                'description' => $item->description,
                'amount' => (string) $item->amount,
            ]),
        ];

        return response()->json($payload)
            ->header('Content-Disposition', "attachment; filename=\"{$claim->claim_number}.json\"");
    }

    protected function logStatusChange(Request $request, NhisClaim $claim, string $action): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => "Claim {$claim->claim_number} status changed to {$claim->status}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['claim_id' => $claim->id, 'status' => $claim->status],
        ]);
    }

    protected function generateClaimNumber(): string
    {
        $prefix = 'CLM';
        $date = date('Ymd');
        $count = NhisClaim::whereDate('created_at', date('Y-m-d'))->count() + 1;

        return "{$prefix}-{$date}-".str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
