@extends('layouts.app')

@section('title', 'Paediatric Record - ' . $patient->full_name)

@section('content')
@php
    $card  = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $label = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 transition focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none';

    $latest = $measurements->last();
    $grade = $latest?->acuteMalnutritionGrade();

    $statusTone = [
        'given' => ['bg-emerald-50', 'text-emerald-700', 'border-emerald-100'],
        'due' => ['bg-amber-50', 'text-amber-700', 'border-amber-100'],
        'overdue' => ['bg-red-50', 'text-red-700', 'border-red-100'],
        'scheduled' => ['bg-slate-50', 'text-slate-600', 'border-slate-200'],
        'unknown' => ['bg-slate-50', 'text-slate-500', 'border-slate-200'],
    ];
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">Paediatric record</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $patient->full_name }} &bull; {{ $patient->hospital_id }} &bull;
                {{ ucfirst($patient->sex) }}, {{ $patient->age }} years
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex items-center gap-2 bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to record
        </a>
    </div>

    @if($grade === 'severe' || $grade === 'moderate')
        <div class="rounded-2xl p-5 border {{ $grade === 'severe' ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }}">
            <p class="text-sm font-bold {{ $grade === 'severe' ? 'text-red-900' : 'text-amber-900' }}">
                {{ ucfirst($grade) }} acute malnutrition by MUAC ({{ $latest->muac_cm }} cm)
            </p>
            <p class="text-sm {{ $grade === 'severe' ? 'text-red-800' : 'text-amber-800' }} mt-0.5">
                Measured {{ $latest->measured_at->format('d M Y') }} at {{ $latest->age_months }} months.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Growth</h2>

            @if($measurements->isEmpty())
                <p class="text-sm text-slate-400 italic">No measurements recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 border-b border-slate-100">
                                <th class="py-2">Date</th>
                                <th class="py-2">Age</th>
                                <th class="py-2">Weight</th>
                                <th class="py-2">Height</th>
                                <th class="py-2">MUAC</th>
                                <th class="py-2">BMI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($measurements->sortByDesc('measured_at') as $m)
                                <tr>
                                    <td class="py-2 text-slate-700">{{ $m->measured_at->format('d M Y') }}</td>
                                    <td class="py-2 text-slate-500">{{ $m->age_months !== null ? $m->age_months.' mo' : '—' }}</td>
                                    <td class="py-2 font-medium text-slate-900">{{ $m->weight_kg ? $m->weight_kg.' kg' : '—' }}</td>
                                    <td class="py-2 text-slate-700">{{ $m->height_cm ? $m->height_cm.' cm' : '—' }}</td>
                                    <td class="py-2 {{ in_array($m->acuteMalnutritionGrade(), ['severe','moderate'], true) ? 'text-red-600 font-bold' : 'text-slate-700' }}">
                                        {{ $m->muac_cm ? $m->muac_cm.' cm' : '—' }}
                                    </td>
                                    <td class="py-2 text-slate-700">{{ $m->bmi() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-500 mt-3">
                    This is the child's own trajectory. WHO population percentile bands are not plotted —
                    the reference tables are not loaded in this system.
                </p>
            @endif

            <form action="{{ route('paediatrics.growth.store', $patient) }}" method="POST" class="mt-5 space-y-4 pt-5 border-t border-slate-100">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $label }}">Weight (kg)</label><input type="number" step="0.01" name="weight_kg" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">Height (cm)</label><input type="number" step="0.1" name="height_cm" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">Head circ. (cm)</label><input type="number" step="0.1" name="head_circumference_cm" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">MUAC (cm)</label><input type="number" step="0.1" name="muac_cm" class="{{ $input }}"></div>
                </div>
                <div>
                    <label class="{{ $label }}">Measured at</label>
                    <input type="datetime-local" name="measured_at" class="{{ $input }}">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-xl text-sm font-bold hover:bg-brand-700 transition-colors">
                    Record measurement
                </button>
            </form>
        </div>

        <div class="{{ $card }}">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Immunisation</h2>
                @if($outstanding->isNotEmpty())
                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-100">
                        {{ $outstanding->count() }} outstanding
                    </span>
                @endif
            </div>

            <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                @foreach($scheduleStatus as $entry)
                    @php [$bg, $fg, $border] = $statusTone[$entry['status']] ?? $statusTone['unknown']; @endphp
                    <div class="flex items-center justify-between gap-3 p-3 rounded-xl border {{ $bg }} {{ $border }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">
                                {{ $entry['name'] }} @if($entry['dose'] > 0)<span class="text-slate-400">dose {{ $entry['dose'] }}</span>@else<span class="text-slate-400">birth dose</span>@endif
                            </p>
                            <p class="text-[10px] font-medium text-slate-500">
                                @if($entry['given_at'])
                                    Given {{ $entry['given_at']->format('d M Y') }}
                                @elseif($entry['due_on'])
                                    Due {{ $entry['due_on']->format('d M Y') }}
                                @else
                                    No date of birth recorded
                                @endif
                            </p>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full {{ $fg }} shrink-0">
                            {{ $entry['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <form action="{{ route('paediatrics.immunizations.store', $patient) }}" method="POST" class="mt-5 space-y-4 pt-5 border-t border-slate-100">
                @csrf
                <div>
                    <label class="{{ $label }}">Vaccine</label>
                    <select name="vaccine_code" id="vaccineCode" required class="{{ $input }}" onchange="syncVaccineFields()">
                        <option value="">Select a dose...</option>
                        @foreach($scheduleStatus->where('status', '!=', 'given') as $entry)
                            <option value="{{ $entry['code'] }}" data-name="{{ $entry['name'] }}" data-dose="{{ $entry['dose'] }}">
                                {{ $entry['name'] }} — dose {{ $entry['dose'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="vaccine_name" id="vaccineName">
                <input type="hidden" name="dose_number" id="doseNumber">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $label }}">Batch number</label><input type="text" name="batch_number" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}">Site</label><input type="text" name="site" class="{{ $input }}" placeholder="e.g. Left thigh"></div>
                </div>
                <div>
                    <label class="{{ $label }}">Given at</label>
                    <input type="datetime-local" name="given_at" class="{{ $input }}">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-xl text-sm font-bold hover:bg-brand-700 transition-colors">
                    Record immunisation
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function syncVaccineFields() {
    const select = document.getElementById('vaccineCode');
    const option = select.options[select.selectedIndex];

    document.getElementById('vaccineName').value = option.dataset.name ?? '';
    document.getElementById('doseNumber').value = option.dataset.dose ?? '';
}
</script>
@endsection
