@extends('layouts.app')

@section('title', 'Labour Ward')

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Labour ward</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ $inLabour->count() }} in labour &bull; {{ $delivered->count() }} delivered today</p>
    </div>

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">In labour</h2>

        @forelse($inLabour as $delivery)
            <a href="{{ route('deliveries.show', $delivery) }}"
               class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors mb-2">
                <div>
                    <div class="font-semibold text-slate-900">{{ $delivery->patient->full_name }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        {{ $delivery->patient->hospital_id }}
                        @if($delivery->gestation_weeks)
                            &bull; {{ $delivery->gestation_weeks }} weeks
                        @endif
                        &bull; {{ $delivery->location() }}
                        &bull; admitted {{ $delivery->admitted_in_labour_at?->diffForHumans() }}
                    </div>
                </div>
                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-rose-50 text-rose-700 border border-rose-100">
                    {{ $delivery->partographEntries()->count() }} observation(s)
                </span>
            </a>
        @empty
            <p class="text-sm text-slate-500 py-6 text-center">Nobody is in labour right now.</p>
        @endforelse
    </div>

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Delivered today</h2>

        @forelse($delivered as $delivery)
            <a href="{{ route('deliveries.show', $delivery) }}"
               class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors mb-2">
                <div>
                    <div class="font-semibold text-slate-900">{{ $delivery->patient->full_name }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        {{ ucfirst(str_replace('_', ' ', $delivery->mode)) }}
                        &bull; {{ $delivery->delivered_at?->format('H:i') }}
                        &bull; {{ $delivery->newborns->count() }} baby/babies
                    </div>
                </div>
                @php $alerts = $delivery->alerts(); @endphp
                @if($alerts !== [])
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-amber-50 text-amber-700 border border-amber-100">
                        {{ count($alerts) }} alert(s)
                    </span>
                @endif
            </a>
        @empty
            <p class="text-sm text-slate-500 py-6 text-center">No deliveries recorded today.</p>
        @endforelse
    </div>
</div>
@endsection
