@extends('layouts.app')

@section('title', 'Emergency Board')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-red-600 tracking-tight">Emergency board</h1>
            <p class="text-slate-500 mt-1">Every active emergency-priority patient, wherever they are in the system.</p>
        </div>
        @if(auth()->user()->hasPermission('register_patients'))
            <a href="{{ route('emergency.create') }}" class="bg-red-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-red-700 shadow-lg shadow-red-100 transition-all flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                New emergency arrival
            </a>
        @endif
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-red-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Stage</th>
                        <th class="px-8 py-4">Time in system</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($visits as $visit)
                    <tr class="hover:bg-red-50/30 transition-colors">
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                {{ $visit->patient->full_name }}
                                @if($visit->patient->is_unidentified)
                                    <span class="px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700 rounded-md">Unidentified</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 font-medium">{{ $visit->patient->hospital_id }} &bull; {{ $visit->reason_for_visit }}</div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-600 rounded-full">{{ ucfirst(str_replace('_', ' ', $visit->status)) }}</span>
                        </td>
                        <td class="px-8 py-5 text-sm font-medium {{ $visit->created_at->diffInMinutes(now()) > 30 ? 'text-red-600' : 'text-slate-500' }}">
                            {{ $visit->created_at->diffForHumans(null, true) }}
                        </td>
                        <td class="px-8 py-5 text-right">
                            @if($visit->status === 'waiting_triage')
                                <a href="{{ route('triage.create', $visit) }}" class="text-teal-600 hover:text-teal-700 font-semibold text-sm">Go to triage</a>
                            @elseif($visit->status === 'waiting_doctor')
                                <a href="{{ route('consultations.create', $visit) }}" class="text-blue-600 hover:text-blue-700 font-semibold text-sm">Go to consultation</a>
                            @elseif($visit->status === 'waiting_lab' && $visit->pendingLabOrder)
                                <a href="{{ route('lab.show', $visit->pendingLabOrder) }}" class="text-purple-600 hover:text-purple-700 font-semibold text-sm">Go to lab</a>
                            @elseif($visit->status === 'waiting_pharmacy' && $visit->latestPrescription)
                                <a href="{{ route('pharmacy.show', $visit->latestPrescription) }}" class="text-teal-600 hover:text-teal-700 font-semibold text-sm">Go to pharmacy</a>
                            @elseif($visit->status === 'awaiting_admission')
                                <a href="{{ route('admissions.index') }}" class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm">Assign bed</a>
                            @elseif($visit->status === 'admitted' && $visit->admission)
                                <a href="{{ route('admissions.show', $visit->admission) }}" class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm">View admission</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium text-sm">No active emergency cases right now</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
