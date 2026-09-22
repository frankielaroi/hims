@extends('layouts.app')

@section('title', 'Invoice')

@section('content')
@php
    $input = 'w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none';
    $label = 'block text-xs font-medium text-slate-600 mb-1';
    $pill  = 'block text-center px-3 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-600 cursor-pointer transition hover:bg-slate-50 peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40';
    $balance = $invoice->balanceDue();
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-brand-50 flex items-center justify-center text-brand-700 text-lg font-semibold shrink-0">
                {{ strtoupper(substr($invoice->visit->patient->full_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-xl font-semibold text-slate-900 tracking-tight">{{ $invoice->visit->patient->full_name }}</h1>
                <div class="flex items-center gap-2.5 mt-0.5">
                    <span class="text-sm text-slate-500">{{ $invoice->visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm text-slate-500">Visit {{ $invoice->visit->visit_number }}</span>
                </div>
            </div>
        </div>
        <span class="px-3 py-1 text-xs font-medium rounded-full shrink-0
            @if($invoice->status === 'paid') bg-green-50 text-green-700 border border-green-100
            @elseif($invoice->status === 'partially_paid') bg-amber-50 text-amber-700 border border-amber-100
            @else bg-slate-100 text-slate-600 border border-slate-200 @endif">
            {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
        </span>
    </div>

    <!-- Charges -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Charges</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                        <th class="px-5 py-2.5">Description</th>
                        <th class="px-5 py-2.5">Category</th>
                        <th class="px-5 py-2.5">Qty</th>
                        <th class="px-5 py-2.5">Payer</th>
                        <th class="px-5 py-2.5 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ $item->description }}</td>
                        <td class="px-5 py-3 text-sm text-slate-500">{{ $item->category }}</td>
                        <td class="px-5 py-3 text-sm text-slate-500">{{ $item->quantity }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-md {{ $item->payer === 'nhis' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $item->payer === 'nhis' ? 'NHIS' : 'Cash' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-sm font-medium text-slate-900 whitespace-nowrap">GHS {{ number_format($item->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 space-y-1.5">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Total charges</span>
                <span class="font-medium text-slate-900">GHS {{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Covered by NHIS</span>
                <span class="font-medium text-brand-700">GHS {{ number_format($invoice->nhis_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Patient owes</span>
                <span class="font-medium text-slate-900">GHS {{ number_format($invoice->patient_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Paid so far</span>
                <span class="font-medium text-green-700">GHS {{ number_format($invoice->amount_paid, 2) }}</span>
            </div>
            <div class="flex justify-between items-baseline text-base pt-2 mt-2 border-t border-slate-200">
                <span class="font-semibold text-slate-900">Balance due</span>
                <span class="text-lg font-semibold {{ $balance > 0 ? 'text-red-600' : 'text-green-700' }}">GHS {{ number_format($balance, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Payment history -->
    @if($invoice->payments->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Payment history</h2>
        <div class="space-y-2">
            @foreach($invoice->payments as $payment)
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ $payment->receipt_number }}</p>
                        <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $payment->method)) }} &bull; {{ $payment->receivedBy->name }} &bull; {{ $payment->created_at->format('d M, Y H:i') }}</p>
                    </div>
                    <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">GHS {{ number_format($payment->amount, 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Collect payment -->
    @if($balance > 0)
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-5 py-3 border-b border-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Collect payment</h2>
        </div>
        <form action="{{ route('billing.payments.store', $invoice) }}" method="POST">
            @csrf
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
                <div>
                    <label for="amount" class="{{ $label }}">Amount (GHS)</label>
                    <input type="number" step="0.01" id="amount" name="amount" min="0.01" max="{{ $balance }}" value="{{ old('amount', $balance) }}" required class="{{ $input }}">
                </div>
                <div>
                    <label for="reference_number" class="{{ $label }}">Reference (optional)</label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}" class="{{ $input }}" placeholder="e.g. MoMo transaction ID">
                </div>
                <div class="md:col-span-2">
                    <span class="{{ $label }}">Method</span>
                    <div class="grid grid-cols-3 gap-2 md:max-w-md">
                        @foreach(['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'card' => 'Card'] as $value => $text)
                            <label>
                                <input type="radio" name="method" value="{{ $value }}" required class="peer sr-only" {{ old('method', 'cash') === $value ? 'checked' : '' }}>
                                <span class="{{ $pill }}">{{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 rounded-b-xl flex justify-end">
                <button type="submit" class="bg-brand-600 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
                    Record payment
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection