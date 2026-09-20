@extends('layouts.app')

@section('title', 'Edit Patient')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Edit Patient Details</h1>
            <p class="text-slate-500 mt-1">Update the record for <span class="font-bold text-slate-800">{{ $patient->full_name }}</span></p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="text-slate-500 hover:text-slate-700 font-bold text-sm flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Cancel
        </a>
    </div>

    <form action="{{ route('patients.update', $patient) }}" method="POST" class="space-y-8">
        @csrf
        @method('PATCH')

        <!-- Identity Section -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center">
                <span class="w-8 h-8 bg-teal-50 text-teal-600 rounded-lg flex items-center justify-center mr-3 text-sm text-center">1</span>
                Patient details
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Full name</label>
                    <input type="text" name="full_name" value="{{ $patient->full_name }}" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ $patient->date_of_birth ? $patient->date_of_birth->format('Y-m-d') : '' }}" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Estimated age</label>
                    <input type="number" name="estimated_age" value="{{ $patient->estimated_age }}" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Sex</label>
                    <select name="sex" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                        <option value="male" @if($patient->sex == 'male') selected @endif>Male</option>
                        <option value="female" @if($patient->sex == 'female') selected @endif>Female</option>
                        <option value="other" @if($patient->sex == 'other') selected @endif>Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Phone number</label>
                    <input type="text" name="phone_number" value="{{ $patient->phone_number }}" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Address / landmark</label>
                    <textarea name="address" rows="2" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">{{ $patient->address }}</textarea>
                </div>
            </div>
        </div>

        <!-- Insurance & Payment -->
        <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-800 mb-8 flex items-center">
                <span class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-3 text-sm text-center">2</span>
                Insurance & payment
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Payment method</label>
                    <select name="payment_method" id="payment_method" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                        <option value="cash" @if($patient->payment_method == 'cash') selected @endif>Cash / self-pay</option>
                        <option value="nhis" @if($patient->payment_method == 'nhis') selected @endif>NHIS</option>
                        <option value="corporate" @if($patient->payment_method == 'corporate') selected @endif>Corporate</option>
                    </select>
                </div>

                <div id="nhis_fields" class="{{ $patient->payment_method == 'nhis' ? '' : 'hidden' }} md:contents">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">NHIS number</label>
                        <input type="text" name="nhis_number" value="{{ $patient->nhis_number }}" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">NHIS expiry date</label>
                        <input type="date" name="nhis_expiry" value="{{ $patient->nhis_expiry ? $patient->nhis_expiry->format('Y-m-d') : '' }}" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-4 pb-12">
            <button type="submit" class="bg-teal-600 text-white px-12 py-4 rounded-2xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all">
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
