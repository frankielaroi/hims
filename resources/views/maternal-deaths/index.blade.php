@extends('layouts.app')

@section('title', 'Maternal Death Surveillance')

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:border-rose-500 focus:outline-none';
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Maternal death surveillance</h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Every maternal death is notified within {{ \App\Models\DeathRecord::NOTIFICATION_DEADLINE_HOURS }} hours and reviewed afterwards.
        </p>
    </div>

    {{-- Deaths the record suggests may be maternal, awaiting a clinician's judgement --}}
    @if($candidates->isNotEmpty())
        <div class="{{ $card }} border-amber-200">
            <h2 class="text-sm font-bold text-amber-700 uppercase tracking-tight mb-1">Needs classifying</h2>
            <p class="text-xs text-slate-500 mb-4">
                These women died while pregnant or within {{ \App\Models\DeathRecord::MATERNAL_WINDOW_DAYS }} days of a pregnancy.
                Whether the death was related to the pregnancy is a clinical judgement, so nothing has been decided automatically.
            </p>

            @foreach($candidates as $record)
                <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                    <div class="font-semibold text-slate-900">{{ $record->patient->full_name }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        Died {{ $record->died_at->format('d M Y H:i') }} &bull; {{ $record->immediate_cause }}
                    </div>

                    <form action="{{ route('maternal-deaths.classify', $record) }}" method="POST" class="flex flex-wrap items-end gap-3 mt-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Timing</label>
                            <select name="maternal_death_timing" class="{{ $input }}">
                                @foreach(\App\Models\DeathRecord::MATERNAL_TIMINGS as $timing)
                                    <option value="{{ $timing }}">{{ ucfirst(str_replace('_', ' ', $timing)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" name="is_maternal_death" value="1" class="px-5 py-2.5 bg-rose-600 text-white rounded-xl text-sm font-bold hover:bg-rose-700">
                            Maternal death
                        </button>
                        <button type="submit" name="is_maternal_death" value="0" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-200">
                            Not maternal
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Awaiting notification</h2>

        @forelse($awaitingNotification as $record)
            <div class="p-4 rounded-2xl border {{ $record->notificationIsOverdue() ? 'border-red-200 bg-red-50' : 'border-amber-100 bg-amber-50' }} mb-3">
                <div class="font-semibold text-slate-900">{{ $record->patient->full_name }}</div>
                <div class="text-xs text-slate-600 mt-0.5">
                    Died {{ $record->died_at->format('d M Y H:i') }}
                    &bull; {{ ucfirst(str_replace('_', ' ', $record->maternal_death_timing ?? '')) }}
                </div>
                <ul class="mt-2 space-y-1 text-sm text-slate-700 list-disc list-inside">
                    @foreach($record->outstandingActions() as $action)<li>{{ $action }}</li>@endforeach
                </ul>
                <form action="{{ route('maternal-deaths.notify', $record) }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 bg-rose-600 text-white rounded-xl text-sm font-bold hover:bg-rose-700">
                        Record notification
                    </button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-500">Nothing awaiting notification.</p>
        @endforelse
    </div>

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Awaiting review</h2>

        @forelse($awaitingReview as $record)
            <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                <div class="font-semibold text-slate-900">{{ $record->patient->full_name }}</div>
                <div class="text-xs text-slate-500 mt-0.5">
                    Died {{ $record->died_at->format('d M Y') }}
                    &bull; notified {{ $record->notified_at->format('d M Y H:i') }} by {{ $record->notifiedBy->name ?? 'unknown' }}
                </div>
                <form action="{{ route('maternal-deaths.review', $record) }}" method="POST" class="mt-3">
                    @csrf
                    <textarea name="review_findings" rows="3" required minlength="20" class="{{ $input }}"
                              placeholder="What the review found, and what will change as a result."></textarea>
                    <button type="submit" class="mt-3 px-5 py-2.5 bg-rose-600 text-white rounded-xl text-sm font-bold hover:bg-rose-700">
                        Record review
                    </button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-500">Nothing awaiting review.</p>
        @endforelse
    </div>
</div>
@endsection
