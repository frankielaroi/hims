@extends('layouts.app')

@section('title', 'Process Lab Order')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-purple-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-purple-100">
                {{ substr($labOrder->visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $labOrder->visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $labOrder->visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">{{ $labOrder->visit->patient->sex }} &bull; {{ $labOrder->visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="text-right">
            <span class="px-3 py-1 text-xs font-semibold rounded-full
                @if($labOrder->priority == 'stat') bg-red-100 text-red-700
                @elseif($labOrder->priority == 'urgent') bg-orange-100 text-orange-700
                @else bg-blue-100 text-blue-700 @endif">
                {{ ucfirst($labOrder->priority) }}
            </span>
            <p class="text-xs text-slate-400 mt-2">Ordered by {{ $labOrder->orderedBy->name }} &bull; {{ $labOrder->created_at->diffForHumans() }}</p>
        </div>
    </div>

    @if($labOrder->clinical_indication)
    <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
        <p class="text-xs font-semibold text-blue-600 mb-1">Clinical indication</p>
        <p class="text-sm text-blue-800">{{ $labOrder->clinical_indication }}</p>
    </div>
    @endif

    <div class="space-y-6">
        @foreach($labOrder->items as $item)
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $item->labTest->name }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Specimen: {{ $item->specimen_type }} &bull; Reference: {{ $item->reference_range ?? 'Not specified' }}</p>
                </div>
                <span class="px-3 py-1 text-xs font-semibold rounded-full
                    @if($item->specimen_status == 'collected') bg-green-50 text-green-700
                    @elseif($item->specimen_status == 'rejected') bg-red-50 text-red-700
                    @else bg-slate-100 text-slate-600 @endif">
                    Specimen {{ str_replace('_', ' ', $item->specimen_status) }}
                </span>
            </div>

            @if($item->specimen_status === 'pending_collection')
                <form action="{{ route('lab.items.collect', [$labOrder, $item]) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full py-3 bg-slate-900 text-white rounded-xl font-semibold text-sm hover:bg-slate-800 transition-all">
                        Mark specimen collected
                    </button>
                </form>
            @elseif(is_null($item->performed_at))
                <form action="{{ route('lab.items.result', [$labOrder, $item]) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Result</label>
                        <textarea name="result_value" rows="2" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="e.g. Negative, or specific findings...">{{ old('result_value') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Flag</label>
                        <div class="flex gap-3">
                            <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-green-50 has-[:checked]:border-green-500">
                                <input type="radio" name="result_flag" value="normal" class="sr-only" checked>
                                <span class="text-sm font-semibold text-slate-900">Normal</span>
                            </label>
                            <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-orange-50 has-[:checked]:border-orange-500">
                                <input type="radio" name="result_flag" value="abnormal" class="sr-only">
                                <span class="text-sm font-semibold text-slate-900">Abnormal</span>
                            </label>
                            <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-red-50 has-[:checked]:border-red-500">
                                <input type="radio" name="result_flag" value="critical" class="sr-only">
                                <span class="text-sm font-semibold text-slate-900">Critical</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 bg-purple-600 text-white rounded-xl font-semibold text-sm hover:bg-purple-700 transition-all">
                        Save result
                    </button>
                </form>
            @else
                <div class="p-5 rounded-2xl border
                    @if($item->result_flag === 'critical') bg-red-50 border-red-100
                    @elseif($item->result_flag === 'abnormal') bg-orange-50 border-orange-100
                    @else bg-slate-50 border-slate-100 @endif">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase
                            @if($item->result_flag === 'critical') text-red-700
                            @elseif($item->result_flag === 'abnormal') text-orange-700
                            @else text-slate-500 @endif">{{ ucfirst($item->result_flag) }}</span>
                        <span class="text-xs text-slate-400">{{ $item->performedBy->name }} &bull; {{ $item->performed_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-900">{{ $item->result_value }}</p>
                </div>
            @endif
        </div>
        @endforeach
    </div>

    @if($labOrder->status !== 'verified')
        <form action="{{ route('lab.verify', $labOrder) }}" method="POST">
            @csrf
            <button type="submit"
                @if(!$labOrder->allResultsRecorded()) disabled @endif
                class="w-full py-5 rounded-[2rem] font-semibold transition-all
                    @if($labOrder->allResultsRecorded()) bg-teal-600 text-white hover:bg-teal-700 shadow-lg shadow-teal-100 @else bg-slate-200 text-slate-400 cursor-not-allowed @endif">
                @if($labOrder->allResultsRecorded())
                    Verify results & send back to doctor
                @else
                    Record all results before verifying
                @endif
            </button>
        </form>
    @endif
</div>
@endsection
