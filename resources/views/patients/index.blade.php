@extends('layouts.app')

@section('title', 'Patient Registry')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Patients</h1>
            <p class="text-slate-500 mt-1">Find an existing patient, or register someone new.</p>
        </div>
        @if(auth()->user()->hasPermission('register_patients'))
            <a href="{{ route('patients.create') }}" class="bg-teal-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Register a patient
            </a>
        @endif
    </div>

    <!-- Search Section (Forced search before creation logic) -->
    <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200">
        <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center">
            <svg class="w-6 h-6 text-teal-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            Find a patient first
        </h2>
        <p class="text-sm text-slate-500 mb-8">A quick search by name, phone, or ID helps us avoid creating duplicate records.</p>

        <form action="{{ route('patients.index') }}" method="GET" class="flex gap-4">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-5 flex items-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, hospital ID, NHIS number or phone..."
                       class="block w-full pl-14 pr-6 py-5 bg-slate-50 border-none rounded-[2rem] text-slate-900 font-medium placeholder-slate-400 focus:ring-2 focus:ring-teal-500 transition-all shadow-inner">
            </div>
            <button type="submit" class="bg-teal-600 text-white px-10 py-5 rounded-[2rem] font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all text-sm">
                Search
            </button>
        </form>
    </div>

    @if(!request('search'))
    <div class="bg-white/50 border-2 border-dashed border-slate-200 rounded-[3rem] p-20 flex flex-col items-center justify-center text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-3xl flex items-center justify-center text-slate-300 mb-6">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a2 2 0 00-.586-1.414l-5.414-5.414A2 2 0 0011.586 2H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"></path></svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-500">Search to get started</h3>
        <p class="text-slate-400 max-w-sm mt-2 text-sm">Try a name, phone number, or hospital ID above to find a patient's record.</p>
    </div>
    @endif

    @if(request('search'))
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-900">Search results</h3>
            <a href="{{ route('patients.create') }}" class="text-teal-600 font-semibold text-sm hover:underline flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Register a new patient instead
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-400 border-b border-slate-50">
                        <th class="px-8 py-4">Patient</th>
                        <th class="px-8 py-4">Insurance / ID</th>
                        <th class="px-8 py-4">Last updated</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($patients as $patient)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-5">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-xl bg-teal-50 flex items-center justify-center text-teal-600 font-bold mr-4">
                                    {{ substr($patient->full_name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $patient->full_name }}</div>
                                    <div class="text-xs text-slate-400 leading-tight">
                                        {{ $patient->sex }} &bull; {{ $patient->age }} years<br>
                                        <span class="text-slate-500">{{ $patient->address ?? 'No address on file' }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-xs font-semibold text-slate-700">{{ $patient->hospital_id }}</div>
                            <div class="text-xs text-slate-400">NHIS: {{ $patient->nhis_number ?? 'None on file' }}</div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-xs font-medium text-slate-500">{{ $patient->updated_at->format('d M, Y') }}</div>
                            <div class="text-xs text-slate-400">{{ $patient->updated_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-8 py-5 text-right">
                            @if($patient->lastVisit)
                                <div class="text-xs text-slate-400 mb-1">Last seen {{ $patient->lastVisit->created_at->format('d M, Y') }}</div>
                            @endif
                            <a href="{{ route('patients.show', $patient) }}" class="text-teal-600 hover:text-teal-700 font-semibold text-sm">Open record</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <p class="text-slate-500 font-medium">We couldn't find anyone matching "{{ request('search') }}"</p>
                                <a href="{{ route('patients.create') }}" class="mt-4 text-teal-600 font-semibold hover:underline">Register them as a new patient</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($patients->hasPages())
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100">
            {{ $patients->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
