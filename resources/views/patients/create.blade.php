@extends('layouts.app')

@section('title', 'New Registration')

@section('content')
@php
    $input = 'w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none';
    $label = 'block text-xs font-medium text-slate-600 mb-1';
    $pill  = 'block text-center px-3 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-600 cursor-pointer transition hover:bg-slate-50 peer-checked:bg-brand-50 peer-checked:border-brand-500 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40';
@endphp

<div class="max-w-3xl mx-auto">
    <div class="flex items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-inherit tracking-tight">Register a new patient</h1>
            <p class="text-sm text-inherit mt-0.5">Only use this if you've already searched and couldn't find them.</p>
        </div>
        <a href="{{ route('patients.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 flex items-center shrink-0">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
            Back to search
        </a>
    </div>

    <form action="{{ route('patients.store') }}" method="POST" class="space-y-4">
        @csrf

        @if(session('duplicate_found'))
        <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-amber-900">This might already be a patient</h3>
                    <p class="text-sm text-amber-800 mt-0.5">Someone with a similar name and age is already in the system. Take a look before continuing.</p>

                    <div class="bg-white p-3 rounded-lg border border-amber-200 flex items-center justify-between mt-3">
                        <div>
                            <p class="text-sm font-medium text-inherit">{{ session('duplicate_found')->full_name }}</p>
                            <p class="text-xs text-inherit">{{ session('duplicate_found')->hospital_id }} &bull; {{ session('duplicate_found')->age }} years</p>
                        </div>
                        <a href="{{ route('patients.show', session('duplicate_found')) }}" target="_blank" class="text-xs font-medium text-brand-600 hover:underline">View this record &rarr;</a>
                    </div>

                    <label for="confirm_duplicate" class="flex items-center gap-2.5 mt-3 cursor-pointer">
                        <input type="checkbox" name="confirm_duplicate" id="confirm_duplicate" class="h-4 w-4 text-brand-600 border-inherit rounded focus:ring-brand-500">
                        <span class="text-sm font-medium text-inherit">I've checked — this is a different person.</span>
                    </label>
                </div>
            </div>
        </div>
        @endif

        <div class="bg-inherit rounded-xl border border-inherit shadow-sm divide-y divide-inherit">

            <!-- Patient -->
            <section class="p-5">
                <h2 class="text-sm font-semibold text-inherit mb-3">Patient</h2>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-x-4 gap-y-3">
                    <div class="md:col-span-3">
                        <label for="full_name" class="{{ $label }}">Full name</label>
                        <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required autofocus class="{{ $input }}" placeholder="e.g. Kofi Mensah">
                    </div>

                    <div class="md:col-span-3">
                        <span class="{{ $label }}">Sex</span>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $text)
                                <label>
                                    <input type="radio" name="sex" value="{{ $value }}" required class="peer sr-only" {{ old('sex') === $value ? 'checked' : '' }}>
                                    <span class="{{ $pill }}">{{ $text }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label for="dob" class="{{ $label }}">Date of birth</label>
                        <input type="date" id="dob" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}" class="{{ $input }}">
                    </div>

                    <div class="md:col-span-2">
                        <label for="est_age" class="{{ $label }}">Age, if date of birth unknown</label>
                        <input type="number" id="est_age" name="estimated_age" value="{{ old('estimated_age') }}" min="0" max="130" class="{{ $input }}" placeholder="e.g. 65">
                    </div>

                    <div class="md:col-span-2">
                        <label for="phone_number" class="{{ $label }}">Phone number</label>
                        <input type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" class="{{ $input }}" placeholder="0244 123 456">
                    </div>

                    <div class="md:col-span-6">
                        <label for="address" class="{{ $label }}">Address or landmark</label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}" class="{{ $input }}" placeholder="e.g. Near the old lorry station, Kumasi">
                    </div>
                </div>
            </section>

            <!-- Payment -->
            <section class="p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Payment</h2>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-x-4 gap-y-3">
                    <div class="md:col-span-6">
                        <span class="{{ $label }}">How will they pay?</span>
                        <div class="grid grid-cols-3 gap-2 md:max-w-md">
                            @foreach(['cash' => 'Cash', 'nhis' => 'NHIS', 'corporate' => 'Corporate'] as $value => $text)
                                <label>
                                    <input type="radio" name="payment_method" value="{{ $value }}" required class="peer sr-only" {{ old('payment_method', 'cash') === $value ? 'checked' : '' }}>
                                    <span class="{{ $pill }}">{{ $text }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div id="nhis_fields" class="hidden md:col-span-6 grid grid-cols-1 md:grid-cols-6 gap-x-4 gap-y-3">
                        <div class="md:col-span-3">
                            <label for="nhis_number" class="{{ $label }}">NHIS number</label>
                            <input type="text" id="nhis_number" name="nhis_number" value="{{ old('nhis_number') }}" class="{{ $input }}">
                        </div>
                        <div class="md:col-span-3">
                            <label for="nhis_expiry" class="{{ $label }}">NHIS expiry date</label>
                            <input type="date" id="nhis_expiry" name="nhis_expiry" value="{{ old('nhis_expiry') }}" class="{{ $input }}">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Next of kin -->
            <section class="p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3">Emergency contact</h2>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-x-4 gap-y-3">
                    <div class="md:col-span-3">
                        <label for="next_of_kin_name" class="{{ $label }}">Full name</label>
                        <input type="text" id="next_of_kin_name" name="next_of_kin_name" value="{{ old('next_of_kin_name') }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-3">
                        <label for="next_of_kin_contact" class="{{ $label }}">Contact number</label>
                        <input type="tel" id="next_of_kin_contact" name="next_of_kin_contact" value="{{ old('next_of_kin_contact') }}" class="{{ $input }}">
                    </div>
                </div>
            </section>

            <!-- Actions -->
            <div class="px-5 py-4 flex items-center justify-end gap-2 bg-slate-50 rounded-b-xl">
                <button type="reset" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
                    Clear form
                </button>
                <button type="submit" class="bg-brand-600 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
                    Complete registration
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nhisFields = document.getElementById('nhis_fields');
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

    function toggleNhis() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        nhisFields.classList.toggle('hidden', !(selected && selected.value === 'nhis'));
    }

    paymentRadios.forEach(r => r.addEventListener('change', toggleNhis));
    document.querySelector('form').addEventListener('reset', () => setTimeout(toggleNhis, 0));
    toggleNhis();

    // Date of birth and estimated age are mutually exclusive
    const dobInput = document.getElementById('dob');
    const estAgeInput = document.getElementById('est_age');

    dobInput.addEventListener('change', function () {
        if (this.value) estAgeInput.value = '';
    });

    estAgeInput.addEventListener('input', function () {
        if (this.value) dobInput.value = '';
    });
});
</script>
@endsection