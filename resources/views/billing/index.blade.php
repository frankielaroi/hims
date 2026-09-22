@extends('layouts.app')

@section('title', 'Billing Queue')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-inherit tracking-tight">Billing</h1>
            <p class="text-inherit mt-1">Invoices with an outstanding balance.</p>
        </div>
        <div class="flex items-center space-x-2 text-sm text-slate-500 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            <span class="w-2 h-2 bg-inherit rounded-full animate-pulse"></span>
            <span>{{ $invoices->total() }} outstanding</span>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Bill total</th>
                        <th class="px-8 py-4">Paid</th>
                        <th class="px-8 py-4">Balance due</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $invoice->visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500 font-medium">{{ $invoice->visit->patient->hospital_id }} &bull; {{ $invoice->visit->department->name }}</div>
                        </td>
                        <td class="px-8 py-5 text-sm font-semibold text-slate-900">GHS {{ number_format($invoice->patient_amount, 2) }}</td>
                        <td class="px-8 py-5 text-sm font-medium text-slate-500">GHS {{ number_format($invoice->amount_paid, 2) }}</td>
                        <td class="px-8 py-5 text-sm font-bold text-red-700">GHS {{ number_format($invoice->balanceDue(), 2) }}</td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($invoice->status === 'partially_paid') bg-orange-100 text-orange-700
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('billing.show', $invoice) }}" class="bg-blue-700 text-white px-5 py-2 rounded-xl font-semibold text-sm transition-all shadow-sm">
                                Collect payment
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V15a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium text-sm">No outstanding invoices</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
