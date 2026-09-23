@extends('layouts.app')

@section('title', 'Theatre')

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:border-indigo-500 focus:outline-none';
    $label = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Theatre</h1>
        <p class="text-sm text-slate-500 mt-0.5">{{ $active->count() }} in theatre &bull; {{ $completed->count() }} completed today</p>
    </div>

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">In theatre</h2>

        @forelse($active as $operation)
            <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold text-slate-900">{{ $operation->procedure }}</span>
                    <span class="text-xs text-slate-500">
                        {{ $operation->patient->full_name }} &bull; {{ ucfirst($operation->urgency) }}
                        @if($operation->started_at) &bull; started {{ $operation->started_at->format('H:i') }} @endif
                    </span>
                </div>

                <form action="{{ route('theatre.complete', $operation) }}" method="POST" class="mt-4 pt-4 border-t border-slate-100">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="{{ $label }}">Ended at</label>
                            <input type="datetime-local" name="ended_at" required value="{{ now()->format('Y-m-d\TH:i') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Surgeon</label>
                            <select name="surgeon_id" required class="{{ $input }}">
                                <option value="">Select…</option>
                                @foreach(\App\Models\User::orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Anaesthetist</label>
                            <select name="anaesthetist_id" class="{{ $input }}">
                                <option value="">—</option>
                                @foreach(\App\Models\User::orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Blood loss (ml)</label>
                            <input type="number" name="estimated_blood_loss_ml" min="0" class="{{ $input }}">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="{{ $label }}">Findings</label>
                        <textarea name="findings" rows="2" class="{{ $input }}"></textarea>
                    </div>

                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-4 mb-2">Complications</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @foreach(\App\Models\TheatreOperation::COMPLICATIONS as $complication)
                            <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200">
                                <input type="checkbox" name="complications[]" value="{{ $complication }}" class="rounded border-slate-300 text-amber-600">
                                <span class="text-xs text-slate-700">{{ ucfirst(str_replace('_', ' ', $complication)) }}</span>
                            </label>
                        @endforeach
                    </div>

                    {{-- A retained swab is a never-event, so this is asked outright. --}}
                    <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <span class="{{ $label }}">Swab and instrument count</span>
                        <div class="flex gap-4 mt-1">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="swab_count_correct" value="1" required class="text-emerald-600">
                                <span class="text-sm font-medium text-slate-700">Correct</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="swab_count_correct" value="0" class="text-red-600">
                                <span class="text-sm font-medium text-slate-700">Incorrect</span>
                            </label>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 mt-3">
                        <input type="checkbox" name="specimen_sent" value="1" class="rounded border-slate-300 text-indigo-600">
                        <span class="text-sm text-slate-700">Specimen sent to laboratory</span>
                    </label>

                    <button type="submit" class="mt-4 px-8 py-3 bg-indigo-600 text-white rounded-[2rem] text-sm font-black hover:bg-indigo-700 transition-all">
                        Close operation record
                    </button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-500">Nothing in theatre.</p>
        @endforelse
    </div>

    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Completed today</h2>

        @forelse($completed as $operation)
            <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold text-slate-900">{{ $operation->procedure }}</span>
                    <span class="text-xs text-slate-500">
                        {{ $operation->patient->full_name }}
                        @if($operation->durationMinutes()) &bull; {{ $operation->durationMinutes() }} min @endif
                        &bull; {{ $operation->surgeon->name ?? 'no surgeon named' }}
                    </span>
                </div>
                @php $alerts = $operation->alerts(); @endphp
                @if($alerts !== [])
                    <ul class="mt-2 space-y-1 text-sm text-amber-700 list-disc list-inside">
                        @foreach($alerts as $alert)<li>{{ $alert }}</li>@endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">No operations completed today.</p>
        @endforelse
    </div>
</div>
@endsection
