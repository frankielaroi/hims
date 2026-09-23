@extends('layouts.app')

@section('title', 'Postnatal Care')

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $styles = [
        'due' => 'bg-amber-50 text-amber-700 border-amber-100',
        'overdue' => 'bg-red-50 text-red-700 border-red-100',
    ];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Postnatal care</h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Ghana's 6-hour, 6-day and 6-week contacts. Most maternal and newborn deaths happen in these first days.
        </p>
    </div>

    @forelse($outstanding as $row)
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <a href="{{ route('deliveries.show', $row['delivery']) }}" class="font-semibold text-slate-900 hover:text-rose-600">
                        {{ $row['delivery']->patient->full_name }}
                    </a>
                    <div class="text-xs text-slate-500 mt-0.5">
                        {{ $row['delivery']->patient->hospital_id }}
                        &bull; delivered {{ $row['delivery']->delivered_at->format('d M Y') }}
                        &bull; {{ $row['delivery']->newborns->count() }} baby/babies
                        &bull; {{ $row['delivery']->location() }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($row['contacts'] as $contact)
                        <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full border {{ $styles[$contact['status']] }}">
                            {{ $contact['label'] }} {{ $contact['status'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @empty
        <div class="{{ $card }} text-center py-14">
            <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 mb-3 mx-auto">
                <i data-lucide="check" class="w-6 h-6"></i>
            </div>
            <p class="text-sm font-medium text-slate-700">No postnatal contacts are due</p>
            <p class="text-sm text-slate-500 mt-1">Every mother delivered in the last three months is up to date.</p>
        </div>
    @endforelse
</div>
@endsection
