@extends('layouts.app')

@section('title', 'Register Attendance')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Start a new visit</h1>
            <p class="text-slate-500 mt-1">For <span class="font-semibold text-slate-800">{{ $patient->full_name }}</span></p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="text-slate-500 hover:text-slate-700 font-semibold text-sm flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Cancel
        </a>
    </div>

    <form action="{{ route('visits.store', $patient) }}" method="POST" class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200 space-y-8">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Department</label>
                <select name="department_id" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    <option value="">Select a department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Visit type</label>
                <select name="type" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    <option value="OPD General">OPD General</option>
                    <option value="Emergency">Emergency</option>
                    <option value="ANC">ANC (Antenatal)</option>
                    <option value="Specialist Clinic">Specialist Clinic</option>
                    <option value="Review">Review / Follow-up</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Priority</label>
                <select name="priority" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Payment method for this visit</label>
                <select name="payment_method" id="payment_method" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    <option value="cash" @if($patient->payment_method == 'cash') selected @endif>Cash / self-pay</option>
                    <option value="nhis" @if($patient->payment_method == 'nhis') selected @endif>NHIS</option>
                    <option value="corporate" @if($patient->payment_method == 'corporate') selected @endif>Corporate</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Reason for the visit</label>
                <textarea name="reason_for_visit" rows="3" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. Complaining of headache and fever..."></textarea>
            </div>
        </div>

        <div id="nhis_verification" class="{{ $patient->payment_method == 'nhis' ? '' : 'hidden' }} bg-blue-50 p-6 rounded-2xl border border-blue-100">
            <p class="text-sm font-semibold text-blue-900 mb-1">NHIS eligibility check</p>
            @if($patient->nhis_number)
                <p class="text-sm text-blue-800">Card on file: <span class="font-semibold">{{ $patient->nhis_number }}</span>
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
            <label class="flex items-center space-x-2 mt-3 cursor-pointer">
                <input type="checkbox" name="nhis_verified" value="1" class="h-5 w-5 rounded text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-blue-900">I have checked the card/biometric and confirmed eligibility.</span>
            </label>
            <p class="text-xs text-blue-700 mt-2">Leaving this unchecked won't block registration, but the visit's NHIS claim can't be generated until it's verified.</p>
        </div>

        <button type="submit" class="w-full py-5 bg-teal-600 text-white rounded-[2rem] font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all">
            Start visit & send to triage
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentMethod = document.getElementById('payment_method');
    const nhisPanel = document.getElementById('nhis_verification');

    paymentMethod.addEventListener('change', function () {
        nhisPanel.classList.toggle('hidden', this.value !== 'nhis');
    });
});
</script>
@endsection
