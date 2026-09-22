@extends('layouts.app')

@section('title', 'Patient Triage')

@section('content')
@php
    $input = 'w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none';
    $label = 'block text-xs font-medium text-slate-600 mb-1';
@endphp

<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-brand-50 flex items-center justify-center text-brand-700 text-lg font-semibold shrink-0">
                {{ strtoupper(substr($visit->patient->full_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-xl font-semibold text-slate-900 tracking-tight">{{ $visit->patient->full_name }}</h1>
                <div class="flex items-center gap-2.5 mt-0.5">
                    <span class="text-sm text-slate-500">{{ $visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm text-slate-500">{{ ucfirst($visit->patient->sex) }} &bull; {{ $visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="text-right shrink-0">
            <div class="text-xs text-slate-500">Queue number</div>
            <div class="text-2xl font-semibold text-brand-700 leading-tight">#{{ $visit->queue_number }}</div>
        </div>
    </div>

    <form action="{{ route('triage.store', $visit) }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Vitals and assessment -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-100">
                <section class="p-5">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center">
                        <i data-lucide="activity" class="w-4 h-4 mr-2 text-brand-600"></i>
                        Vitals
                    </h2>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3">
                        <div>
                            <label for="temperature" class="{{ $label }}">Temp (°C)</label>
                            <input type="number" step="0.1" id="temperature" name="temperature" value="{{ old('temperature') }}" class="{{ $input }}" placeholder="36.5">
                        </div>

                        <div class="col-span-2 md:col-span-1">
                            <label for="systolic_bp" class="{{ $label }}">Blood pressure</label>
                            <div class="flex items-center gap-1.5">
                                <input type="number" id="systolic_bp" name="systolic_bp" value="{{ old('systolic_bp') }}" class="{{ $input }}" placeholder="120" aria-label="Systolic BP">
                                <span class="text-slate-400">/</span>
                                <input type="number" id="diastolic_bp" name="diastolic_bp" value="{{ old('diastolic_bp') }}" class="{{ $input }}" placeholder="80" aria-label="Diastolic BP">
                            </div>
                        </div>

                        <div>
                            <label for="pulse_rate" class="{{ $label }}">Pulse (bpm)</label>
                            <input type="number" id="pulse_rate" name="pulse_rate" value="{{ old('pulse_rate') }}" class="{{ $input }}" placeholder="72">
                        </div>

                        <div>
                            <label for="respiratory_rate" class="{{ $label }}">Resp. rate</label>
                            <input type="number" id="respiratory_rate" name="respiratory_rate" value="{{ old('respiratory_rate') }}" class="{{ $input }}" placeholder="16">
                        </div>

                        <div>
                            <label for="spo2" class="{{ $label }}">SpO2 (%)</label>
                            <input type="number" id="spo2" name="spo2" value="{{ old('spo2') }}" class="{{ $input }}" placeholder="98">
                        </div>

                        <div>
                            <label for="weight" class="{{ $label }}">Weight (kg)</label>
                            <input type="number" step="0.01" id="weight" name="weight" value="{{ old('weight') }}" class="{{ $input }}" placeholder="70.5">
                        </div>

                        <div>
                            <label for="height" class="{{ $label }}">Height (cm)</label>
                            <input type="number" step="0.1" id="height" name="height" value="{{ old('height') }}" class="{{ $input }}" placeholder="175">
                        </div>

                        <div>
                            <span class="{{ $label }}">BMI</span>
                            <div id="bmi_display" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-semibold text-slate-400">---</div>
                        </div>
                    </div>
                </section>

                <section class="p-5">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center">
                        <i data-lucide="clipboard-list" class="w-4 h-4 mr-2 text-brand-600"></i>
                        Assessment
                    </h2>

                    <div class="space-y-3">
                        <div>
                            <label for="chief_complaint" class="{{ $label }}">What's bothering the patient?</label>
                            <textarea id="chief_complaint" name="chief_complaint" rows="2" required class="{{ $input }}" placeholder="In the patient's own words...">{{ old('chief_complaint') }}</textarea>
                        </div>
                        <div>
                            <label for="history_presenting_illness" class="{{ $label }}">History of this illness (brief)</label>
                            <textarea id="history_presenting_illness" name="history_presenting_illness" rows="3" class="{{ $input }}" placeholder="Duration, severity, etc...">{{ old('history_presenting_illness') }}</textarea>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Priority and submit -->
            <div class="space-y-5">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900 mb-3">How urgent is this?</h2>

                    @php $selectedPriority = old('priority_level', $visit->priority); @endphp
                    <div class="space-y-2">
                        <label class="block cursor-pointer">
                            <input type="radio" name="priority_level" value="emergency" required class="peer sr-only" {{ $selectedPriority == 'emergency' ? 'checked' : '' }}>
                            <span class="flex items-center gap-3 px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 transition hover:bg-slate-50 peer-checked:bg-red-50 peer-checked:border-red-400 peer-checked:text-red-800 peer-focus-visible:ring-2 peer-focus-visible:ring-red-400/40">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                                Emergency
                            </span>
                        </label>

                        <label class="block cursor-pointer">
                            <input type="radio" name="priority_level" value="urgent" class="peer sr-only" {{ $selectedPriority == 'urgent' ? 'checked' : '' }}>
                            <span class="flex items-center gap-3 px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 transition hover:bg-slate-50 peer-checked:bg-amber-50 peer-checked:border-amber-400 peer-checked:text-amber-800 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400/40">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                Urgent
                            </span>
                        </label>

                        <label class="block cursor-pointer">
                            <input type="radio" name="priority_level" value="routine" class="peer sr-only" {{ $selectedPriority == 'routine' ? 'checked' : '' }}>
                            <span class="flex items-center gap-3 px-4 py-2.5 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 transition hover:bg-slate-50 peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">
                                <span class="w-2.5 h-2.5 rounded-full bg-brand-500"></span>
                                Routine
                            </span>
                        </label>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Double-check the readings are correct &mdash; the doctor on duty will be notified right away.</p>
                    <button type="submit" class="w-full py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
                        Complete triage
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const weightInput = document.getElementById('weight');
    const heightInput = document.getElementById('height');
    const bmiDisplay = document.getElementById('bmi_display');

    function calculateBMI() {
        const weight = parseFloat(weightInput.value);
        const height = parseFloat(heightInput.value);

        if (weight > 0 && height > 0) {
            const heightInMeters = height / 100;
            const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(1);
            bmiDisplay.innerText = bmi;
            bmiDisplay.classList.remove('text-slate-400');
            bmiDisplay.classList.add('text-slate-900');
        } else {
            bmiDisplay.innerText = '---';
            bmiDisplay.classList.remove('text-slate-900');
            bmiDisplay.classList.add('text-slate-400');
        }
    }

    weightInput.addEventListener('input', calculateBMI);
    heightInput.addEventListener('input', calculateBMI);
    calculateBMI();
});
</script>
@endsection