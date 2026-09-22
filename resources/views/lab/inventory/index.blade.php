@extends('layouts.app')

@section('title', 'Reagent Inventory')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Lab Inventory</h1>
            <p class="text-slate-500 mt-1">Manage reagents, batch tracking, and stock levels.</p>
        </div>
        <a href="{{ route('lab.inventory.create') }}" class="bg-purple-600 text-white px-6 py-3 rounded-2xl font-bold hover:bg-purple-700 transition-all shadow-lg shadow-purple-100 flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            <span>Add Reagent</span>
        </a>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr class="text-xs font-semibold text-slate-400 border-b border-slate-50 uppercase tracking-wider">
                    <th class="px-8 py-5">Reagent / Batch</th>
                    <th class="px-8 py-5 text-center">Current Stock</th>
                    <th class="px-8 py-5">Expiry</th>
                    <th class="px-8 py-5 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($reagents as $reagent)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-8 py-6">
                        <div class="text-sm font-bold text-slate-900">{{ $reagent->name }}</div>
                        <div class="text-xs text-slate-500 font-medium">Code: {{ $reagent->code }} &bull; Batch: {{ $reagent->batch_number }}</div>
                    </td>
                    <td class="px-8 py-6 text-center">
                        <div class="text-sm font-bold text-slate-900">{{ $reagent->current_stock }} {{ $reagent->unit }}</div>
                        <div class="text-xs text-slate-400">Min level: {{ $reagent->min_stock_level }}</div>
                    </td>
                    <td class="px-8 py-6">
                        <div class="text-sm font-medium {{ $reagent->expiry_date && $reagent->expiry_date->isPast() ? 'text-red-600' : 'text-slate-600' }}">
                            {{ $reagent->expiry_date ? $reagent->expiry_date->format('M d, Y') : 'N/A' }}
                        </div>
                    </td>
                    <td class="px-8 py-6 text-right">
                        @if($reagent->current_stock <= 0)
                            <span class="px-3 py-1 text-xs font-bold bg-red-100 text-red-700 rounded-full">Out of Stock</span>
                        @elseif($reagent->current_stock <= $reagent->min_stock_level)
                            <span class="px-3 py-1 text-xs font-bold bg-orange-100 text-orange-700 rounded-full">Low Stock</span>
                        @else
                            <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-700 rounded-full">Healthy</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-8 py-20 text-center text-slate-500">No reagents in inventory</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
