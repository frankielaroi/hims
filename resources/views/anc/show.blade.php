@extends('layouts.app')

@section('title', 'Antenatal Record - ' . $patient->full_name)

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Antenatal record</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $patient->full_name }} &bull; {{ $patient->hospital_id }} &bull; {{ $assessments->count() }} contact(s)
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center gap-2 bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to record
        </a>
    </div>

    @forelse($assessments as $assessment)
        @php $danger = $assessment->dangerSigns(); @endphp
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight">
                    Contact {{ $assessment->contact_number }}
                    @if($assessment->gestational_age_weeks)
                        <span class="text-slate-400 normal-case font-medium">&bull; {{ $assessment->gestational_age_weeks }} weeks</span>
                    @endif
                </h2>
                <span class="text-xs font-medium text-slate-400">
                    {{ $assessment->created_at->format('d M Y') }} &bull; {{ $assessment->recordedBy->name ?? 'unknown' }}
                </span>
            </div>

            @if($danger)
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <p class="text-sm font-bold text-red-900 mb-1">Danger signs</p>
                    <ul class="space-y-0.5">
                        @foreach($danger as $sign)
                            <li class="text-sm text-red-800">{{ $sign }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Blood pressure</dt>
                    <dd class="font-medium {{ $assessment->isHypertensive() ? 'text-red-600' : 'text-slate-900' }}">
                        {{ $assessment->systolic_bp ?? '—' }}/{{ $assessment->diastolic_bp ?? '—' }}
                    </dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Weight</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->maternal_weight_kg ? $assessment->maternal_weight_kg.' kg' : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Fundal height</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->fundal_height_cm ? $assessment->fundal_height_cm.' cm' : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Fetal heart</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->fetal_heart_rate ? $assessment->fetal_heart_rate.' bpm' : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Presentation</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->presentation ? ucfirst(str_replace('_', ' ', $assessment->presentation)) : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Haemoglobin</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->haemoglobin ? $assessment->haemoglobin.' g/dL' : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Urine protein</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->urinalysis_protein ?? '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Next contact</dt>
                    <dd class="font-medium text-slate-900">{{ $assessment->next_contact_due?->format('d M Y') ?? '—' }}</dd></div>
            </dl>

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach(['tt_given' => 'Tetanus toxoid', 'iptp_given' => 'IPTp', 'itn_given' => 'Insecticide-treated net'] as $field => $title)
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full border
                        {{ $assessment->{$field} ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                        {{ $title }} {{ $assessment->{$field} ? 'given' : 'not given' }}
                    </span>
                @endforeach
            </div>

            @if($assessment->notes)
                <p class="text-sm text-slate-600 mt-4 whitespace-pre-line">{{ $assessment->notes }}</p>
            @endif
        </div>
    @empty
        <div class="{{ $card }} text-center py-14">
            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-purple-600 mb-3 mx-auto">
                <i data-lucide="baby" class="w-6 h-6"></i>
            </div>
            <p class="text-sm font-medium text-slate-700">No antenatal contacts recorded yet</p>
            <p class="text-sm text-slate-500 mt-1">Contacts are recorded from the consultation workspace during a visit.</p>
        </div>
    @endforelse
</div>
@endsection
