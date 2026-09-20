@extends('layouts.app')

@section('title', 'Merge Patients')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Merge duplicate patients</h1>
        <p class="text-slate-500 mt-1">Combine two records for the same person into one. This can't be undone, so double-check the IDs first.</p>
    </div>

    <form action="{{ route('admin.patients.merge.store') }}" method="POST" class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-200 space-y-8">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Record to keep</label>
            <input type="text" name="keep_id" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500" placeholder="e.g. HIMS-2026-00001">
        </div>

        <div class="flex items-center justify-center">
            <div class="h-px bg-slate-100 flex-1"></div>
            <span class="mx-4 text-slate-400 font-semibold text-xs">merge with</span>
            <div class="h-px bg-slate-100 flex-1"></div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Duplicate record to retire</label>
            <input type="text" name="merge_id" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-red-500" placeholder="e.g. HIMS-2026-00002">
        </div>

        <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 flex items-start">
            <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p class="text-sm text-blue-800 leading-relaxed">We'll combine all allergies and chronic conditions onto the record you keep. The duplicate record will be deactivated, not deleted.</p>
        </div>

        <button type="submit" class="w-full py-5 bg-slate-900 text-white rounded-[2rem] font-semibold hover:bg-slate-800 shadow-lg transition-all">
            Merge these records
        </button>
    </form>
</div>
@endsection
