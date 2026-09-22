@extends('layouts.app')

@section('title', 'Register Attendance')

@section('content')
@php
    $input = 'w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none';
    $label = 'block text-xs font-medium text-slate-600 mb-1';
    $pill  = 'block text-center px-3 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-600 cursor-pointer transition hover:bg-slate-50 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40';
    $selectedPayment = old('payment_method', $patient->payment_method);
@endphp

<div class="max-w-2xl mx-auto">
    <div class="flex items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Start a new visit</h1>
            <p class="text-sm text-slate-500 mt-0.5">For <span class="font-medium text-slate-800">{{ $patient->full_name }}</span></p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="text-sm font-medium text-slate-500 hover:text-slate-900 flex items-center shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
            Cancel
        </a>
    </div>

    <form action="{{ route('visits.store', $patient) }}" method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm">
        @csrf

        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
                <div>
                    <label for="department_id" class="{{ $label }}">Department</label>
                    <select id="department_id" name="department_id" required class="{{ $input }}">
                        <option value="">Select a department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type" class="{{ $label }}">Visit type</label>
                    <select id="type" name="type" required class="{{ $input }}">
                        @foreach(['OPD General' => 'OPD General', 'Emergency' => 'Emergency', 'ANC' => 'ANC (Antenatal)', 'Specialist Clinic' => 'Specialist Clinic', 'Review' => 'Review / Follow-up'] as $value => $text)
                            <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <span class="{{ $label }}">Priority</span>
                <div class="grid grid-cols-3 gap-2">
                    @php $selectedPriority = old('priority', 'routine'); @endphp
                    <label>
                        <input type="radio" name="priority" value="routine" required class="peer sr-only" {{ $selectedPriority === 'routine' ? 'checked' : '' }}>
                        <span class="{{ $pill }} peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700">Routine</span>
                    </label>
                    <label>
                        <input type="radio" name="priority" value="urgent" class="peer sr-only" {{ $selectedPriority === 'urgent' ? 'checked' : '' }}>
                        <span class="{{ $pill }} peer-checked:bg-amber-50 peer-checked:border-amber-400 peer-checked:text-amber-800">Urgent</span>
                    </label>
                    <label>
                        <input type="radio" name="priority" value="emergency" class="peer sr-only" {{ $selectedPriority === 'emergency' ? 'checked' : '' }}>
                        <span class="{{ $pill }} peer-checked:bg-red-50 peer-checked:border-red-400 peer-checked:text-red-800">Emergency</span>
                    </label>
                </div>
            </div>

            <div>
                <span class="{{ $label }}">Payment method for this visit</span>
                <div class="grid grid-cols-3 gap-2">
                    @foreach(['cash' => 'Cash', 'nhis' => 'NHIS', 'corporate' => 'Corporate'] as $value => $text)
                        <label>
                            <input type="radio" name="payment_method" value="{{ $value }}" required class="peer sr-only" {{ $selectedPayment === $value ? 'checked' : '' }}>
                            <span class="{{ $pill }} peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700">{{ $text }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div id="nhis_verification" class="{{ $selectedPayment === 'nhis' ? '' : 'hidden' }} bg-brand-50 p-4 rounded-lg border border-brand-100">
                <p class="text-sm font-semibold text-brand-900 mb-1">NHIS eligibility check</p>
                @if($patient->nhis_number)
                    <p class="text-sm text-brand-900">Card on file: <span class="font-semibold">{{ $patient->nhis_number }}</span>
                        @if($patient->nhis_expiry)
                            &bull; Expires {{ $patient->nhis_expiry->format('d M, Y') }}
                            @if($patient->nhis_expiry->isPast())
                                <span class="text-red-600 font-semibold">(expired)</span>
                            @endif
                        @endif
                    </p>
                @else
                    <p class="text-sm text-red-700 font-semibold">No NHIS number on file for this patient &mdash; confirm their card before proceeding.</p>
                @endif
                <label class="flex items-center gap-2.5 mt-3 cursor-pointer">
                    <input type="checkbox" name="nhis_verified" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-brand-900">I have checked the card/biometric and confirmed eligibility.</span>
                </label>
                <p class="text-xs text-brand-700 mt-2">Leaving this unchecked won't block registration, but the visit's NHIS claim can't be generated until it's verified.</p>
            </div>

            <div>
                <label for="reason_for_visit" class="{{ $label }}">Reason for the visit</label>
                <textarea id="reason_for_visit" name="reason_for_visit" rows="2" class="{{ $input }}" placeholder="e.g. Complaining of headache and fever...">{{ old('reason_for_visit') }}</textarea>
            </div>
        </div>

        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 rounded-b-xl">
            <button type="submit" class="w-full py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
                Start visit &amp; send to triage
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const nhisPanel = document.getElementById('nhis_verification');

    function toggleNhis() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        nhisPanel.classList.toggle('hidden', !(selected && selected.value === 'nhis'));
    }

    paymentRadios.forEach(r => r.addEventListener('change', toggleNhis));
    toggleNhis();
});
</script>
@endsection