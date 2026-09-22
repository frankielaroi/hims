@extends('layouts.app')

@section('title', 'Consultation Queue')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Consultation queue</h1>
            <p class="text-sm text-slate-500 mt-0.5">Patients ready to be seen, and cases you've started but not yet signed.</p>
        </div>
        <div class="flex items-center gap-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 shadow-sm px-3.5 py-2 rounded-lg shrink-0">
            <span class="w-2 h-2 bg-brand-500 rounded-full animate-pulse"></span>
            <span>{{ $visits->total() }} on the list</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                        <th class="px-5 py-2.5">Queue</th>
                        <th class="px-5 py-2.5">Patient</th>
                        <th class="px-5 py-2.5">Chief complaint</th>
                        <th class="px-5 py-2.5">Case state</th>
                        <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($visits as $visit)
                    @php
                        $consultation = $visit->latestConsultation;
                        $heldByOther = $consultation && $consultation->isLockedByAnother(auth()->id());
                    @endphp
                    <tr class="hover:bg-brand-50/40 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="text-lg font-semibold text-slate-900">#{{ $visit->queue_number }}</div>
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full
                                    @if($visit->priority == 'emergency') bg-red-50 text-red-700 border border-red-100
                                    @elseif($visit->priority == 'urgent') bg-amber-50 text-amber-700 border border-amber-100
                                    @else bg-brand-50 text-brand-700 border border-brand-100 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="text-sm font-medium text-slate-900">{{ $visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($visit->patient->sex) }} &bull; {{ $visit->patient->age }} years</div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="text-sm text-slate-700">{{ $visit->latestTriage->chief_complaint ?? 'Nothing recorded' }}</div>
                            @if($visit->latestVitals)
                                <div class="flex gap-3 mt-0.5">
                                    <span class="text-xs text-slate-500">Temp {{ $visit->latestVitals->temperature }}°C</span>
                                    <span class="text-xs text-slate-500">BP {{ $visit->latestVitals->systolic_bp }}/{{ $visit->latestVitals->diastolic_bp }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            @if(! $consultation)
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-50 text-slate-600 border border-slate-200">Not started</span>
                            @else
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full
                                    @if($consultation->status === \App\Models\Consultation::STATUS_IN_PROGRESS) bg-amber-50 text-amber-700 border border-amber-100
                                    @elseif($consultation->status === \App\Models\Consultation::STATUS_DRAFT) bg-blue-50 text-blue-700 border border-blue-100
                                    @else bg-slate-50 text-slate-600 border border-slate-200 @endif">
                                    {{ $consultation->status_label }}
                                </span>
                                @if($heldByOther)
                                    <div class="text-xs text-red-600 font-medium mt-1 flex items-center gap-1">
                                        <i data-lucide="lock" class="w-3 h-3"></i>
                                        {{ $consultation->lockedBy?->name ?? 'Another clinician' }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if($heldByOther && auth()->user()->hasPermission('override_consultation_lock'))
                                <form action="{{ route('consultations.break-glass', $consultation) }}" method="POST" class="inline-flex items-center gap-2"
                                      onsubmit="return confirm('Take over this case? The current editor will lose their lock and this will be recorded.');">
                                    @csrf
                                    <input type="text" name="reason" required minlength="10" placeholder="Reason for taking over"
                                           class="w-48 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-red-400 focus:outline-none">
                                    <button type="submit" class="inline-flex items-center bg-red-600 text-white px-3 py-1.5 rounded-lg font-semibold text-xs hover:bg-red-700 transition-colors">
                                        Break glass
                                    </button>
                                </form>
                            @elseif($heldByOther)
                                <span class="text-xs text-slate-400 font-medium">Locked</span>
                            @else
                                @php
                                    $resumable = in_array($consultation?->status, [
                                        \App\Models\Consultation::STATUS_DRAFT,
                                        \App\Models\Consultation::STATUS_IN_PROGRESS,
                                        \App\Models\Consultation::STATUS_READY_FOR_SIGN_OFF,
                                    ], true);
                                @endphp
                                <a href="{{ route('consultations.create', $visit) }}" class="inline-flex items-center bg-brand-600 text-white px-4 py-1.5 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors">
                                    {{ $resumable ? 'Resume' : 'Open case' }}
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 mb-3">
                                    <i data-lucide="stethoscope" class="w-6 h-6"></i>
                                </div>
                                <p class="text-sm font-medium text-slate-700">No one's waiting right now</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($visits->hasPages())
        <div class="px-5 py-3 border-t border-slate-200">
            {{ $visits->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
