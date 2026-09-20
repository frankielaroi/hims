@extends('layouts.app')

@section('title', 'Invoice')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-amber-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-amber-100">
                {{ substr($invoice->visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $invoice->visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $invoice->visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">Visit {{ $invoice->visit->visit_number }}</span>
                </div>
            </div>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full
            @if($invoice->status === 'paid') bg-green-100 text-green-700
            @elseif($invoice->status === 'partially_paid') bg-orange-100 text-orange-700
            @else bg-slate-100 text-slate-600 @endif">
            {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
        </span>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Charges</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-3">Description</th>
                        <th class="px-8 py-3">Category</th>
                        <th class="px-8 py-3">Qty</th>
                        <th class="px-8 py-3">Payer</th>
                        <th class="px-8 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-8 py-4 text-sm font-semibold text-slate-900">{{ $item->description }}</td>
                        <td class="px-8 py-4 text-sm text-slate-500">{{ $item->category }}</td>
                        <td class="px-8 py-4 text-sm text-slate-500">{{ $item->quantity }}</td>
                        <td class="px-8 py-4">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-md {{ $item->payer === 'nhis' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $item->payer === 'nhis' ? 'NHIS' : 'Cash' }}
                            </span>
                        </td>
                        <td class="px-8 py-4 text-right text-sm font-semibold text-slate-900">GHS {{ number_format($item->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Total charges</span>
                <span class="font-semibold text-slate-900">GHS {{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Covered by NHIS</span>
                <span class="font-semibold text-blue-700">GHS {{ number_format($invoice->nhis_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Patient owes</span>
                <span class="font-semibold text-slate-900">GHS {{ number_format($invoice->patient_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Paid so far</span>
                <span class="font-semibold text-green-700">GHS {{ number_format($invoice->amount_paid, 2) }}</span>
            </div>
            <div class="flex justify-between text-base pt-2 border-t border-slate-200">
                <span class="font-bold text-slate-900">Balance due</span>
                <span class="font-bold text-red-600">GHS {{ number_format($invoice->balanceDue(), 2) }}</span>
            </div>
        </div>
    </div>

    @if($invoice->payments->isNotEmpty())
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
        <h3 class="text-lg font-bold text-slate-900 mb-4">Payment history</h3>
        <div class="space-y-3">
            @foreach($invoice->payments as $payment)
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $payment->receipt_number }}</p>
                        <p class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $payment->method)) }} &bull; {{ $payment->receivedBy->name }} &bull; {{ $payment->created_at->format('d M, Y H:i') }}</p>
                    </div>
                    <span class="text-sm font-bold text-slate-900">GHS {{ number_format($payment->amount, 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($invoice->balanceDue() > 0)
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Collect payment</h3>
        <form action="{{ route('billing.payments.store', $invoice) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Amount (GHS)</label>
                <input type="number" step="0.01" name="amount" min="0.01" max="{{ $invoice->balanceDue() }}" value="{{ $invoice->balanceDue() }}" required
                       class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Method</label>
                <select name="method" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-amber-500">
                    <option value="cash">Cash</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="card">Card</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Reference (optional)</label>
                <input type="text" name="reference_number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-amber-500" placeholder="e.g. MoMo transaction ID">
            </div>
            <div class="md:col-span-3">
                <button type="submit" class="w-full py-4 bg-amber-600 text-white rounded-2xl font-semibold hover:bg-amber-700 shadow-lg shadow-amber-100 transition-all">
                    Record payment
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection
