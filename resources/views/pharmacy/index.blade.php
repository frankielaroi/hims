@extends('layouts.app')

@section('title', 'Pharmacy Queue')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Pharmacy</h1>
            <p class="text-slate-500 mt-1">Prescriptions waiting to be dispensed.</p>
        </div>
        <div class="flex items-center space-x-2 text-sm text-slate-500 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-200">
            <span class="w-2 h-2 bg-teal-500 rounded-full animate-pulse"></span>
            <span>{{ $prescriptions->total() }} waiting</span>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Medications</th>
                        <th class="px-8 py-4">Status</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($prescriptions as $prescription)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="text-sm font-semibold text-slate-900">{{ $prescription->visit->patient->full_name }}</div>
                            <div class="text-xs text-slate-500 font-medium">{{ $prescription->visit->patient->hospital_id }} &bull; {{ $prescription->visit->department->name }}</div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="flex flex-wrap gap-1">
                                @foreach($prescription->items as $item)
                                    <span class="px-2 py-1 text-xs font-medium bg-teal-50 text-teal-700 rounded-md">{{ $item->drug->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full
                                @if($prescription->status === 'partially_dispensed') bg-orange-100 text-orange-700
                                @else bg-blue-100 text-blue-700 @endif">
                                {{ ucfirst(str_replace('_', ' ', $prescription->status)) }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="{{ route('pharmacy.show', $prescription) }}" class="bg-teal-600 text-white px-5 py-2 rounded-xl font-semibold text-sm hover:bg-teal-700 transition-all shadow-sm">
                                Dispense
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.212 1.212 3.18 1.212 4.392 0z"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium text-sm">No prescriptions waiting</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($prescriptions->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $prescriptions->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
