@extends('layouts.app')

@section('title', 'Process Lab Order')

@section('content')
<div class="max-w-5xl mx-auto space-y-8 pb-20">
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
        <p class="text-xs font-semibold text-blue-600 mb-1 uppercase tracking-wider">Clinical indication</p>
        <p class="text-sm text-blue-800">{{ $labOrder->clinical_indication }}</p>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Sample Collection Sidebar -->
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Specimens</h3>

                @php
                    $pendingItemsBySpecimen = $labOrder->items->where('specimen_status', 'pending_collection')->groupBy('specimen_type');
                @endphp

                @if($pendingItemsBySpecimen->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($pendingItemsBySpecimen as $type => $items)
                            <form action="{{ route('lab.collect', $labOrder) }}" method="POST" class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                @csrf
                                <input type="hidden" name="specimen_type" value="{{ $type }}">
                                <div class="text-sm font-bold text-slate-900 mb-2">{{ $type }} Tube</div>
                                <div class="space-y-2 mb-4">
                                    @foreach($items as $item)
                                        <label class="flex items-center space-x-2">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" checked class="w-4 h-4 text-purple-600 border-slate-300 rounded">
                                            <span class="text-xs text-slate-600">{{ $item->labTest->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-all">
                                    Collect & Print Barcode
                                </button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-400 text-center py-4">All specimens collected.</p>
                @endif

                <hr class="my-6 border-slate-100">

                <h4 class="text-xs font-bold text-slate-400 uppercase mb-4">Active Samples</h4>
                <div class="space-y-4">
                    @foreach($labOrder->samples as $sample)
                        <div class="p-4 rounded-2xl border @if($sample->status == 'rejected') border-red-200 bg-red-50 @else border-slate-100 bg-white @endif">
                            <div class="flex justify-between items-start mb-2">
                                <div class="text-xs font-black text-slate-900 tracking-widest">{{ $sample->barcode_number }}</div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full
                                    @if($sample->status == 'received') bg-green-100 text-green-700
                                    @elseif($sample->status == 'rejected') bg-red-100 text-red-700
                                    @else bg-blue-100 text-blue-700 @endif">
                                    {{ ucfirst($sample->status) }}
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500 mb-3">{{ $sample->specimen_type }} &bull; {{ $sample->items->count() }} tests</div>

                            <div class="flex gap-2">
                                @if($sample->status == 'collected')
                                    <form action="{{ route('lab.samples.receive', $sample) }}" method="POST" class="flex-1">
                                        @csrf
                                        <button class="w-full py-1.5 bg-green-600 text-white rounded-lg text-[10px] font-bold hover:bg-green-700 transition-all">Receive</button>
                                    </form>
                                @endif

                                @if(in_array($sample->status, ['collected', 'received']))
                                    <button onclick="document.getElementById('reject-modal-{{ $sample->id }}').classList.remove('hidden')" class="flex-1 py-1.5 bg-white border border-red-200 text-red-600 rounded-lg text-[10px] font-bold hover:bg-red-50 transition-all">Reject</button>
                                @endif
                            </div>

                            <!-- Reject Modal (Simplified for demo) -->
                            <div id="reject-modal-{{ $sample->id }}" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50">
                                <div class="bg-white p-6 rounded-[2rem] max-w-sm w-full mx-4 shadow-2xl">
                                    <h3 class="text-lg font-bold text-slate-900 mb-2">Reject Sample</h3>
                                    <p class="text-sm text-slate-500 mb-4">Please provide a reason for rejecting sample {{ $sample->barcode_number }}.</p>
                                    <form action="{{ route('lab.samples.reject', $sample) }}" method="POST">
                                        @csrf
                                        <textarea name="rejection_reason" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm mb-4" placeholder="e.g. Hemolyzed, insufficient volume..."></textarea>
                                        <div class="flex gap-3">
                                            <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="flex-1 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm">Cancel</button>
                                            <button type="submit" class="flex-1 py-3 bg-red-600 text-white rounded-xl font-bold text-sm shadow-lg shadow-red-100">Reject Sample</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Result Entry Main Area -->
        <div class="md:col-span-2 space-y-6">
            @foreach($labOrder->items as $item)
                <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $item->labTest->name }}</h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Specimen: {{ $item->specimen_type }}
                                @if($item->labSample)
                                    &bull; <span class="font-bold text-slate-700">{{ $item->labSample->barcode_number }}</span>
                                @endif
                            </p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                            @if($item->authorized_at) bg-green-100 text-green-700
                            @elseif($item->performed_at) bg-orange-100 text-orange-700
                            @elseif($item->specimen_status == 'collected') bg-blue-100 text-blue-700
                            @else bg-slate-100 text-slate-600 @endif">
                            @if($item->authorized_at) Authorized @elseif($item->performed_at) Pending Authorization @else {{ ucfirst(str_replace('_', ' ', $item->specimen_status)) }} @endif
                        </span>
                    </div>

                    @if($item->specimen_status === 'collected' && is_null($item->performed_at))
                        @if($item->labSample && $item->labSample->status == 'received')
                            <form action="{{ route('lab.items.result', [$labOrder, $item]) }}" method="POST" class="space-y-4">
                                @csrf
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">Result Value</label>
                                        <textarea name="result_value" rows="2" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="e.g. Negative, or specific findings...">{{ old('result_value') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">Reagent Batch</label>
                                        <select name="lab_reagent_id" class="w-full px-5 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-purple-500">
                                            <option value="">N/A (No Reagent)</option>
                                            @foreach($reagents as $reagent)
                                                <option value="{{ $reagent->id }}">{{ $reagent->name }} (Batch: {{ $reagent->batch_number }} - {{ $reagent->current_stock }} left)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-2">Reference Range</label>
                                        <input type="text" readonly value="{{ $item->reference_range }}" class="w-full px-5 py-3 bg-slate-100 border-none rounded-xl text-sm text-slate-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Interpretation Flag</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-green-50 has-[:checked]:border-green-500">
                                            <input type="radio" name="result_flag" value="normal" class="sr-only" checked>
                                            <span class="text-xs font-bold text-slate-900">Normal</span>
                                        </label>
                                        <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-orange-50 has-[:checked]:border-orange-500">
                                            <input type="radio" name="result_flag" value="abnormal" class="sr-only">
                                            <span class="text-xs font-bold text-slate-900">Abnormal</span>
                                        </label>
                                        <label class="flex-1 flex items-center justify-center p-3 bg-slate-50 rounded-xl border-2 border-transparent cursor-pointer hover:bg-slate-100 has-[:checked]:bg-red-50 has-[:checked]:border-red-500">
                                            <input type="radio" name="result_flag" value="critical" class="sr-only">
                                            <span class="text-xs font-bold text-slate-900">Critical</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="w-full py-3 bg-purple-600 text-white rounded-xl font-bold text-sm hover:bg-purple-700 transition-all shadow-lg shadow-purple-100">
                                    Save Result for Review
                                </button>
                            </form>
                        @else
                            <div class="flex flex-col items-center justify-center p-8 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-sm font-medium text-slate-500">Waiting for sample to be marked as "Received"</p>
                            </div>
                        @endif
                    @elseif($item->performed_at)
                        <div class="p-6 rounded-2xl border
                            @if($item->result_flag === 'critical') bg-red-50 border-red-100
                            @elseif($item->result_flag === 'abnormal') bg-orange-50 border-orange-100
                            @else bg-slate-50 border-slate-100 @endif">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-xs font-black uppercase tracking-widest
                                    @if($item->result_flag === 'critical') text-red-700
                                    @elseif($item->result_flag === 'abnormal') text-orange-700
                                    @else text-slate-500 @endif">{{ ucfirst($item->result_flag) }}</span>
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">{{ $item->performedBy->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $item->performed_at->format('M d, H:i') }}</div>
                                </div>
                            </div>
                            <p class="text-lg font-bold text-slate-900 mb-3">{{ $item->result_value }}</p>

                            @if($item->reagent)
                                <div class="flex items-center space-x-2 text-[10px] text-slate-400 border-t border-slate-100 pt-3">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.293a2 2 0 01-1.64 0l-.628-.293a6 6 0 00-3.86-.517l-2.387.477a2 2 0 00-1.022.547l-.318.318a2 2 0 000 2.828l.635.635a2 2 0 002.828 0l.318-.318a2 2 0 00.547-1.022l.477-2.387a6 6 0 00-.517-3.86l-.293-.628a2 2 0 010-1.64l.293-.628a6 6 0 00.517-3.86l-.477-2.387a2 2 0 00-.547-1.022l-.318-.318a2 2 0 000-2.828l.635-.635a2 2 0 002.828 0l.318.318a2 2 0 00.547 1.022l.477 2.387a6 6 0 00.517 3.86l.293.628a2 2 0 010 1.64l-.293.628a6 6 0 00-.517 3.86l.477 2.387z"></path></svg>
                                    <span>Reagent: {{ $item->reagent->name }} (Batch: {{ $item->reagent->batch_number }})</span>
                                </div>
                            @endif

                            @if($item->authorized_at)
                                <div class="flex items-center space-x-2 text-[10px] text-green-600 mt-2 bg-green-50/50 p-2 rounded-lg">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    <span>Authorized by {{ $item->authorizedBy->name }} on {{ $item->authorized_at->format('M d, H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center p-8 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                            <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.293a2 2 0 01-1.64 0l-.628-.293a6 6 0 00-3.86-.517l-2.387.477a2 2 0 00-1.022.547l-.318.318a2 2 0 000 2.828l.635.635a2 2 0 002.828 0l.318-.318a2 2 0 00.547-1.022l.477-2.387a6 6 0 00-.517-3.86l-.293-.628a2 2 0 010-1.64l.293-.628a6 6 0 00.517-3.86l-.477-2.387a2 2 0 00-.547-1.022l-.318-.318a2 2 0 000-2.828l.635-.635a2 2 0 002.828 0l.318.318a2 2 0 00.547 1.022l.477 2.387a6 6 0 00.517 3.86l.293.628a2 2 0 010 1.64l-.293.628a6 6 0 00-.517 3.86l.477 2.387z"></path></svg>
                            <p class="text-sm font-medium text-slate-500">Waiting for specimen collection</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Action Bar -->
    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 max-w-lg w-full px-4 flex gap-4">
        @php
            $readyForAuth = $labOrder->items->whereNotNull('performed_at')->whereNull('authorized_at')->isNotEmpty();
            $allAuth = $labOrder->allResultsAuthorized();
        @endphp

        @if($readyForAuth)
            <form action="{{ route('lab.authorize', $labOrder) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full py-4 bg-purple-600 text-white rounded-2xl font-bold shadow-xl shadow-purple-100 hover:bg-purple-700 transition-all flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Authorize Results</span>
                </button>
            </form>
        @endif

        @if($allAuth && $labOrder->status !== 'verified')
            <form action="{{ route('lab.verify', $labOrder) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-bold shadow-xl shadow-teal-100 hover:bg-teal-700 transition-all flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Verify & Close Order</span>
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
