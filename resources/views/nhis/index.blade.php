@extends('layouts.app')

@section('title', 'NHIS Claims')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">NHIS Claims</h1>
        <p class="text-slate-500 mt-1">Generate, submit, and track claims with the National Health Insurance Authority.</p>
    </div>

    @if($readyToClaim->isNotEmpty())
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Ready to claim</h3>
            <p class="text-sm text-slate-500 mt-1">Invoices with NHIS-covered charges that don't have a claim yet.</p>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($readyToClaim as $invoice)
                <div class="px-8 py-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $invoice->visit->patient->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $invoice->visit->visit_number }} &bull; GHS {{ number_format($invoice->nhis_amount, 2) }} NHIS-covered</p>
                    </div>
                    @if($invoice->visit->nhis_verified)
                        <form action="{{ route('nhis.claims.generate', $invoice) }}" method="POST">
                            @csrf
                            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-blue-700 transition-all shadow-sm">
                                Generate claim
                            </button>
                        </form>
                    @else
                        <span class="text-xs font-semibold text-red-600 bg-red-50 px-3 py-2 rounded-lg">Eligibility not verified at registration</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Claims</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Claim</th>
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Amount</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($claims as $claim)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5 text-sm font-semibold text-slate-900">{{ $claim->claim_number }}</td>
                        <td class="px-8 py-5 text-sm text-slate-600">{{ $claim->invoice->visit->patient->full_name }}</td>
                        <td class="px-8 py-5 text-sm font-semibold text-slate-900">GHS {{ number_format($claim->amount_claimed, 2) }}</td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($claim->status === 'paid') bg-green-100 text-green-700
                                @elseif($claim->status === 'rejected') bg-red-100 text-red-700
                                @elseif($claim->status === 'vetted') bg-blue-100 text-blue-700
                                @elseif($claim->status === 'submitted') bg-orange-100 text-orange-700
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ ucfirst($claim->status) }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('nhis.show', $claim) }}" class="text-blue-600 hover:text-blue-700 font-semibold text-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-8 py-16 text-center text-slate-500 font-medium text-sm">No claims yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($claims->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $claims->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
