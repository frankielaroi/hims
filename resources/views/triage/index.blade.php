@extends('layouts.app')

@section('title', 'Triage Queue')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Triage queue</h1>
            <p class="text-sm text-slate-500 mt-0.5">Patients waiting to be assessed, in order of priority.</p>
        </div>
        <div class="flex items-center gap-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 shadow-sm px-3.5 py-2 rounded-lg shrink-0">
            <span class="w-2 h-2 bg-brand-500 rounded-full animate-pulse"></span>
            <span>{{ $visits->total() }} waiting</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                        <th class="px-5 py-2.5">Queue</th>
                        <th class="px-5 py-2.5">Patient</th>
                        <th class="px-5 py-2.5">Department</th>
                        <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($visits as $visit)
                    <tr class="hover:bg-brand-50/40 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="text-lg font-semibold text-slate-900">#{{ $visit->queue_number }}</div>
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full
                                    @if($visit->priority == 'emergency') bg-red-50 text-red-700 border border-red-100
                                    @elseif($visit->priority == 'urgent') bg-amber-50 text-amber-700 border border-amber-100
                                    @else bg-brand-50 text-brand-700 border border-brand-100 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $visit->visit_number }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="text-sm font-medium text-slate-900">{{ $visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($visit->patient->sex) }} &bull; {{ $visit->patient->age }} years</div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded-md">
                                {{ $visit->department->name }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('triage.create', $visit) }}" class="inline-flex items-center bg-brand-600 text-white px-4 py-1.5 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors">
                                Start triage
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-14 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 mb-3">
                                    <i data-lucide="clipboard-check" class="w-6 h-6"></i>
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