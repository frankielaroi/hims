@extends('layouts.app')

@section('title', 'Patient Registry')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Patients</h1>
            <p class="text-sm text-slate-500 mt-0.5">Find an existing patient, or register someone new.</p>
        </div>
        @if(auth()->user()->hasPermission('register_patients'))
            <a href="{{ route('patients.create') }}" class="bg-brand-600 text-white px-4 py-2 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors flex items-center shrink-0">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                Register a patient
            </a>
        @endif
    </div>

    <!-- Search first, to avoid duplicate records -->
    <form action="{{ route('patients.index') }}" method="GET" class="bg-white p-2 rounded-xl border border-slate-200 shadow-sm flex items-center gap-2">
        <div class="flex-1 relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="search" name="search" value="{{ request('search') }}" autofocus
                   placeholder="Search by name, hospital ID, NHIS number or phone"
                   class="block w-full pl-9 pr-3 py-2.5 bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none">
        </div>
        @if(request('search'))
            <a href="{{ route('patients.index') }}" class="px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">Clear</a>
        @endif
        <button type="submit" class="bg-brand-600 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
            Search
        </button>
    </form>

    @if(!request('search'))
        <div class="flex flex-col items-center justify-center text-center py-16">
            <div class="w-12 h-12 bg-brand-50 rounded-xl flex items-center justify-center text-brand-600 mb-4">
                <i data-lucide="user-search" class="w-6 h-6"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-900">Search to get started</h3>
            <p class="text-sm text-slate-500 max-w-sm mt-1">A quick search by name, phone number or hospital ID helps us avoid duplicate records.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    {{ $patients->total() }} {{ \Illuminate\Support\Str::plural('result', $patients->total()) }}
                </h2>
                <a href="{{ route('patients.create') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 flex items-center">
                    <i data-lucide="plus" class="w-4 h-4 mr-1"></i>
                    Register a new patient instead
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-medium text-slate-500 bg-slate-50">
                            <th class="px-5 py-2.5">Patient</th>
                            <th class="px-5 py-2.5">ID and insurance</th>
                            <th class="px-5 py-2.5">Last visit</th>
                            <th class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($patients as $patient)
                        <tr class="hover:bg-brand-50/40 transition-colors cursor-pointer" onclick="window.location='{{ route('patients.show', $patient) }}'">
                            <td class="px-5 py-3">
                                <div class="flex items-center">
                                    <div class="h-9 w-9 rounded-lg bg-brand-50 flex items-center justify-center text-brand-700 text-sm font-semibold mr-3 shrink-0">
                                        {{ strtoupper(substr($patient->full_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-slate-900 truncate">{{ $patient->full_name }}</div>
                                        <div class="text-xs text-slate-500 truncate">
                                            {{ ucfirst($patient->sex) }} &bull; {{ $patient->age }} years &bull; {{ $patient->address ?? 'No address on file' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900">{{ $patient->hospital_id }}</div>
                                <div class="text-xs text-slate-500">NHIS: {{ $patient->nhis_number ?? 'None on file' }}</div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="text-sm text-slate-700">
                                    {{ $patient->lastVisit ? $patient->lastVisit->created_at->format('d M, Y') : 'No visits yet' }}
                                </div>
                                <div class="text-xs text-slate-400">Updated {{ $patient->updated_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center text-sm font-medium text-brand-600 hover:text-brand-700">
                                    Open record
                                    <i data-lucide="chevron-right" class="w-4 h-4 ml-0.5"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-5 py-14 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 mb-3">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                    <p class="text-sm font-medium text-slate-700">We couldn't find anyone matching "{{ request('search') }}"</p>
                                    <a href="{{ route('patients.create') }}" class="mt-2 text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">Register them as a new patient</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($patients->hasPages())
                <div class="px-5 py-3 border-t border-slate-200">
                    {{ $patients->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection