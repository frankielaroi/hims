@extends('layouts.app')

@section('title', 'Patient Triage')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-5">
            <div class="h-14 w-14 rounded-xl bg-amber-600 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-amber-950/50">
                {{ substr($visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-100 tracking-tight">{{ $visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-400">{{ $visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-400">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="text-right">
            <div class="text-xs font-semibold text-slate-500 mb-1">Queue number</div>
            <div class="text-2xl font-bold text-amber-400">#{{ $visit->queue_number }}</div>
        </div>
    </div>

    <form action="{{ route('triage.store', $visit) }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Vitals Column -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-400 mb-6 flex items-center">
                        <i data-lucide="activity" class="w-4 h-4 mr-2 text-amber-400"></i>
                        Vitals
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Temp (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="36.5">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Systolic BP</label>
                            <input type="number" name="systolic_bp" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="120">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Diastolic BP</label>
                            <input type="number" name="diastolic_bp" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="80">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Pulse (bpm)</label>
                            <input type="number" name="pulse_rate" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="72">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Resp. rate</label>
                            <input type="number" name="respiratory_rate" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="16">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">SpO2 (%)</label>
                            <input type="number" name="spo2" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="98">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Weight (kg)</label>
                            <input type="number" step="0.01" name="weight" id="weight" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="70.5">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">Height (cm)</label>
                            <input type="number" step="0.1" name="height" id="height" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="175">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 ml-1">BMI</label>
                            <div id="bmi_display" class="w-full px-4 py-3 bg-slate-800 border border-slate-700 rounded-lg text-slate-500 font-bold flex items-center">---</div>
                        </div>
                    </div>
                </div>

                <!-- Assessment -->
                <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-400 mb-6 flex items-center">
                        <i data-lucide="clipboard-list" class="w-4 h-4 mr-2 text-amber-400"></i>
                        Assessment
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">What's bothering the patient?</label>
                            <textarea name="chief_complaint" rows="2" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="In the patient's own words..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">History of this illness (brief)</label>
                            <textarea name="history_presenting_illness" rows="3" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="Duration, severity, etc..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Priority Column -->
            <div class="space-y-6">
                <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-400 mb-5">How urgent is this?</h3>

                    <div class="space-y-3">
                        <label class="relative flex items-center p-4 bg-slate-950 rounded-lg border-2 border-transparent cursor-pointer hover:bg-slate-800 transition-all has-[:checked]:bg-rose-950/40 has-[:checked]:border-rose-700 group">
                            <input type="radio" name="priority_level" value="emergency" class="sr-only" @if($visit->priority == 'emergency') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-600 mr-3 group-has-[:checked]:border-rose-500 group-has-[:checked]:bg-rose-500"></span>
                            <span class="text-sm font-semibold text-slate-100">Emergency</span>
                        </label>

                        <label class="relative flex items-center p-4 bg-slate-950 rounded-lg border-2 border-transparent cursor-pointer hover:bg-slate-800 transition-all has-[:checked]:bg-amber-950/40 has-[:checked]:border-amber-700 group">
                            <input type="radio" name="priority_level" value="urgent" class="sr-only" @if($visit->priority == 'urgent') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-600 mr-3 group-has-[:checked]:border-amber-500 group-has-[:checked]:bg-amber-500"></span>
                            <span class="text-sm font-semibold text-slate-100">Urgent</span>
                        </label>

                        <label class="relative flex items-center p-4 bg-slate-950 rounded-lg border-2 border-transparent cursor-pointer hover:bg-slate-800 transition-all has-[:checked]:bg-blue-950/40 has-[:checked]:border-blue-700 group">
                            <input type="radio" name="priority_level" value="routine" class="sr-only" @if($visit->priority == 'routine') checked @endif>
                            <span class="w-4 h-4 rounded-full border-2 border-slate-600 mr-3 group-has-[:checked]:border-blue-500 group-has-[:checked]:bg-blue-500"></span>
                            <span class="text-sm font-semibold text-slate-100">Routine</span>
                        </label>
                    </div>
                </div>

                <div class="bg-emerald-600 p-6 rounded-lg shadow-lg shadow-emerald-950/50 text-white">
                    <h3 class="text-sm font-semibold text-white/70 mb-3">Before you submit</h3>
                    <p class="text-sm leading-relaxed mb-6">Double-check the readings above are correct &mdash; the doctor on duty will be notified right away.</p>
                    <button type="submit" class="w-full py-3 bg-white text-emerald-700 rounded-lg font-semibold text-sm hover:bg-slate-50 transition-all shadow-md">
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
            bmiDisplay.classList.remove('text-slate-500');
            bmiDisplay.classList.add('text-slate-100');
        } else {
            bmiDisplay.innerText = '---';
            bmiDisplay.classList.remove('text-slate-100');
            bmiDisplay.classList.add('text-slate-500');
        }
    }

    weightInput.addEventListener('input', calculateBMI);
    heightInput.addEventListener('input', calculateBMI);
});
</script>
@endsection
