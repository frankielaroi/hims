@extends('layouts.app')

@section('title', 'Clinical Timeline - ' . $patient->full_name)

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';

    $tone = [
        'visit' => ['bg-slate-100', 'text-slate-600'],
        'consultation' => ['bg-brand-50', 'text-brand-600'],
        'lab' => ['bg-purple-50', 'text-purple-600'],
        'radiology' => ['bg-indigo-50', 'text-indigo-600'],
        'prescription' => ['bg-emerald-50', 'text-emerald-600'],
        'admission' => ['bg-amber-50', 'text-amber-600'],
        'referral' => ['bg-sky-50', 'text-sky-600'],
        'dama' => ['bg-orange-50', 'text-orange-600'],
        'death' => ['bg-red-50', 'text-red-600'],
    ];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Clinical timeline</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $patient->full_name }} &bull; {{ $patient->hospital_id }} &bull;
                {{ ucfirst($patient->sex) }}, {{ $patient->age }} years
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center gap-2 bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to record
        </a>
    </div>

    <div class="{{ $card }}">
        @if($entries->isEmpty())
            <div class="py-14 text-center">
                <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 mx-auto mb-3">
                    <i data-lucide="history" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-medium text-slate-700">Nothing recorded for this patient yet</p>
            </div>
        @else
            <div class="relative pl-8 space-y-8 before:absolute before:left-[0.9375rem] before:top-2 before:bottom-2 before:w-px before:bg-slate-200">
                @foreach($entries as $entry)
                    @php [$bg, $fg] = $tone[$entry['type']] ?? ['bg-slate-100', 'text-slate-600']; @endphp
                    <div class="relative">
                        <div class="absolute -left-8 top-0 w-8 h-8 rounded-full {{ $bg }} {{ $fg }} flex items-center justify-center ring-4 ring-white">
                            <i data-lucide="{{ $entry['icon'] }}" class="w-4 h-4"></i>
                        </div>

                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="text-sm font-bold text-slate-900">{{ $entry['title'] }}</h2>
                            <span class="text-xs font-medium text-slate-400">
                                {{ $entry['occurred_at']?->format('d M Y, H:i') }}
                                @if($entry['visit_number'])
                                    &bull; {{ $entry['visit_number'] }}
                                @endif
                            </span>
                        </div>

                        <p class="text-sm text-slate-600 mt-0.5">{{ $entry['summary'] }}</p>

                        @if($entry['detail'])
                            <ul class="mt-2 space-y-1">
                                @foreach($entry['detail'] as $line)
                                    <li class="text-xs text-slate-500 pl-3 border-l-2 border-slate-100">{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
