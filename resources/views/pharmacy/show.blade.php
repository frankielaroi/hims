@extends('layouts.app')

@section('title', 'Dispense Prescription')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-teal-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-teal-100">
                {{ substr($prescription->visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $prescription->visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $prescription->visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">{{ $prescription->visit->patient->sex }} &bull; {{ $prescription->visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <span class="px-3 py-1 text-xs font-semibold rounded-full
            @if($prescription->status === 'partially_dispensed') bg-orange-100 text-orange-700
            @elseif($prescription->status === 'dispensed') bg-green-100 text-green-700
            @else bg-blue-100 text-blue-700 @endif">
            {{ ucfirst(str_replace('_', ' ', $prescription->status)) }}
        </span>
    </div>

    @if($prescription->allergyChecks->isNotEmpty())
    <div class="bg-red-50 p-6 rounded-2xl border border-red-100">
        <p class="text-sm font-semibold text-red-800 mb-2">Allergy override on this prescription</p>
        @foreach($prescription->allergyChecks as $check)
            <p class="text-sm text-red-700">{{ $check->drug_prescribed }}: {{ $check->doctor_justification }}</p>
        @endforeach
    </div>
    @endif

    <div class="space-y-6">
        @foreach($prescription->items as $item)
        @php $remaining = $item->quantity_to_dispense - $item->quantity_dispensed; @endphp
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $item->drug->name }} <span class="text-sm font-medium text-slate-400">{{ $item->drug->strength }}</span></h3>
                    <p class="text-sm text-slate-500 mt-1">{{ $item->dose }} &bull; {{ $item->frequency }} &bull; {{ $item->duration_days }} days</p>
                </div>
                @if($item->nhis_covered_snapshot)
                    <span class="px-2 py-1 text-xs font-semibold bg-blue-50 text-blue-700 rounded-md">NHIS covered</span>
                @else
                    <span class="px-2 py-1 text-xs font-semibold bg-slate-100 text-slate-600 rounded-md">Cash</span>
                @endif
            </div>

            <div class="flex items-center justify-between text-sm mb-4 px-4 py-3 bg-slate-50 rounded-xl">
                <span class="text-slate-500">Prescribed: <span class="font-semibold text-slate-900">{{ $item->quantity_to_dispense }}</span></span>
                <span class="text-slate-500">Dispensed: <span class="font-semibold text-slate-900">{{ $item->quantity_dispensed }}</span></span>
                <span class="text-slate-500">In stock: <span class="font-semibold {{ $item->drug->stock_level < $remaining ? 'text-red-600' : 'text-slate-900' }}">{{ $item->drug->stock_level }}</span></span>
            </div>

            @if($item->drug->batches->contains(fn ($b) => $b->quantity_remaining > 0 && $b->isExpiringSoon()))
                <p class="text-xs text-orange-600 mb-4">A batch of this drug expires within 60 days &mdash; it will be dispensed first.</p>
            @endif

            @if($remaining > 0)
                <form action="{{ route('pharmacy.items.dispense', [$prescription, $item]) }}" method="POST" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Quantity to dispense</label>
                        <input type="number" name="quantity" min="1" max="{{ $remaining }}" value="{{ $remaining }}" required
                               class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                    </div>
                    <button type="submit" class="py-3 px-8 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 transition-all">
                        Dispense
                    </button>
                </form>
                @error('quantity')
                    <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror
            @else
                <div class="flex items-center text-sm font-semibold text-green-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Fully dispensed
                </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
