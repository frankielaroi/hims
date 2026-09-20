@extends('layouts.app')

@section('title', 'NHIS Claim')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $claim->claim_number }}</h1>
            <p class="text-slate-500 mt-1">{{ $claim->invoice->visit->patient->full_name }} &bull; Visit {{ $claim->invoice->visit->visit_number }}</p>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full
            @if($claim->status === 'paid') bg-green-100 text-green-700
            @elseif($claim->status === 'rejected') bg-red-100 text-red-700
            @elseif($claim->status === 'vetted') bg-blue-100 text-blue-700
            @elseif($claim->status === 'submitted') bg-orange-100 text-orange-700
            @else bg-slate-100 text-slate-600 @endif">
            {{ ucfirst($claim->status) }}
        </span>
    </div>

    @if($claim->status === 'rejected' && $claim->rejected_reason)
    <div class="bg-red-50 p-6 rounded-2xl border border-red-100">
        <p class="text-sm font-semibold text-red-800 mb-1">Rejection reason</p>
        <p class="text-sm text-red-700">{{ $claim->rejected_reason }}</p>
    </div>
    @endif

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Claimed items</h3>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($claim->items as $item)
                <div class="px-8 py-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">{{ $item->description }}</span>
                    <span class="text-sm font-semibold text-slate-900">GHS {{ number_format($item->amount, 2) }}</span>
                </div>
            @endforeach
        </div>
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-between">
            <span class="font-bold text-slate-900">Total claimed</span>
            <span class="font-bold text-slate-900">GHS {{ number_format($claim->amount_claimed, 2) }}</span>
        </div>
        @if($claim->status === 'paid')
        <div class="px-8 py-4 bg-green-50 border-t border-green-100 flex justify-between">
            <span class="font-semibold text-green-800">Paid by NHIA</span>
            <span class="font-semibold text-green-800">GHS {{ number_format($claim->amount_paid, 2) }} on {{ $claim->paid_at->format('d M, Y') }}</span>
        </div>
        @endif
    </div>

    <div class="flex items-center gap-3 flex-wrap">
        <a href="{{ route('nhis.export', $claim) }}" class="bg-white text-slate-700 px-6 py-3 rounded-2xl font-semibold border border-slate-200 hover:bg-slate-50 transition-all">
            Export for submission
        </a>

        @if($claim->status === 'draft')
            <form action="{{ route('nhis.submit', $claim) }}" method="POST">
                @csrf
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-100 transition-all">
                    Submit to NHIA
                </button>
            </form>
        @endif

        @if($claim->status === 'submitted')
            <form action="{{ route('nhis.vet', $claim) }}" method="POST">
                @csrf
                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-blue-700 shadow-lg shadow-blue-100 transition-all">
                    Mark as vetted
                </button>
            </form>
        @endif

        @if($claim->status === 'vetted')
            <form action="{{ route('nhis.pay', $claim) }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="number" step="0.01" name="amount_paid" max="{{ $claim->amount_claimed }}" placeholder="{{ $claim->amount_claimed }}"
                       class="px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium w-32 focus:ring-2 focus:ring-green-500">
                <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-green-700 shadow-lg shadow-green-100 transition-all">
                    Mark as paid
                </button>
            </form>
        @endif

        @if(in_array($claim->status, ['submitted', 'vetted']))
            <button type="button" onclick="document.getElementById('rejectForm').classList.toggle('hidden')" class="text-red-600 font-semibold text-sm hover:underline">
                Mark as rejected
            </button>
        @endif

        @if($claim->status === 'rejected')
            <form action="{{ route('nhis.resubmit', $claim) }}" method="POST">
                @csrf
                <button type="submit" class="bg-orange-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-orange-700 shadow-lg shadow-orange-100 transition-all">
                    Resubmit
                </button>
            </form>
        @endif
    </div>

    @if(in_array($claim->status, ['submitted', 'vetted']))
    <form id="rejectForm" action="{{ route('nhis.reject', $claim) }}" method="POST" class="hidden bg-white p-6 rounded-2xl border border-slate-200 space-y-3">
        @csrf
        <label class="block text-sm font-semibold text-slate-700">Rejection reason</label>
        <textarea name="reason" rows="2" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-red-500" placeholder="e.g. Missing documentation"></textarea>
        <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-xl font-semibold text-sm hover:bg-red-700 transition-all">
            Confirm rejection
        </button>
    </form>
    @endif
</div>
@endsection
