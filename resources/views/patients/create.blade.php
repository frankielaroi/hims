@extends('layouts.app')

@section('title', 'New Registration')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Register a new patient</h1>
            <p class="text-slate-500 mt-1">Only use this if you've already searched and couldn't find them.</p>
        </div>
        <a href="{{ route('patients.index') }}" class="text-slate-500 hover:text-slate-700 font-semibold text-sm flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to patient search
        </a>
    </div>

    <form action="{{ route('patients.store') }}" method="POST" class="space-y-8">
        @csrf

        @if(session('duplicate_found'))
        <div class="bg-amber-50 border-2 border-amber-200 p-8 rounded-[2rem] shadow-sm">
            <div class="flex items-start">
                <div class="bg-amber-100 p-3 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-amber-800">This might already be a patient</h3>
                    <p class="text-sm text-amber-700 mt-1 mb-4">We found someone with a similar name and age already in the system. Take a look before continuing.</p>

                    <div class="bg-white/50 p-4 rounded-xl border border-amber-100 flex items-center justify-between mb-6">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ session('duplicate_found')->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ session('duplicate_found')->hospital_id }} &bull; {{ session('duplicate_found')->age }} years</p>
                        </div>
                        <a href="{{ route('patients.show', session('duplicate_found')) }}" target="_blank" class="text-xs font-semibold text-blue-600 hover:underline">View this record &rarr;</a>
                    </div>

                    <div class="flex items-center space-x-4">
                        <input type="checkbox" name="confirm_duplicate" id="confirm_duplicate" class="h-5 w-5 text-teal-600 border-slate-300 rounded-lg focus:ring-teal-500">
                        <label for="confirm_duplicate" class="text-sm font-medium text-amber-900">I've checked — this is a different person.</label>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Identity Section -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center">
                <span class="w-8 h-8 bg-teal-50 text-teal-600 rounded-lg flex items-center justify-center mr-3 text-sm">1</span>
                Who is the patient?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Full name</label>
                    <input type="text" name="full_name" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. Kofi Mensah">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Date of birth</label>
                    <input type="date" name="date_of_birth" id="dob" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Estimated age (if date of birth is unknown)</label>
                    <input type="number" name="estimated_age" id="est_age" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. 65">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Sex</label>
                    <select name="sex" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Phone number</label>
                    <input type="text" name="phone_number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. 0244 123 456">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Address / landmark</label>
                    <textarea name="address" rows="2" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. Near the old lorry station, Kumasi"></textarea>
                </div>
            </div>
        </div>

        <!-- Insurance & Payment -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center">
                <span class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-3 text-sm">2</span>
                How will they pay?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Payment method</label>
                    <select name="payment_method" id="payment_method" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                        <option value="cash">Cash / self-pay</option>
                        <option value="nhis">NHIS</option>
                        <option value="corporate">Corporate</option>
                    </select>
                </div>

                <div id="nhis_fields" class="hidden md:contents">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">NHIS number</label>
                        <input type="text" name="nhis_number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">NHIS expiry date</label>
                        <input type="date" name="nhis_expiry" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
            </div>
        </div>

        <!-- Next of Kin -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center">
                <span class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center mr-3 text-sm">3</span>
                Who should we contact in an emergency?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Full name</label>
                    <input type="text" name="next_of_kin_name" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Contact number</label>
                    <input type="text" name="next_of_kin_contact" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-4 pb-12">
            <button type="reset" class="px-8 py-4 text-slate-500 font-semibold hover:text-slate-700 transition-all">
                Clear form
            </button>
            <button type="submit" class="bg-teal-600 text-white px-12 py-4 rounded-2xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all">
                Complete registration
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentMethod = document.getElementById('payment_method');
    const nhisFields = document.getElementById('nhis_fields');

    paymentMethod.addEventListener('change', function() {
        if (this.value === 'nhis') {
            nhisFields.classList.remove('hidden');
        } else {
            nhisFields.classList.add('hidden');
        }
    });

    // Auto-clear estimated age if DOB is entered
    const dobInput = document.getElementById('dob');
    const estAgeInput = document.getElementById('est_age');

    dobInput.addEventListener('change', function() {
        if (this.value) estAgeInput.value = '';
    });

    estAgeInput.addEventListener('input', function() {
        if (this.value) dobInput.value = '';
    });
});
</script>
@endsection
