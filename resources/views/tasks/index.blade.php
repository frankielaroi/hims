@extends('layouts.app')

@section('title', 'Clinical Tasks')

@section('content')
@php
    $tabs = [
        'mine' => 'Assigned to me',
        'overdue' => 'Overdue',
        'all' => 'Everything open',
    ];

    $tone = [
        'critical' => ['bg-red-50', 'text-red-700', 'border-red-100'],
        'urgent' => ['bg-amber-50', 'text-amber-700', 'border-amber-100'],
        'routine' => ['bg-slate-50', 'text-slate-600', 'border-slate-200'],
    ];
@endphp

<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Clinical tasks</h1>
            <p class="text-sm text-slate-500 mt-0.5">Results nobody has read, critical findings nobody has acknowledged, and follow-ups that were promised.</p>
        </div>
        @if($counts['overdue'] > 0)
            <div class="flex items-center gap-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 px-3.5 py-2 rounded-lg shrink-0">
                <i data-lucide="alarm-clock" class="w-4 h-4"></i>
                <span>{{ $counts['overdue'] }} overdue</span>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach($tabs as $key => $title)
            <a href="{{ route('tasks.index', ['filter' => $key]) }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors border
                      {{ $filter === $key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                {{ $title }}
                <span class="ml-1 text-xs {{ $filter === $key ? 'text-white/70' : 'text-slate-400' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($tasks as $task)
            @php [$bg, $fg, $border] = $tone[$task->priority] ?? $tone['routine']; @endphp
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full {{ $bg }} {{ $fg }} border {{ $border }}">
                                {{ $task->priority }}
                            </span>
                            <h2 class="text-sm font-bold text-slate-900">{{ $task->title }}</h2>
                            @if($task->isOverdue())
                                <span class="text-[10px] font-bold text-red-600 uppercase tracking-wider">Overdue</span>
                            @endif
                        </div>

                        @if($task->description)
                            <p class="text-sm text-slate-600 mt-1">{{ $task->description }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-500">
                            @if($task->patient)
                                <a href="{{ route('patients.timeline', $task->patient) }}" class="font-medium text-brand-600 hover:text-brand-800">
                                    {{ $task->patient->full_name }}
                                </a>
                            @endif
                            @if($task->visit)
                                <span>Visit {{ $task->visit->visit_number }}</span>
                            @endif
                            @if($task->due_at)
                                <span>Due {{ $task->due_at->format('d M Y') }}</span>
                            @endif
                            <span>{{ $task->assignedTo->name ?? 'Unassigned' }}</span>
                        </div>
                    </div>

                    <form action="{{ route('tasks.complete', $task) }}" method="POST" class="flex items-center gap-2 shrink-0">
                        @csrf
                        <input type="text" name="completion_note" placeholder="Note (optional)"
                               class="w-44 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-brand-500 focus:outline-none">
                        <button type="submit" class="inline-flex items-center bg-brand-600 text-white px-4 py-1.5 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors">
                            Done
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-14 text-center">
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 mb-3 mx-auto">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-medium text-slate-700">Nothing outstanding here</p>
            </div>
        @endforelse
    </div>

    @if($tasks->hasPages())
        <div>{{ $tasks->links() }}</div>
    @endif
</div>
@endsection
