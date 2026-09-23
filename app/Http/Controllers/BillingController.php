<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'provider' => ['nullable', Rule::in(Payment::PROVIDERS)],
            'payer_msisdn' => 'nullable|string|max:20',
            // A cashier records money they have already seen arrive, so a
            // recorded payment is settled by default. Pending is opt-in, for a
            // mobile money request sent to the patient's handset that nobody
            // has confirmed yet — and it is pending rows that must never count
            // towards the invoice.
            'awaiting_confirmation' => 'nullable|boolean',
        ]);

        $isAsynchronous = in_array($validated['method'], Payment::ASYNCHRONOUS_METHODS, true);
        $settled = ! ($isAsynchronous && $request->boolean('awaiting_confirmation'));

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => $this->generateReceiptNumber(),
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'status' => $settled ? Payment::STATUS_SUCCESSFUL : Payment::STATUS_PENDING,
            'provider' => $validated['provider'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'payer_msisdn' => $validated['payer_msisdn'] ?? null,
            'confirmed_at' => $settled ? now() : null,
            'received_by' => auth()->id(),
        ]);

        $invoice->recalculateTotals();

        if (! $settled) {
            return back()->with(
                'warning',
                "Mobile money request of GHS {$payment->amount} recorded as pending. "
                .'It will not count against the invoice until the payment is confirmed.'
            );
        }

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

    /**
     * Settle or fail a pending mobile money payment.
     *
     * Until a gateway is wired in, the cashier confirms against their own
     * merchant app. The transition is the same either way, so the webhook can
     * take this path later without the invoice logic changing.
     */
    public function settlePayment(Request $request, Payment $payment)
    {
        abort_unless($payment->isPending(), 422, 'Only a pending payment can be settled.');

        $validated = $request->validate([
            'outcome' => ['required', Rule::in([Payment::STATUS_SUCCESSFUL, Payment::STATUS_FAILED])],
            'failure_reason' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string',
        ]);

        $successful = $validated['outcome'] === Payment::STATUS_SUCCESSFUL;

        $payment->update([
            'status' => $validated['outcome'],
            'failure_reason' => $successful ? null : ($validated['failure_reason'] ?? null),
            'reference_number' => $validated['reference_number'] ?? $payment->reference_number,
            'confirmed_at' => $successful ? now() : null,
        ]);

        $payment->invoice->recalculateTotals();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'payment_'.$validated['outcome'],
            'description' => "Payment {$payment->receipt_number} marked {$validated['outcome']}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'amount' => (string) $payment->amount,
            ],
        ]);

        return back()->with(
            $successful ? 'success' : 'warning',
            $successful
                ? "Payment {$payment->receipt_number} confirmed."
                : "Payment {$payment->receipt_number} marked failed; the balance is unchanged."
        );
    }

    protected function generateReceiptNumber(): string
    {
        $prefix = 'RCT';
        $date = date('Ymd');
        $count = Payment::whereDate('created_at', date('Y-m-d'))->count() + 1;

        return "{$prefix}-{$date}-".str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
