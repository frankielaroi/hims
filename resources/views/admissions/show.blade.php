@extends('layouts.app')

@section('title', 'Admission')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-100">
                {{ substr($admission->visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $admission->visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $admission->visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">Bed {{ $admission->bed->bed_number }}, {{ $admission->bed->ward->name }}</span>
                </div>
            </div>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $admission->status === 'admitted' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' }}">
            {{ ucfirst($admission->status) }}
        </span>
    </div>

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200 space-y-4">
        <div class="flex justify-between text-sm">
            <span class="text-slate-500">Admitting diagnosis</span>
            <span class="font-semibold text-slate-900">{{ $admission->admitting_diagnosis ?? 'Not recorded' }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-slate-500">Admitted by</span>
            <span class="font-semibold text-slate-900">{{ $admission->admittedBy->name }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-slate-500">Admission date</span>
            <span class="font-semibold text-slate-900">{{ $admission->admission_date->format('d M, Y H:i') }}</span>
        </div>
        <div class="flex justify-between text-sm pt-3 border-t border-slate-100">
            <span class="text-slate-500">Length of stay</span>
            <span class="font-semibold text-slate-900">{{ $admission->lengthOfStayDays() }} day(s)</span>
        </div>
    </div>

    @if($admission->transfers->isNotEmpty())
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
        <h3 class="text-sm font-semibold text-slate-500 mb-4">Bed transfer history</h3>
        <div class="space-y-3">
            @foreach($admission->transfers as $transfer)
                <div class="text-sm text-slate-700">
                    {{ $transfer->fromBed->bed_number }} &rarr; {{ $transfer->toBed->bed_number }}
                    <span class="text-xs text-slate-400">({{ $transfer->created_at->format('d M, Y H:i') }}{{ $transfer->reason ? ' — '.$transfer->reason : '' }})</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($admission->status === 'admitted')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-500 mb-4">Transfer to another bed</h3>
            <form action="{{ route('admissions.transfer', $admission) }}" method="POST" class="space-y-3">
                @csrf
                <select name="bed_id" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select a bed</option>
                    @foreach($wards as $ward)
                        @foreach($ward->beds->where('status', 'available') as $bed)
                            <option value="{{ $bed->id }}">{{ $ward->name }} &mdash; Bed {{ $bed->bed_number }}</option>
                        @endforeach
                    @endforeach
                </select>
                <input type="text" name="reason" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500" placeholder="Reason (optional)">
                <button type="submit" class="w-full py-3 bg-indigo-600 text-white rounded-xl font-semibold text-sm hover:bg-indigo-700 transition-all">
                    Transfer
                </button>
            </form>
        </div>

        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-500 mb-4">Discharge</h3>
            <form action="{{ route('admissions.discharge', $admission) }}" method="POST" class="space-y-3">
                @csrf
                <textarea name="discharge_notes" rows="3" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-teal-500" placeholder="Discharge notes (optional)"></textarea>
                <button type="submit" class="w-full py-3 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 transition-all">
                    Discharge patient
                </button>
            </form>
        </div>
    </div>
    @else
    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
        <p class="text-sm font-semibold text-slate-700">Discharged {{ $admission->discharge_date->format('d M, Y H:i') }}</p>
        @if($admission->discharge_notes)
            <p class="text-sm text-slate-500 mt-1">{{ $admission->discharge_notes }}</p>
        @endif
    </div>
    @endif
</div>
@endsection
