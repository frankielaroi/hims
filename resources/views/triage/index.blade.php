@extends('layouts.app')

@section('title', 'Triage Queue')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Triage queue</h1>
            <p class="text-slate-500 mt-1">Patients waiting to be assessed, in order of priority.</p>
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
                        <th class="px-8 py-4">Department</th>
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
                            <div class="text-xs text-slate-400 mt-1">{{ $visit->visit_number }}</div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500 font-medium">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-medium bg-slate-100 text-slate-600 rounded-lg">
                                {{ $visit->department->name }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('triage.create', $visit) }}" class="bg-teal-600 text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-teal-700 transition-all shadow-sm">
                                Start triage
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium text-sm">No one's waiting right now</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($visits->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $visits->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
