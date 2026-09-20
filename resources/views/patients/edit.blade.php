@extends('layouts.app')

@section('title', 'Edit Patient')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Edit Patient Details</h1>
            <p class="text-slate-400 mt-1">Update the record for <span class="font-bold text-slate-200">{{ $patient->full_name }}</span></p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="text-slate-400 hover:text-slate-200 font-semibold text-sm flex items-center">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
            Cancel
        </a>
    </div>

    <form action="{{ route('patients.update', $patient) }}" method="POST" class="space-y-6">
        @csrf
        @method('PATCH')

        <!-- Identity Section -->
        <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
            <h3 class="text-lg font-bold text-slate-100 mb-6 flex items-center">
                <span class="w-7 h-7 bg-emerald-950 border border-emerald-800 text-emerald-400 rounded-lg flex items-center justify-center mr-3 text-xs">1</span>
                Patient details
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Full name</label>
                    <input type="text" name="full_name" value="{{ $patient->full_name }}" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ $patient->date_of_birth ? $patient->date_of_birth->format('Y-m-d') : '' }}" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Estimated age</label>
                    <input type="number" name="estimated_age" value="{{ $patient->estimated_age }}" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Sex</label>
                    <select name="sex" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="male" @if($patient->sex == 'male') selected @endif>Male</option>
                        <option value="female" @if($patient->sex == 'female') selected @endif>Female</option>
                        <option value="other" @if($patient->sex == 'other') selected @endif>Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Phone number</label>
                    <input type="text" name="phone_number" value="{{ $patient->phone_number }}" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Address / landmark</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ $patient->address }}</textarea>
                </div>
            </div>
        </div>

        <!-- Insurance & Payment -->
        <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
            <h3 class="text-lg font-bold text-slate-100 mb-6 flex items-center">
                <span class="w-7 h-7 bg-blue-950 border border-blue-800 text-blue-400 rounded-lg flex items-center justify-center mr-3 text-xs">2</span>
                Insurance & payment
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Payment method</label>
                    <select name="payment_method" id="payment_method" required class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="cash" @if($patient->payment_method == 'cash') selected @endif>Cash / self-pay</option>
                        <option value="nhis" @if($patient->payment_method == 'nhis') selected @endif>NHIS</option>
                        <option value="corporate" @if($patient->payment_method == 'corporate') selected @endif>Corporate</option>
                    </select>
                </div>

                <div id="nhis_fields" class="{{ $patient->payment_method == 'nhis' ? '' : 'hidden' }} md:contents">
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">NHIS number</label>
                        <input type="text" name="nhis_number" value="{{ $patient->nhis_number }}" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">NHIS expiry date</label>
                        <input type="date" name="nhis_expiry" value="{{ $patient->nhis_expiry ? $patient->nhis_expiry->format('Y-m-d') : '' }}" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-4 pb-8">
            <button type="submit" class="bg-emerald-600 text-white px-10 py-3 rounded-lg font-semibold hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all">
                Save changes
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
});
</script>
@endsection
