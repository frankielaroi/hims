<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with(['visit.patient', 'visit.department'])
            ->whereIn('status', ['open', 'partially_paid'])
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('billing.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['visit.patient', 'items', 'payments.receivedBy']);

        return view('billing.show', compact('invoice'));
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $balance = $invoice->balanceDue();

        $validated = $request->validate([
            'amount' => "required|numeric|min:0.01|max:{$balance}",
            'method' => 'required|in:cash,mobile_money,card',
            'reference_number' => 'nullable|string',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference_number' => $validated['reference_number'] ?? null,
            'received_by' => auth()->id(),
        ]);

        $invoice->recalculateTotals();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'payment_recorded',
            'description' => "Payment {$payment->receipt_number} of GHS {$payment->amount} recorded for invoice #{$invoice->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'amount' => (string) $payment->amount],
        ]);

        return back()->with('success', "Payment of GHS {$payment->amount} recorded. Receipt: {$payment->receipt_number}.");
    }

    protected function generateReceiptNumber(): string
    {
        $prefix = 'RCT';
        $date = date('Ymd');
        $count = Payment::whereDate('created_at', date('Y-m-d'))->count() + 1;

        return "{$prefix}-{$date}-".str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
