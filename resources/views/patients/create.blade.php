@extends('layouts.app')

@section('title', 'New Registration')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Register a new patient</h1>
            <p class="text-slate-400 mt-1">Only use this if you've already searched and couldn't find them.</p>
        </div>
        <a href="{{ route('patients.index') }}" class="text-slate-400 hover:text-slate-200 font-semibold text-sm flex items-center">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
            Back to patient search
        </a>
    </div>

    <form action="{{ route('patients.store') }}" method="POST" class="space-y-6">
        @csrf

        @if(session('duplicate_found'))
        <div class="bg-amber-950/40 border-2 border-amber-800 p-8 rounded-lg">
            <div class="flex items-start">
                <div class="bg-amber-900 p-3 rounded-lg mr-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6 text-amber-400"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-amber-300">This might already be a patient</h3>
                    <p class="text-sm text-amber-400 mt-1 mb-4">We found someone with a similar name and age already in the system. Take a look before continuing.</p>

                    <div class="bg-slate-950/50 p-4 rounded-lg border border-amber-800 flex items-center justify-between mb-6">
                        <div>
                            <p class="text-sm font-semibold text-slate-100">{{ session('duplicate_found')->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ session('duplicate_found')->hospital_id }} &bull; {{ session('duplicate_found')->age }} years</p>
                        </div>
                        <a href="{{ route('patients.show', session('duplicate_found')) }}" target="_blank" class="text-xs font-semibold text-blue-400 hover:underline">View this record &rarr;</a>
                    </div>

                    <div class="flex items-center space-x-4">
                        <input type="checkbox" name="confirm_duplicate" id="confirm_duplicate" class="h-5 w-5 text-emerald-600 bg-slate-800 border-slate-600 rounded focus:ring-emerald-500">
                        <label for="confirm_duplicate" class="text-sm font-medium text-amber-200">I've checked — this is a different person.</label>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Identity Section -->
        <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
            <h3 class="text-lg font-bold text-slate-100 mb-6 flex items-center">
                <span class="w-7 h-7 bg-emerald-950 border border-emerald-800 text-emerald-400 rounded-lg flex items-center justify-center mr-3 text-xs">1</span>
                Who is the patient?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Full name</label>
                    <input type="text" name="full_name" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. Kofi Mensah">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Date of birth</label>
                    <input type="date" name="date_of_birth" id="dob" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Estimated age (if date of birth is unknown)</label>
                    <input type="number" name="estimated_age" id="est_age" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. 65">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Sex</label>
                    <select name="sex" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Phone number</label>
                    <input type="text" name="phone_number" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. 0244 123 456">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Address / landmark</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. Near the old lorry station, Kumasi"></textarea>
                </div>
            </div>
        </div>

        <!-- Insurance & Payment -->
        <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
            <h3 class="text-lg font-bold text-slate-100 mb-6 flex items-center">
                <span class="w-7 h-7 bg-blue-950 border border-blue-800 text-blue-400 rounded-lg flex items-center justify-center mr-3 text-xs">2</span>
                How will they pay?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Payment method</label>
                    <select name="payment_method" id="payment_method" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="cash">Cash / self-pay</option>
                        <option value="nhis">NHIS</option>
                        <option value="corporate">Corporate</option>
                    </select>
                </div>

                <div id="nhis_fields" class="hidden md:contents">
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">NHIS number</label>
                        <input type="text" name="nhis_number" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">NHIS expiry date</label>
                        <input type="date" name="nhis_expiry" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- Next of Kin -->
        <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
            <h3 class="text-lg font-bold text-slate-100 mb-6 flex items-center">
                <span class="w-7 h-7 bg-violet-950 border border-violet-800 text-violet-400 rounded-lg flex items-center justify-center mr-3 text-xs">3</span>
                Who should we contact in an emergency?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Full name</label>
                    <input type="text" name="next_of_kin_name" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Contact number</label>
                    <input type="text" name="next_of_kin_contact" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-4 pb-8">
            <button type="reset" class="px-6 py-3 text-slate-400 font-semibold hover:text-slate-200 transition-all text-sm">
                Clear form
            </button>
            <button type="submit" class="bg-emerald-600 text-white px-10 py-3 rounded-lg font-semibold hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all">
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
