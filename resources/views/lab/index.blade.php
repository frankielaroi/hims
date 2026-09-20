@extends('layouts.app')

@section('title', 'Laboratory Queue')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Laboratory</h1>
            <p class="text-slate-500 mt-1">Investigations ordered by doctors, waiting on specimens or results.</p>
        </div>
        <div class="flex items-center space-x-2 text-sm text-slate-500 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            <span class="w-2 h-2 bg-purple-500 rounded-full animate-pulse"></span>
            <span>{{ $orders->total() }} pending</span>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Tests</th>
                        <th class="px-8 py-4">Priority</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($orders as $order)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $order->visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500 font-medium">{{ $order->visit->patient->hospital_id }} &bull; {{ $order->visit->department->name }}</div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex flex-wrap gap-1">
                                @foreach($order->items as $item)
                                    <span class="px-2 py-1 text-xs font-medium bg-purple-50 text-purple-700 rounded-md">{{ $item->labTest->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($order->priority == 'stat') bg-red-100 text-red-700
                                @elseif($order->priority == 'urgent') bg-orange-100 text-orange-700
                                @else bg-blue-100 text-blue-700 @endif">
                                {{ ucfirst($order->priority) }}
                            </span>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold bg-slate-100 text-slate-600 rounded-full">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('lab.show', $order) }}" class="bg-purple-600 text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-purple-700 transition-all shadow-sm">
                                Process
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v3m6-3v3M9 21v-3m6 3v-3M5 9H2m3 6H2m20-6h-3m3 6h-3M9 21h6a2 2 0 002-2V5a2 2 0 00-2-2H9a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium text-sm">No pending investigations right now</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
