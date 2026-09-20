@extends('layouts.app')

@section('title', 'Consultation Queue')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Consultation queue</h1>
            <p class="text-slate-500 mt-1">Patients who've been triaged and are ready to be seen.</p>
        </div>
        <div class="flex items-center space-x-2 text-sm text-slate-500 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
            <span>{{ $visits->total() }} waiting</span>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Queue</th>
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Chief complaint</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($visits as $visit)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center space-x-3">
                                <div class="text-lg font-bold text-slate-900">#{{ $visit->queue_number }}</div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($visit->priority == 'emergency') bg-red-100 text-red-700
                                    @elseif($visit->priority == 'urgent') bg-orange-100 text-orange-700
                                    @else bg-blue-100 text-blue-700 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500 font-medium">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-sm text-slate-600 italic">"{{ $visit->latestTriage->chief_complaint ?? 'Nothing recorded' }}"</div>
                            @if($visit->latestVitals)
                                <div class="flex gap-3 mt-1">
                                    <span class="text-xs text-slate-400">Temp: {{ $visit->latestVitals->temperature }}°C</span>
                                    <span class="text-xs text-slate-400">BP: {{ $visit->latestVitals->systolic_bp }}/{{ $visit->latestVitals->diastolic_bp }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('consultations.create', $visit) }}" class="bg-blue-600 text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-blue-700 transition-all shadow-sm">
                                Open case
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center text-slate-500 font-medium text-sm">No one's waiting right now</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
