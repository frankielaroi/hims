@extends('layouts.app')

@section('title', 'Patient Registry')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">Patients</h1>
            <p class="text-slate-400 mt-1">Find an existing patient, or register someone new.</p>
        </div>
        @if(auth()->user()->hasPermission('register_patients'))
            <a href="{{ route('patients.create') }}" class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all duration-200 flex items-center">
                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                Register a patient
            </a>
        @endif
    </div>

    <!-- Search Section (Forced search before creation logic) -->
    <div class="bg-slate-900 p-8 rounded-lg border border-slate-800">
        <h2 class="text-lg font-bold text-slate-100 mb-2 flex items-center">
            <i data-lucide="search" class="w-5 h-5 text-emerald-400 mr-3"></i>
            Find a patient first
        </h2>
        <p class="text-sm text-slate-400 mb-6">A quick search by name, phone, or ID helps us avoid creating duplicate records.</p>

        <form action="{{ route('patients.index') }}" method="GET" class="flex gap-3">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, hospital ID, NHIS number or phone..."
                       class="block w-full pl-11 pr-5 py-3.5 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none transition-all">
            </div>
            <button type="submit" class="bg-emerald-600 text-white px-8 py-3.5 rounded-lg font-semibold hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all text-sm">
                Search
            </button>
        </form>
    </div>

    @if(!request('search'))
    <div class="bg-slate-900/50 border-2 border-dashed border-slate-800 rounded-lg p-20 flex flex-col items-center justify-center text-center">
        <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center text-slate-500 mb-6">
            <i data-lucide="user-search" class="w-8 h-8"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-300">Search to get started</h3>
        <p class="text-slate-500 max-w-sm mt-2 text-sm">Try a name, phone number, or hospital ID above to find a patient's record.</p>
    </div>
    @endif

    @if(request('search'))
    <div class="bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-100">Search results</h3>
            <a href="{{ route('patients.create') }}" class="text-emerald-400 font-semibold text-sm hover:underline flex items-center">
                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                Register a new patient instead
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-xs font-semibold text-slate-500 border-b border-slate-800">
                        <th class="px-6 py-3">Patient</th>
                        <th class="px-6 py-3">Insurance / ID</th>
                        <th class="px-6 py-3">Last updated</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($patients as $patient)
                    <tr class="hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-lg bg-emerald-950 border border-emerald-800 flex items-center justify-center text-emerald-400 font-bold mr-4">
                                    {{ substr($patient->full_name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-100">{{ $patient->full_name }}</div>
                                    <div class="text-xs text-slate-500 leading-tight">
                                        {{ $patient->sex }} &bull; {{ $patient->age }} years<br>
                                        <span class="text-slate-400">{{ $patient->address ?? 'No address on file' }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs font-semibold text-slate-300">{{ $patient->hospital_id }}</div>
                            <div class="text-xs text-slate-500">NHIS: {{ $patient->nhis_number ?? 'None on file' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs font-medium text-slate-400">{{ $patient->updated_at->format('d M, Y') }}</div>
                            <div class="text-xs text-slate-500">{{ $patient->updated_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($patient->lastVisit)
                                <div class="text-xs text-slate-500 mb-1">Last seen {{ $patient->lastVisit->created_at->format('d M, Y') }}</div>
                            @endif
                            <a href="{{ route('patients.show', $patient) }}" class="text-emerald-400 hover:text-emerald-300 font-semibold text-sm">Open record</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center text-slate-500 mb-4">
                                    <i data-lucide="users" class="w-8 h-8"></i>
                                </div>
                                <p class="text-slate-400 font-medium">We couldn't find anyone matching "{{ request('search') }}"</p>
                                <a href="{{ route('patients.create') }}" class="mt-4 text-emerald-400 font-semibold hover:underline">Register them as a new patient</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($patients->hasPages())
        <div class="px-6 py-4 bg-slate-950/50 border-t border-slate-800">
            {{ $patients->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
