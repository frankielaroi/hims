@extends('layouts.app')

@section('title', 'Wards & Beds')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Wards &amp; Beds</h1>
            <p class="text-slate-500 mt-1">Who's waiting for a bed, and what's free right now.</p>
        </div>
        <div class="flex items-center space-x-4 text-xs font-semibold">
            <span class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-green-500"></span><span class="text-slate-600">Available</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-red-500"></span><span class="text-slate-600">Occupied</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span><span class="text-slate-600">Cleaning</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-slate-400"></span><span class="text-slate-600">Maintenance</span></span>
        </div>
    </div>

    @if($awaitingAdmission->isNotEmpty())
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-900">Waiting for a bed</h3>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($awaitingAdmission as $visit)
                <div class="px-8 py-5 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $visit->patient->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $visit->patient->hospital_id }} &bull; {{ $visit->department->name }} &bull; {{ $visit->created_at->diffForHumans() }}</p>
                    </div>
                    <form action="{{ route('admissions.assign-bed', $visit) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <select name="bed_id" required class="px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select a bed</option>
                            @foreach($wards as $ward)
                                @foreach($ward->beds->where('status', 'available') as $bed)
                                    <option value="{{ $bed->id }}">{{ $ward->name }} &mdash; Bed {{ $bed->bed_number }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:bg-indigo-700 transition-all shadow-sm">
                            Admit
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($wards as $ward)
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-slate-900">{{ $ward->name }}</h3>
                <p class="text-xs text-slate-500">{{ $ward->category }} &bull; Floor {{ $ward->floor }}</p>
            </div>
            <div class="grid grid-cols-3 gap-2">
                @foreach($ward->beds as $bed)
                    @php
                        $color = match($bed->status) {
                            'available' => 'bg-green-500',
                            'occupied' => 'bg-red-500',
                            'cleaning' => 'bg-amber-500',
                            default => 'bg-slate-400',
                        };
                    @endphp
                    @if($bed->status === 'occupied' && $bed->currentAdmission)
                        <a href="{{ route('admissions.show', $bed->currentAdmission) }}" class="{{ $color }} rounded-xl aspect-square flex items-center justify-center text-white text-xs font-bold hover:opacity-80 transition" title="{{ $bed->currentAdmission->visit->patient->full_name }}">
                            {{ $bed->bed_number }}
                        </a>
                    @elseif($bed->status === 'cleaning')
                        <form action="{{ route('beds.mark-available', $bed) }}" method="POST">
                            @csrf
                            <button type="submit" class="{{ $color }} rounded-xl aspect-square flex items-center justify-center text-white text-xs font-bold hover:opacity-80 transition w-full" title="Mark clean & available">
                                {{ $bed->bed_number }}
                            </button>
                        </form>
                    @else
                        <div class="{{ $color }} rounded-xl aspect-square flex items-center justify-center text-white text-xs font-bold" title="{{ ucfirst($bed->status) }}">
                            {{ $bed->bed_number }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
