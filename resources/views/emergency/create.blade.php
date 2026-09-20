@extends('layouts.app')

@section('title', 'Emergency Arrival')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-red-600 tracking-tight">Emergency arrival</h1>
        <p class="text-slate-500 mt-1">Get them to triage first. Full details can be filled in later.</p>
    </div>

    <form action="{{ route('emergency.store') }}" method="POST" class="bg-white p-10 rounded-[3rem] shadow-sm border border-red-100 space-y-6">
        @csrf

        <label class="flex items-center space-x-2 p-4 bg-red-50 rounded-2xl border border-red-100 cursor-pointer">
            <input type="checkbox" id="is_unidentified" name="is_unidentified" value="1" class="h-5 w-5 rounded text-red-600 focus:ring-red-500">
            <span class="text-sm font-semibold text-red-800">Patient's identity is unknown (unconscious, no ID, no one accompanying)</span>
        </label>

        <div id="name_field">
            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Full name</label>
            <input type="text" name="full_name" id="full_name" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-red-500" placeholder="e.g. Kofi Mensah">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Apparent sex</label>
                <select name="apparent_sex" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-red-500">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Unclear / Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Estimated age</label>
                <input type="number" name="estimated_age" min="0" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-red-500" placeholder="e.g. 30">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">What happened / presenting complaint</label>
            <textarea name="reason_for_visit" rows="3" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-red-500" placeholder="e.g. Road traffic accident, unconscious on arrival"></textarea>
        </div>

        <button type="submit" class="w-full py-5 bg-red-600 text-white rounded-[2rem] font-semibold hover:bg-red-700 shadow-lg shadow-red-100 transition-all">
            Register &amp; go to triage now
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('is_unidentified');
    const nameField = document.getElementById('name_field');
    const nameInput = document.getElementById('full_name');

    checkbox.addEventListener('change', function () {
        nameField.classList.toggle('hidden', this.checked);
        nameInput.required = !this.checked;
    });
});
</script>
@endsection
