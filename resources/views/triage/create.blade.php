@extends('layouts.app')

@section('title', 'Patient Triage')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-teal-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-teal-100">
                {{ substr($visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="text-right">
            <div class="text-xs font-semibold text-slate-400 mb-1">Queue number</div>
            <div class="text-3xl font-bold text-teal-600">#{{ $visit->queue_number }}</div>
        </div>
    </div>

    <form action="{{ route('triage.store', $visit) }}" method="POST" class="space-y-8">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Vitals Column -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Vitals
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Temp (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="36.5">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Systolic BP</label>
                            <input type="number" name="systolic_bp" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="120">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Diastolic BP</label>
                            <input type="number" name="diastolic_bp" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="80">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Pulse (bpm)</label>
                            <input type="number" name="pulse_rate" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="72">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Resp. Rate</label>
                            <input type="number" name="respiratory_rate" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="16">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">SpO2 (%)</label>
                            <input type="number" name="spo2" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="98">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Weight (kg)</label>
                            <input type="number" step="0.01" name="weight" id="weight" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="70.5">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Height (cm)</label>
                            <input type="number" step="0.1" name="height" id="height" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="175">
                        </div>
                        <div class="vital-input-group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">BMI</label>
                            <div id="bmi_display" class="w-full px-5 py-4 bg-slate-100 border-none rounded-2xl text-slate-400 font-bold flex items-center">---</div>
                        </div>
                    </div>
                </div>

                <!-- Assessment -->
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Assessment
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">What's bothering the patient?</label>
                            <textarea name="chief_complaint" rows="2" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="In the patient's own words..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">History of this illness (brief)</label>
                            <textarea name="history_presenting_illness" rows="3" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500 shadow-inner" placeholder="Duration, severity, etc..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Priority Column -->
            <div class="space-y-8">
                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6">How urgent is this?</h3>

                    <div class="space-y-3">
                        <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-red-50 has-[:checked]:border-red-500 group">
                            <input type="radio" name="priority_level" value="emergency" class="sr-only" @if($visit->priority == 'emergency') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-300 mr-3 group-has-[:checked]:border-red-500 group-has-[:checked]:bg-red-500"></span>
                            <span class="text-sm font-semibold text-slate-900">Emergency</span>
                        </label>

                        <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-orange-50 has-[:checked]:border-orange-500 group">
                            <input type="radio" name="priority_level" value="urgent" class="sr-only" @if($visit->priority == 'urgent') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-300 mr-3 group-has-[:checked]:border-orange-500 group-has-[:checked]:bg-orange-500"></span>
                            <span class="text-sm font-semibold text-slate-900">Urgent</span>
                        </label>

                        <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 group">
                            <input type="radio" name="priority_level" value="routine" class="sr-only" @if($visit->priority == 'routine') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-300 mr-3 group-has-[:checked]:border-blue-500 group-has-[:checked]:bg-blue-500"></span>
                            <span class="text-sm font-semibold text-slate-900">Routine</span>
                        </label>
                    </div>
                </div>

                <div class="bg-teal-600 p-8 rounded-[2rem] shadow-lg shadow-teal-100 text-white">
                    <h3 class="text-sm font-semibold text-white/70 mb-4">Before you submit</h3>
                    <p class="text-sm leading-relaxed mb-8">Double-check the readings above are correct — the doctor on duty will be notified right away.</p>
                    <button type="submit" class="w-full py-4 bg-white text-teal-600 rounded-2xl font-semibold text-sm hover:bg-slate-50 transition-all shadow-md">
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
});
</script>
@endsection
