@extends('layouts.app')

@section('title', 'Triage Queue')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Triage queue</h1>
            <p class="text-slate-400 mt-1">Patients waiting to be assessed, in order of priority.</p>
        </div>
        <div class="flex items-center space-x-2 text-sm text-slate-400 bg-slate-900 border border-slate-800 px-4 py-2 rounded-lg">
            <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
            <span>{{ $visits->total() }} waiting</span>
        </div>
    </div>

    <div class="bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-500 border-b border-slate-800">
                        <th class="px-6 py-3">Queue</th>
                        <th class="px-6 py-3">Patient</th>
                        <th class="px-6 py-3">Department</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($visits as $visit)
                    <tr class="hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="text-lg font-bold text-slate-100">#{{ $visit->queue_number }}</div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($visit->priority == 'emergency') bg-rose-950 text-rose-400 border border-rose-800
                                    @elseif($visit->priority == 'urgent') bg-amber-950 text-amber-400 border border-amber-800
                                    @else bg-blue-950 text-blue-400 border border-blue-800 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">{{ $visit->visit_number }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-slate-100">{{ $visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-400 font-medium">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-xs font-medium bg-slate-800 text-slate-300 rounded-lg">
                                {{ $visit->department->name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('triage.create', $visit) }}" class="bg-amber-600 text-white px-5 py-2 rounded-lg font-semibold text-sm hover:bg-amber-500 transition-all shadow-sm">
                                Start triage
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center text-slate-500 mb-4">
                                    <i data-lucide="clipboard-list" class="w-8 h-8"></i>
                                </div>
                                <p class="text-slate-400 font-medium text-sm">No one's waiting right now</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($visits->hasPages())
        <div class="px-6 py-4 bg-slate-950/50 border-t border-slate-800">
            {{ $visits->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
