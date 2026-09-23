@extends('layouts.app')

@section('title', 'IMCI - ' . $patient->full_name)

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $colours = [
        'pink' => 'bg-red-50 border-red-200 text-red-800',
        'yellow' => 'bg-amber-50 border-amber-200 text-amber-800',
        'green' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
    ];
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">IMCI assessments</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $patient->full_name }} &bull; {{ $patient->hospital_id }} &bull; {{ $assessments->count() }} assessment(s)
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center gap-2 bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to record
        </a>
    </div>

    @forelse($assessments as $assessment)
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight">
                    {{ $assessment->created_at->format('d M Y H:i') }}
                    <span class="text-slate-400 normal-case font-medium">&bull; {{ $assessment->age_months }} months</span>
                </h2>
                <span class="text-xs font-medium text-slate-400">{{ $assessment->recordedBy->name ?? '' }}</span>
            </div>

            @if($assessment->needsUrgentReferral())
                <div class="p-4 rounded-2xl bg-red-50 border border-red-200 mb-4">
                    <div class="text-xs font-bold text-red-700 uppercase tracking-widest">Refer urgently</div>
                    <p class="text-sm text-slate-800 mt-1">
                        Give pre-referral treatment and record it on the referral before the child travels.
                    </p>
                </div>
            @endif

            <div class="space-y-3">
                @foreach($assessment->classifications ?? [] as $row)
                    <div class="p-4 rounded-2xl border {{ $colours[$row['colour']] ?? 'bg-slate-50 border-slate-200' }}">
                        <div class="text-xs font-bold uppercase tracking-wider opacity-70">{{ $row['condition'] }}</div>
                        <div class="text-sm font-bold mt-0.5">{{ $row['classification'] }}</div>
                        <p class="text-sm mt-1 opacity-90">{{ $row['action'] }}</p>
                    </div>
                @endforeach
            </div>

            @if($assessment->notes)
                <p class="text-sm text-slate-600 mt-4 whitespace-pre-line">{{ $assessment->notes }}</p>
            @endif
        </div>
    @empty
        <div class="{{ $card }} text-center py-14">
            <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 mb-3 mx-auto">
                <i data-lucide="stethoscope" class="w-6 h-6"></i>
            </div>
            <p class="text-sm font-medium text-slate-700">No IMCI assessment recorded yet</p>
            <p class="text-sm text-slate-500 mt-1">Assessments are recorded from the consultation workspace during a visit.</p>
        </div>
    @endforelse
</div>
@endsection
