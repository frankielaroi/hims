@extends('layouts.app')

@section('title', 'Add Reagent')

@section('content')
<div class="max-w-xl mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Add Reagent</h1>
        <p class="text-slate-500 mt-1">Track new batches of lab supplies.</p>
    </div>

    <form action="{{ route('lab.inventory.store') }}" method="POST" class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200 space-y-6">
        @csrf

        <div class="grid grid-cols-2 gap-6">
            <div class="col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Reagent Name</label>
                <input type="text" name="name" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="e.g. Malaria RDT Kits">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Internal Code</label>
                <input type="text" name="code" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="KIT-001">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Batch Number</label>
                <input type="text" name="batch_number" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" placeholder="B-2023-X">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Initial Stock</label>
                <input type="number" name="current_stock" required min="0" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Min Alert Level</label>
                <input type="number" name="min_stock_level" required min="0" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Expiry Date</label>
                <input type="date" name="expiry_date" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Unit</label>
                <input type="text" name="unit" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-purple-500" value="tests">
            </div>
        </div>

        <button type="submit" class="w-full py-4 bg-purple-600 text-white rounded-2xl font-bold text-lg hover:bg-purple-700 transition-all shadow-lg shadow-purple-100">
            Register Batch
        </button>
    </form>
</div>
@endsection
