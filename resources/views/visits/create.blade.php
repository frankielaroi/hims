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
                <select name="payment_method" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
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

        <div class="bg-teal-50 p-6 rounded-2xl border border-teal-100 flex items-start">
            <svg class="w-5 h-5 text-teal-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p class="text-sm text-teal-800 leading-relaxed">This will add the patient straight to the triage queue. If they have NHIS, double-check the card has been verified.</p>
        </div>

        <button type="submit" class="w-full py-5 bg-teal-600 text-white rounded-[2rem] font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all">
            Start visit & send to triage
        </button>
    </form>
</div>
@endsection
