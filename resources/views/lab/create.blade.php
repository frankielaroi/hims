@extends('layouts.app')

@section('title', 'New Lab Order')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">New Lab Order</h1>
        <p class="text-slate-500 mt-1">Manual entry for walk-in patients or diagnostic requests.</p>
    </div>

    <form action="{{ route('lab.store') }}" method="POST" class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200 space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Patient</label>
            <select name="patient_id" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500">
                <option value="">Select Patient</option>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}">{{ $patient->full_name }} ({{ $patient->hospital_id }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Investigations</label>
            <div class="grid grid-cols-2 gap-4">
                @foreach($tests as $test)
                <label class="flex items-center space-x-3 p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-slate-100 transition-colors border border-transparent has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
                    <input type="checkbox" name="test_ids[]" value="{{ $test->id }}" class="w-5 h-5 text-purple-600 border-slate-300 rounded focus:ring-purple-500">
                    <div>
                        <div class="text-sm font-bold text-slate-900">{{ $test->name }}</div>
                        <div class="text-xs text-slate-500">{{ $test->category }}</div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Priority</label>
                <select name="priority" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Clinical Indication</label>
            <textarea name="clinical_indication" rows="3" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="Optional clinical details..."></textarea>
        </div>

        <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold text-lg hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
            Create Lab Order
        </button>
    </form>
</div>
@endsection
