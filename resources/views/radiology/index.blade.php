@extends('layouts.app')

@section('title', 'Radiology Worklist')

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Radiology worklist</h1>
            <p class="text-sm text-slate-500 mt-0.5">Imaging requests waiting to be performed, reported, or read.</p>
        </div>
        <div class="flex items-center gap-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 shadow-sm px-3.5 py-2 rounded-lg shrink-0">
            <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
            <span>{{ $orders->total() }} open</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                        <th class="px-5 py-2.5">Study</th>
                        <th class="px-5 py-2.5">Patient</th>
                        <th class="px-5 py-2.5">Indication</th>
                        <th class="px-5 py-2.5">Status</th>
                        <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                    <tr class="hover:bg-indigo-50/40 transition-colors">
                        <td class="px-5 py-3">
                            <div class="text-sm font-semibold text-slate-900">{{ $order->study_label }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full
                                    @if($order->priority === 'stat') bg-red-50 text-red-700 border border-red-100
                                    @elseif($order->priority === 'urgent') bg-amber-50 text-amber-700 border border-amber-100
                                    @else bg-slate-50 text-slate-600 border border-slate-200 @endif">
                                    {{ $order->priority }}
                                </span>
                                @if($order->contrast_required)
                                    <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider">Contrast</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="text-sm font-medium text-slate-900">{{ $order->visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500">{{ $order->visit->patient->hospital_id }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-sm text-slate-700 max-w-xs truncate">{{ $order->clinical_indication }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">Requested by {{ $order->orderedBy->name ?? 'unknown' }}</p>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-50 text-slate-600 border border-slate-200">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('radiology.show', $order) }}" class="inline-flex items-center bg-indigo-600 text-white px-4 py-1.5 rounded-lg font-semibold text-sm hover:bg-indigo-700 transition-colors">
                                Open
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-14 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 mb-3">
                                    <i data-lucide="scan" class="w-6 h-6"></i>
                                </div>
                                <p class="text-sm font-medium text-slate-700">No imaging requests outstanding</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-5 py-3 border-t border-slate-200">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
