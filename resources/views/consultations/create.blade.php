@extends('layouts.app')

@section('title', 'Clinical Consultation - ' . $visit->patient->full_name)

@section('content')
@php
    use App\Models\Consultation;

    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 transition focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none';
    $label = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
    $card  = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $opt   = 'block px-4 py-3 rounded-xl border-2 border-slate-100 bg-slate-50 cursor-pointer transition hover:bg-white peer-checked:bg-white peer-checked:border-brand-500 peer-checked:ring-4 peer-checked:ring-brand-500/5';

    $isSigned = $consultation->isSigned();
    $showObstetric = $visit->patient->sex === 'female';
    $isChild = $visit->patient->age !== null && $visit->patient->age < 18;
    $labsToReview = $visit->labOrdersAwaitingReview;
    $imagingToReview = $visit->radiologyOrdersAwaitingReview;
    $allergies = $visit->patient->activeAllergies;
    $criticalAllergies = $allergies->filter(fn ($a) => $a->isCritical());
@endphp

<div class="max-w-7xl mx-auto space-y-6 pb-20">
    <!-- Clinical Safety Banner -->
    <div class="bg-slate-900 rounded-[2rem] p-6 text-white shadow-xl flex flex-wrap items-center justify-between gap-6 sticky top-4 z-30">
        <div class="flex items-center gap-6">
            <div class="h-16 w-16 rounded-2xl bg-brand-500 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-brand-500/20">
                {{ strtoupper(substr($visit->patient->full_name, 0, 1)) }}
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight">{{ $visit->patient->full_name }}</h1>
                    <span class="px-2 py-0.5 bg-white/10 rounded-md text-[10px] font-bold uppercase tracking-widest">{{ $visit->patient->hospital_id }}</span>
                </div>
                <div class="flex items-center gap-4 mt-1 text-slate-400 text-sm font-medium">
                    <span>{{ ucfirst($visit->patient->sex) }} &bull; {{ $visit->patient->age }} years</span>
                    <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                    <span class="text-brand-400">Visit {{ $visit->visit_number }}</span>
                    <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                    <span class="text-slate-300">{{ $consultation->status_label }} &bull; v{{ $consultation->version }}</span>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            @if($allergies->isNotEmpty())
                <div class="px-4 py-2 {{ $criticalAllergies->isNotEmpty() ? 'bg-red-500/20 border-red-500/40' : 'bg-amber-500/10 border-amber-500/20' }} border rounded-xl flex items-center gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 {{ $criticalAllergies->isNotEmpty() ? 'text-red-400' : 'text-amber-400' }}"></i>
                    <div>
                        <div class="text-[10px] font-bold {{ $criticalAllergies->isNotEmpty() ? 'text-red-400' : 'text-amber-400' }} uppercase tracking-widest">Allergies</div>
                        <div class="text-xs font-semibold">
                            @foreach($allergies as $allergy)
                                <span title="{{ $allergy->reaction }}">{{ $allergy->allergen }} ({{ $allergy->severity }})</span>@if(! $loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="px-4 py-2 bg-white/5 border border-white/10 rounded-xl flex items-center gap-3">
                    <i data-lucide="shield-check" class="w-5 h-5 text-slate-400"></i>
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Allergies</div>
                        <div class="text-xs font-semibold text-slate-300">None recorded</div>
                    </div>
                </div>
            @endif

            @if($visit->patient->sex === 'female')
                <div class="px-4 py-2 bg-purple-500/10 border border-purple-500/20 rounded-xl flex items-center gap-3">
                    <i data-lucide="baby" class="w-5 h-5 text-purple-400"></i>
                    <div>
                        <div class="text-[10px] font-bold text-purple-400 uppercase tracking-widest">Pregnancy</div>
                        <div class="text-xs font-semibold">{{ ucfirst($consultation->pregnancy_status ?? 'Unknown') }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- Patient Context Sidebar -->
        <aside class="md:col-span-4 lg:col-span-3 space-y-6">
            <!-- Critical Vitals Flagging -->
            <div class="{{ $card }}">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Current Vitals</h3>
                    <span class="text-[10px] font-bold text-slate-400">{{ $visit->latestVitals?->created_at->diffForHumans() }}</span>
                </div>
                @if($v = $visit->latestVitals)
                <div class="space-y-4">
                    <div class="flex justify-between items-end pb-2 border-b border-slate-100">
                        <span class="text-xs font-medium text-slate-500">Temp</span>
                        <span class="text-sm font-bold {{ $v->temperature > 37.5 || $v->temperature < 36.0 ? 'text-red-600' : 'text-slate-900' }}">{{ $v->temperature }}°C</span>
                    </div>
                    <div class="flex justify-between items-end pb-2 border-b border-slate-100">
                        <span class="text-xs font-medium text-slate-500">Blood Pressure</span>
                        <span class="text-sm font-bold {{ $v->systolic_bp > 140 || $v->systolic_bp < 90 ? 'text-red-600' : 'text-slate-900' }}">{{ $v->systolic_bp }}/{{ $v->diastolic_bp }}</span>
                    </div>
                    <div class="flex justify-between items-end pb-2 border-b border-slate-100">
                        <span class="text-xs font-medium text-slate-500">Pulse</span>
                        <span class="text-sm font-bold {{ $v->pulse_rate > 100 || $v->pulse_rate < 60 ? 'text-red-600' : 'text-slate-900' }}">{{ $v->pulse_rate }} bpm</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="text-xs font-medium text-slate-500">SpO₂</span>
                        <span class="text-sm font-bold {{ $v->spo2 < 94 ? 'text-red-600' : 'text-slate-900' }}">{{ $v->spo2 }}%</span>
                    </div>
                </div>
                @else
                    <p class="text-sm text-slate-400 text-center py-4 italic border-2 border-dashed border-slate-100 rounded-xl">No vitals recorded</p>
                @endif
            </div>

            <!-- Allergies -->
            <div class="{{ $card }}">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Allergies</h3>
                <div class="space-y-3">
                    @forelse($allergies as $allergy)
                        <div class="p-3 rounded-xl border {{ $allergy->isCritical() ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100' }}">
                            <p class="text-sm font-bold {{ $allergy->isCritical() ? 'text-red-900' : 'text-amber-900' }}">{{ $allergy->allergen }}</p>
                            <p class="text-[10px] font-medium {{ $allergy->isCritical() ? 'text-red-700' : 'text-amber-700' }}">
                                {{ ucfirst($allergy->severity) }}@if($allergy->reaction) &bull; {{ $allergy->reaction }}@endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">None documented</p>
                    @endforelse
                </div>
            </div>

            <!-- Chronic Medications -->
            <div class="{{ $card }}">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Chronic Medications</h3>
                <div class="space-y-3">
                    @forelse($visit->patient->activeChronicMedications as $med)
                        <div class="p-3 bg-blue-50 rounded-xl border border-blue-100">
                            <p class="text-sm font-bold text-blue-900">{{ $med->drug_name }}</p>
                            <p class="text-[10px] text-blue-700 font-medium">{{ $med->strength }} &bull; {{ $med->frequency }}</p>
                            @unless($isSigned)
                                <button type="button" onclick="continueMedication({{ Js::from([
                                        'id' => $med->id,
                                        'drug_id' => $med->drug_id,
                                        'drug_name' => $med->drug_name,
                                        'dose' => $med->dose,
                                        'route' => $med->route,
                                        'frequency' => $med->frequency,
                                    ]) }})" class="mt-2 text-[10px] font-bold text-blue-600 uppercase hover:text-blue-800 transition-colors">Continue in Plan &rarr;</button>
                            @endunless
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">None documented</p>
                    @endforelse
                </div>
            </div>

            <!-- Results waiting to be read -->
            @if($labsToReview->isNotEmpty() || $imagingToReview->isNotEmpty())
            <div class="{{ $card }} border-amber-200">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Results to read</h3>
                <div class="space-y-3">
                    @foreach($labsToReview as $order)
                        <a href="{{ route('lab.show', $order) }}" class="block p-3 rounded-xl border {{ $order->hasCriticalResult() ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100' }}">
                            <p class="text-sm font-bold {{ $order->hasCriticalResult() ? 'text-red-900' : 'text-amber-900' }}">
                                Lab order #{{ $order->id }}{{ $order->hasCriticalResult() ? ' — critical' : '' }}
                            </p>
                            <p class="text-[10px] font-medium {{ $order->hasCriticalResult() ? 'text-red-700' : 'text-amber-700' }}">
                                Verified {{ $order->verified_at?->diffForHumans() }}
                            </p>
                        </a>
                    @endforeach
                    @foreach($imagingToReview as $order)
                        <a href="{{ route('radiology.show', $order) }}" class="block p-3 rounded-xl border {{ $order->is_critical ? 'bg-red-50 border-red-100' : 'bg-indigo-50 border-indigo-100' }}">
                            <p class="text-sm font-bold {{ $order->is_critical ? 'text-red-900' : 'text-indigo-900' }}">
                                {{ $order->study_label }}{{ $order->is_critical ? ' — critical' : '' }}
                            </p>
                            <p class="text-[10px] font-medium {{ $order->is_critical ? 'text-red-700' : 'text-indigo-700' }}">
                                Reported {{ $order->reported_at?->diffForHumans() }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Specialty records -->
            <div class="{{ $card }}">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Record</h3>
                <div class="space-y-2">
                    <a href="{{ route('patients.timeline', $visit->patient) }}" class="flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-800 transition-colors">
                        <i data-lucide="history" class="w-4 h-4"></i> Full clinical timeline
                    </a>
                    @if($isChild)
                        <a href="{{ route('paediatrics.show', $visit->patient) }}" class="flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-800 transition-colors">
                            <i data-lucide="baby" class="w-4 h-4"></i> Growth &amp; immunisation
                        </a>
                    @endif
                    @if($showObstetric)
                        <a href="{{ route('anc.show', $visit->patient) }}" class="flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-800 transition-colors">
                            <i data-lucide="heart-pulse" class="w-4 h-4"></i> Antenatal record
                        </a>
                    @endif
                </div>
            </div>

            <!-- Past Encounters Timeline -->
            <div class="{{ $card }}">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Clinical Timeline</h3>
                <div class="relative pl-4 space-y-6 before:absolute before:left-0 before:top-0 before:bottom-0 before:w-0.5 before:bg-slate-100">
                    @forelse($pastDiagnoses as $diag)
                        <div class="relative">
                            <div class="absolute -left-[1.375rem] top-1.5 w-3 h-3 rounded-full bg-white border-2 border-slate-300"></div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">{{ $diag->consultation->visit->created_at->format('d M Y') }}</p>
                            <p class="text-sm font-semibold text-slate-800 leading-tight mt-0.5">{{ $diag->description }}</p>
                            <p class="text-[10px] text-slate-400 font-medium">{{ $diag->icd10_code }} &bull; {{ $diag->type }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No history available</p>
                    @endforelse
                </div>
            </div>
        </aside>

        <!-- Main Consultation Workspace -->
        <main class="md:col-span-8 lg:col-span-9">
            @if($isSigned)
                <!-- Signed note: read-only, with an amendment trail -->
                <div class="space-y-6">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 flex items-start gap-3">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 mt-0.5"></i>
                        <div>
                            <p class="text-sm font-bold text-emerald-900">
                                Signed by {{ $consultation->signedBy?->name ?? 'unknown' }}
                                on {{ $consultation->signed_at?->format('d M Y, H:i') }}
                            </p>
                            <p class="text-sm text-emerald-800 mt-0.5">
                                This note is at version {{ $consultation->version }} and can no longer be edited in place.
                                Record an amendment to correct or add to it.
                            </p>
                        </div>
                    </div>

                    <div class="{{ $card }}">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Clinical Note (current version)</h2>
                        <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">{{ $consultation->notes->first()?->content ?? 'No note recorded.' }}</p>
                    </div>

                    <div class="{{ $card }}">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Diagnoses</h2>
                        <div class="space-y-2">
                            @forelse($consultation->diagnoses as $diag)
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $diag->description }}</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $diag->icd10_code }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md uppercase {{ $diag->type === 'primary' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }}">{{ $diag->type }}</span>
                                        <p class="text-[10px] text-slate-400 font-medium mt-0.5">{{ $diag->certainty }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 italic">None recorded</p>
                            @endforelse
                        </div>
                    </div>

                    @if($consultation->amendments->isNotEmpty())
                    <div class="{{ $card }}">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Amendment History</h2>
                        <div class="space-y-4">
                            @foreach($consultation->amendments->sortByDesc('created_at') as $amendment)
                                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ str_replace('_', ' ', $amendment->field_name) }}</span>
                                        <span class="text-[10px] font-medium text-slate-400">
                                            {{ $amendment->user?->name }} &bull; {{ $amendment->created_at?->format('d M Y, H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 line-through whitespace-pre-line">{{ $amendment->old_value }}</p>
                                    <p class="text-sm text-slate-800 whitespace-pre-line mt-1">{{ $amendment->new_value }}</p>
                                    <p class="text-xs text-slate-500 italic mt-2">Reason: {{ $amendment->reason }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Amendment form -->
                    <form action="{{ route('consultations.amend', $consultation) }}" method="POST" class="{{ $card }} space-y-4">
                        @csrf
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight flex items-center gap-2">
                            <i data-lucide="file-pen-line" class="w-4 h-4 text-brand-600"></i>
                            Record an Amendment
                        </h2>
                        <div>
                            <label class="{{ $label }}">Revised clinical note</label>
                            <textarea name="clinical_notes" rows="6" class="{{ $input }}">{{ old('clinical_notes', $consultation->notes->first()?->content) }}</textarea>
                        </div>
                        <div>
                            <label class="{{ $label }}">Reason for the amendment</label>
                            <textarea name="reason" rows="2" required minlength="10" class="{{ $input }}" placeholder="What new information changed the record?">{{ old('reason') }}</textarea>
                            <p class="text-xs text-slate-500 mt-1.5">The original text is kept and the note's version will be raised.</p>
                        </div>
                        <button type="submit" class="px-8 py-3 bg-brand-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-brand-500/20 hover:bg-brand-700 transition-all">
                            Save Amendment
                        </button>
                    </form>
                </div>
            @else
            <form action="{{ route('consultations.store', $visit) }}" method="POST" id="consultationForm" class="space-y-6">
                @csrf
                <input type="hidden" name="consultation_id" value="{{ $consultation->id }}">

                <!-- History & Physical Snapshot -->
                <div class="grid grid-cols-1 gap-6 {{ $showObstetric ? 'xl:grid-cols-2' : '' }}">
                    <div class="{{ $card }}">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4 flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-brand-600"></i>
                            History of Presenting Complaint
                        </h2>
                        <textarea name="clinical_notes" rows="8" class="{{ $input }}" placeholder="Onset, duration, severity, aggravating factors...">{{ old('clinical_notes', $consultation->notes->first()?->content) }}</textarea>
                    </div>

                    @if($showObstetric)
                    <div class="{{ $card }}">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4 flex items-center gap-2">
                            <i data-lucide="baby" class="w-4 h-4 text-purple-600"></i>
                            Obstetric History
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="{{ $label }}">Pregnancy Status</label>
                                <select name="pregnancy_status" class="{{ $input }}">
                                    <option value="unknown" @selected(old('pregnancy_status', $consultation->pregnancy_status) === 'unknown')>Unknown / Not Assessed</option>
                                    <option value="none" @selected(old('pregnancy_status', $consultation->pregnancy_status) === 'none')>Not Pregnant</option>
                                    <option value="pregnant" @selected(old('pregnancy_status', $consultation->pregnancy_status) === 'pregnant')>Confirmed Pregnant</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">LMP</label>
                                    <input type="date" name="lmp" value="{{ old('lmp', $consultation->lmp?->format('Y-m-d')) }}" class="{{ $input }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">EDD</label>
                                    <input type="date" name="edd" value="{{ old('edd', $consultation->edd?->format('Y-m-d')) }}" class="{{ $input }}">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">Gestational age</label>
                                    <input type="text" name="gestational_age" value="{{ old('gestational_age', $consultation->gestational_age) }}" class="{{ $input }}" placeholder="e.g. 24 weeks">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Lactation</label>
                                    <select name="lactation_status" class="{{ $input }}">
                                        <option value="unknown" @selected(old('lactation_status', $consultation->lactation_status) === 'unknown')>Unknown</option>
                                        <option value="none" @selected(old('lactation_status', $consultation->lactation_status) === 'none')>Not Breastfeeding</option>
                                        <option value="breastfeeding" @selected(old('lactation_status', $consultation->lactation_status) === 'breastfeeding')>Currently Breastfeeding</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Prescribing safety context -->
                <div class="{{ $card }}">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4 flex items-center gap-2">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-brand-600"></i>
                        Prescribing Safety Context
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">Used to check doses and to flag drugs cleared by the kidneys or liver.</p>
                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="{{ $label }}">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight_kg" value="{{ old('weight_kg', $consultation->weight_kg) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">eGFR</label>
                            <input type="number" step="0.1" name="egfr" value="{{ old('egfr', $consultation->egfr) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Renal impairment</label>
                            <select name="renal_impairment" class="{{ $input }}">
                                @foreach(['' => 'Not assessed', 'none' => 'None', 'mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe'] as $value => $title)
                                    <option value="{{ $value }}" @selected(old('renal_impairment', $consultation->renal_impairment) === ($value ?: null))>{{ $title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Hepatic impairment</label>
                            <select name="hepatic_impairment" class="{{ $input }}">
                                @foreach(['' => 'Not assessed', 'none' => 'None', 'mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe'] as $value => $title)
                                    <option value="{{ $value }}" @selected(old('hepatic_impairment', $consultation->hepatic_impairment) === ($value ?: null))>{{ $title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis Engine -->
                <div class="{{ $card }}">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight flex items-center gap-2">
                            <i data-lucide="stethoscope" class="w-4 h-4 text-brand-600"></i>
                            Assessments & Diagnoses
                        </h2>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Exactly one primary required to sign</span>
                    </div>
                    <div id="diagnosis-container" class="space-y-4">
                        @php $diags = old('diagnoses', $consultation->diagnoses->toArray() ?: [[]]); @endphp
                        @foreach($diags as $idx => $diag)
                        <div class="diagnosis-row grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100 relative">
                            <div class="xl:col-span-4">
                                <label class="{{ $label }}">ICD-10 Code</label>
                                <select name="diagnoses[{{ $idx }}][code]" required class="{{ $input }}">
                                    <option value="">Select code...</option>
                                    @foreach($icd10Codes->groupBy('category') as $category => $codesInCategory)
                                        <optgroup label="{{ $category ?: 'Other' }}">
                                            @foreach($codesInCategory as $code)
                                                <option value="{{ $code->code }}" @selected(($diag['code'] ?? $diag['icd10_code'] ?? '') === $code->code)>{{ $code->code }} - {{ $code->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-4">
                                <label class="{{ $label }}">Clinical Details</label>
                                <input type="text" name="diagnoses[{{ $idx }}][description]" value="{{ $diag['description'] ?? '' }}" required class="{{ $input }}" placeholder="e.g. Severe with vomiting">
                            </div>
                            <div class="xl:col-span-2">
                                <label class="{{ $label }}">Type</label>
                                <select name="diagnoses[{{ $idx }}][type]" onchange="enforceSinglePrimary(this)" class="{{ $input }} diagnosis-type">
                                    <option value="primary" @selected(($diag['type'] ?? 'primary') === 'primary')>Primary</option>
                                    <option value="secondary" @selected(($diag['type'] ?? '') === 'secondary')>Secondary</option>
                                    <option value="comorbidity" @selected(($diag['type'] ?? '') === 'comorbidity')>Comorbidity</option>
                                    <option value="differential" @selected(($diag['type'] ?? '') === 'differential')>Differential</option>
                                </select>
                            </div>
                            <div class="xl:col-span-2">
                                <label class="{{ $label }}">Certainty</label>
                                <select name="diagnoses[{{ $idx }}][certainty]" class="{{ $input }}">
                                    <option value="confirmed" @selected(($diag['certainty'] ?? 'confirmed') === 'confirmed')>Confirmed</option>
                                    <option value="probable" @selected(($diag['certainty'] ?? '') === 'probable')>Probable</option>
                                    <option value="possible" @selected(($diag['certainty'] ?? '') === 'possible')>Possible</option>
                                    <option value="rule-out" @selected(($diag['certainty'] ?? '') === 'rule-out')>Rule-out</option>
                                </select>
                            </div>
                            @if($idx > 0)
                            <button type="button" onclick="this.closest('.diagnosis-row').remove()" class="absolute -top-2 -right-2 bg-white border border-slate-200 text-slate-400 rounded-full p-1 hover:text-red-500 shadow-sm">&times;</button>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <button type="button" onclick="addDiagnosisRow()" class="mt-4 text-xs font-bold text-brand-600 uppercase tracking-widest flex items-center gap-1.5 hover:text-brand-800 transition-colors">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Diagnosis
                    </button>
                </div>

                <!-- Prescription Plan -->
                <div class="{{ $card }}">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight flex items-center gap-2">
                            <i data-lucide="pill" class="w-4 h-4 text-brand-600"></i>
                            Treatment Plan (Prescriptions)
                        </h2>
                        <span class="text-[10px] font-black text-brand-600 bg-brand-50 px-2 py-1 rounded-md uppercase tracking-tighter">Pharmacy Direct</span>
                    </div>

                    <div id="prescription-container" class="space-y-4"></div>

                    <button type="button" onclick="addPrescriptionRow()" class="mt-4 text-xs font-bold text-brand-600 uppercase tracking-widest flex items-center gap-1.5 hover:text-brand-800 transition-colors">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Medication
                    </button>
                </div>

                <!-- Disposition / Outcome -->
                <div class="{{ $card }}">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Final Disposition</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                        @foreach(['discharge' => 'Discharge Home', 'admit' => 'Admit to Ward', 'refer' => 'External Referral', 'dama' => 'Left Against Advice', 'deceased' => 'Died in Care'] as $val => $title)
                        <label class="block">
                            <input type="radio" name="outcome" value="{{ $val }}" @checked(old('outcome') === $val) class="peer sr-only">
                            <span class="{{ $opt }}">
                                <span class="block text-sm font-bold text-slate-900">{{ $title }}</span>
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <div class="mt-4 max-w-xs">
                        <label class="{{ $label }}">Follow-up date (optional)</label>
                        <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" class="{{ $input }}">
                    </div>
                </div>

                <!-- Full disposition set, including the two that carry legal weight -->
                <div class="{{ $card }}" id="dispositionDetail">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">If leaving against advice, or died in care</h2>

                    <div class="space-y-6">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Discharge against medical advice</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="{{ $label }}">Risks explained to the patient</label>
                                    <textarea name="dama_risks_explained" rows="2" class="{{ $input }}" placeholder="What were they told could happen?">{{ old('dama_risks_explained') }}</textarea>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $label }}">Witness name</label>
                                        <input type="text" name="dama_witness_name" value="{{ old('dama_witness_name') }}" class="{{ $input }}">
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Witness relationship</label>
                                        <input type="text" name="dama_witness_relationship" value="{{ old('dama_witness_relationship') }}" class="{{ $input }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="{{ $label }}">Reason the patient gave</label>
                                    <input type="text" name="dama_reason_given" value="{{ old('dama_reason_given') }}" class="{{ $input }}">
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="hidden" name="dama_patient_has_capacity" value="0">
                                        <input type="checkbox" name="dama_patient_has_capacity" value="1" @checked(old('dama_patient_has_capacity', true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        <span class="text-sm font-medium text-slate-700">Patient has capacity</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="dama_patient_signature_obtained" value="1" @checked(old('dama_patient_signature_obtained')) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        <span class="text-sm font-medium text-slate-700">Signature obtained</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Death in care</p>
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $label }}">Time of death</label>
                                        <input type="datetime-local" name="death_died_at" value="{{ old('death_died_at') }}" class="{{ $input }}">
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Place of death</label>
                                        <input type="text" name="death_place" value="{{ old('death_place') }}" class="{{ $input }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="{{ $label }}">Immediate cause</label>
                                    <input type="text" name="death_immediate_cause" value="{{ old('death_immediate_cause') }}" class="{{ $input }}">
                                </div>
                                <div>
                                    <label class="{{ $label }}">Underlying cause</label>
                                    <input type="text" name="death_underlying_cause" value="{{ old('death_underlying_cause') }}" class="{{ $input }}">
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $label }}">Manner</label>
                                        <select name="death_manner" class="{{ $input }}">
                                            <option value="">Select...</option>
                                            @foreach(['natural', 'accident', 'suicide', 'homicide', 'undetermined'] as $manner)
                                                <option value="{{ $manner }}" @selected(old('death_manner') === $manner)>{{ ucfirst($manner) }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-slate-500 mt-1.5">Anything other than natural is referred to the coroner automatically.</p>
                                    </div>
                                    <div>
                                        <label class="{{ $label }}">Certificate number</label>
                                        <input type="text" name="death_certificate_number" value="{{ old('death_certificate_number') }}" class="{{ $input }}">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="death_autopsy_requested" value="1" @checked(old('death_autopsy_requested')) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm font-medium text-slate-700">Autopsy requested</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Bar -->
                <div class="fixed bottom-8 left-1/2 -translate-x-1/2 max-w-2xl w-full px-6 z-40">
                    <div class="bg-white/80 backdrop-blur-xl border border-slate-200 p-2 rounded-[2.5rem] shadow-2xl flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4 pl-4">
                            <div class="flex items-center gap-2">
                                <div id="heartbeat-indicator" class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                                <span id="save-status" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    {{ $consultation->autosaved_at ? 'Saved '.$consultation->autosaved_at->format('H:i') : 'Active Editing' }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="save_as_draft" value="1" formnovalidate
                                    class="px-6 py-3.5 bg-slate-100 text-slate-600 rounded-[2rem] text-sm font-bold hover:bg-slate-200 transition-all">
                                Save Draft
                            </button>
                            <button type="submit" class="px-10 py-3.5 bg-brand-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-brand-500/20 hover:bg-brand-700 transition-all transform active:scale-95">
                                Sign Consultation
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Investigations: separate forms, so they cannot be nested in the consultation form above -->
            <div class="space-y-6 mt-6">
                @if(auth()->user()->hasPermission('order_lab_tests'))
                <form action="{{ route('consultations.labs.store', $visit) }}" method="POST"
                      class="{{ $card }}" data-saves-draft-first>
                    @csrf
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight flex items-center gap-2">
                            <i data-lucide="flask-conical" class="w-4 h-4 text-purple-600"></i>
                            Request Laboratory Tests
                        </h2>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sends the patient to the lab</span>
                    </div>

                    @if($labTests->isEmpty())
                        <p class="text-sm text-slate-400 italic">No laboratory tests are set up on the formulary.</p>
                    @else
                        <div class="space-y-5 max-h-72 overflow-y-auto pr-1">
                            @foreach($labTests->groupBy('category') as $category => $testsInCategory)
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">{{ $category ?: 'General' }}</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
                                        @foreach($testsInCategory as $test)
                                            <label class="flex items-start gap-2 p-2.5 rounded-xl border border-slate-100 bg-slate-50 cursor-pointer hover:bg-white transition">
                                                <input type="checkbox" name="lab_tests[]" value="{{ $test->id }}"
                                                       class="mt-0.5 rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium text-slate-900 truncate">{{ $test->name }}</span>
                                                    <span class="block text-[10px] text-slate-500">{{ $test->specimen_type }} &bull; GHS {{ number_format((float) $test->price, 2) }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
                            <div>
                                <label class="{{ $label }}">Priority</label>
                                <select name="priority" class="{{ $input }}">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="{{ $label }}">Clinical indication</label>
                                <input type="text" name="clinical_indication" class="{{ $input }}" placeholder="What question are you asking the lab?">
                            </div>
                        </div>

                        <button type="submit" class="mt-5 px-8 py-3 bg-purple-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-purple-500/20 hover:bg-purple-700 transition-all">
                            Send request to laboratory
                        </button>
                        <p class="text-xs text-slate-500 mt-2">Your notes are saved as a draft first, so nothing is lost when the patient goes to the lab.</p>
                    @endif
                </form>
                @endif

                @if(auth()->user()->hasPermission('order_imaging'))
                <form action="{{ route('radiology.store', $visit) }}" method="POST"
                      class="{{ $card }}" data-saves-draft-first>
                    @csrf
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4 flex items-center gap-2">
                        <i data-lucide="scan" class="w-4 h-4 text-indigo-600"></i>
                        Request Imaging
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div>
                            <label class="{{ $label }}">Modality</label>
                            <select name="modality" required class="{{ $input }}">
                                @foreach(\App\Models\RadiologyOrder::MODALITIES as $modality)
                                    <option value="{{ $modality }}">{{ strtoupper($modality) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Body part</label>
                            <input type="text" name="body_part" class="{{ $input }}" placeholder="e.g. Chest">
                        </div>
                        <div>
                            <label class="{{ $label }}">Priority</label>
                            <select name="priority" class="{{ $input }}">
                                @foreach(\App\Models\RadiologyOrder::PRIORITIES as $priority)
                                    <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end pb-2.5">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="contrast_required" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-medium text-slate-700">Contrast</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="{{ $label }}">Clinical indication</label>
                        <input type="text" name="clinical_indication" class="{{ $input }}" placeholder="What are you looking for?">
                    </div>

                    <button type="submit" class="mt-5 px-8 py-3 bg-indigo-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-indigo-500/20 hover:bg-indigo-700 transition-all">
                        Send request to radiology
                    </button>
                </form>
                @endif
            </div>
            @endif
        </main>
    </div>
</div>

@unless($isSigned)
<script>
const drugList = {!! Js::from($drugs->map->only(['id', 'name', 'generic_name', 'strength'])) !!};

// Conflicts are computed by the server's allergy engine, so the warning shown
// here is the same one that will gate the save.
const allergyConflicts = {!! Js::from($allergyConflicts) !!};

const justificationMinLength = {{ \App\Services\AllergyEngine::JUSTIFICATION_MIN_LENGTH }};
const existingItems = {!! Js::from($existingItems->map(fn ($item) => [
    'drug_id' => $item->drug_id,
    'chronic_medication_id' => $item->chronic_medication_id,
    'dose' => $item->dose,
    'route' => $item->route,
    'frequency' => $item->frequency,
    'duration' => $item->duration_days,
    'quantity' => $item->quantity_to_dispense,
])) !!};

let diagnosisCount = {{ count(old('diagnoses', $consultation->diagnoses->toArray() ?: [[]])) }};
let prescriptionCount = 0;

/**
 * Only one diagnosis may be primary; downgrade any other row that already was.
 */
function enforceSinglePrimary(changed) {
    if (changed.value !== 'primary') return;

    document.querySelectorAll('.diagnosis-type').forEach(select => {
        if (select !== changed && select.value === 'primary') {
            select.value = 'secondary';
        }
    });
}

function addDiagnosisRow() {
    const container = document.getElementById('diagnosis-container');
    const idx = diagnosisCount++;
    const row = document.createElement('div');
    row.className = 'diagnosis-row grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-100 relative';
    row.innerHTML = `
        <div class="xl:col-span-4">
            <label class="{!! $label !!}">ICD-10 Code</label>
            <select name="diagnoses[${idx}][code]" required class="{!! $input !!}">
                ${document.querySelector('select[name="diagnoses[0][code]"]').innerHTML}
            </select>
        </div>
        <div class="xl:col-span-4">
            <label class="{!! $label !!}">Clinical Details</label>
            <input type="text" name="diagnoses[${idx}][description]" required class="{!! $input !!}" placeholder="Details...">
        </div>
        <div class="xl:col-span-2">
            <label class="{!! $label !!}">Type</label>
            <select name="diagnoses[${idx}][type]" onchange="enforceSinglePrimary(this)" class="{!! $input !!} diagnosis-type">
                <option value="secondary">Secondary</option>
                <option value="primary">Primary</option>
                <option value="comorbidity">Comorbidity</option>
                <option value="differential">Differential</option>
            </select>
        </div>
        <div class="xl:col-span-2">
            <label class="{!! $label !!}">Certainty</label>
            <select name="diagnoses[${idx}][certainty]" class="{!! $input !!}">
                <option value="confirmed">Confirmed</option>
                <option value="probable">Probable</option>
                <option value="possible">Possible</option>
                <option value="rule-out">Rule-out</option>
            </select>
        </div>
        <button type="button" onclick="this.closest('.diagnosis-row').remove()" class="absolute -top-2 -right-2 bg-white border border-slate-200 text-slate-400 rounded-full p-1 hover:text-red-500 shadow-sm">&times;</button>
    `;
    container.appendChild(row);
    row.querySelector('select').value = '';
    if (window.lucide) lucide.createIcons();
}

function addPrescriptionRow(data = null) {
    const container = document.getElementById('prescription-container');
    const idx = prescriptionCount++;
    const row = document.createElement('div');
    row.className = 'p-4 bg-slate-50 rounded-2xl border border-slate-100 relative';
    row.id = `presc-${idx}`;
    row.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-3">
            <div class="xl:col-span-4">
                <label class="{!! $label !!}">Medication</label>
                <select name="prescription_items[${idx}][drug_id]" required onchange="checkAllergy(${idx})" class="{!! $input !!}">
                    <option value="">Select Drug...</option>
                    ${drugList.map(d => `<option value="${d.id}" ${data && data.drug_id == d.id ? 'selected' : ''}>${d.name} (${d.strength ?? ''})</option>`).join('')}
                </select>
            </div>
            <div class="xl:col-span-2">
                <label class="{!! $label !!}">Dose</label>
                <input type="text" name="prescription_items[${idx}][dose]" value="${data && data.dose ? data.dose : ''}" required class="{!! $input !!}">
            </div>
            <div class="xl:col-span-2">
                <label class="{!! $label !!}">Frequency</label>
                <input type="text" name="prescription_items[${idx}][frequency]" value="${data && data.frequency ? data.frequency : ''}" required class="{!! $input !!}" placeholder="e.g. BD, TID">
            </div>
            <div class="xl:col-span-2">
                <label class="{!! $label !!}">Days</label>
                <input type="number" name="prescription_items[${idx}][duration]" min="1" value="${data && data.duration ? data.duration : ''}" required class="{!! $input !!}">
            </div>
            <div class="xl:col-span-2">
                <label class="{!! $label !!}">Qty</label>
                <input type="number" name="prescription_items[${idx}][quantity]" min="1" value="${data && data.quantity ? data.quantity : ''}" class="{!! $input !!}" placeholder="Auto">
            </div>
        </div>
        <input type="hidden" name="prescription_items[${idx}][route]" value="${data && data.route ? data.route : 'Oral'}">
        <input type="hidden" name="prescription_items[${idx}][chronic_medication_id]" value="${data && data.chronic_medication_id ? data.chronic_medication_id : ''}">
        ${data && data.continued_name && !data.drug_id ? `<p class="mt-2 text-xs font-semibold text-blue-700">Continuing "${data.continued_name}" — pick the matching formulary entry above.</p>` : ''}
        <div id="allergy-warn-${idx}" class="hidden mt-3 rounded-xl text-xs border p-3"></div>
        <button type="button" onclick="document.getElementById('presc-${idx}').remove()" class="absolute -top-2 -right-2 bg-white border border-slate-200 text-slate-400 rounded-full p-1 hover:text-red-500 shadow-sm">&times;</button>
    `;
    container.appendChild(row);
    checkAllergy(idx);
    if (window.lucide) lucide.createIcons();
}

/**
 * Bring a chronic medication into the plan. A medication recorded as free text
 * has no formulary entry, so the clinician is asked to pick one.
 */
function continueMedication(med) {
    addPrescriptionRow({
        chronic_medication_id: med.id,
        drug_id: med.drug_id,
        continued_name: med.drug_name,
        dose: med.dose,
        route: med.route,
        frequency: med.frequency,
        duration: 30,
        quantity: null,
    });
}

function checkAllergy(idx) {
    const select = document.querySelector(`select[name="prescription_items[${idx}][drug_id]"]`);
    const warn = document.getElementById(`allergy-warn-${idx}`);
    if (!select || !warn) return;

    const conflict = allergyConflicts[select.value];

    if (!conflict) {
        warn.classList.add('hidden');
        warn.innerHTML = '';
        return;
    }

    const critical = conflict.is_critical;
    warn.className = `mt-3 rounded-xl text-xs border p-3 ${critical ? 'bg-red-100 text-red-800 border-red-200' : 'bg-amber-50 text-amber-800 border-amber-200'}`;
    warn.innerHTML = `
        <p class="font-bold">${critical ? 'CRITICAL' : 'Caution'}: ${conflict.severity} allergy to ${conflict.allergen} (${conflict.match_type} match)${conflict.reaction ? ' — ' + conflict.reaction : ''}</p>
        ${critical ? `
            <label class="block mt-2 font-bold uppercase tracking-wider text-[10px]">Clinical justification to proceed (min ${justificationMinLength} characters)</label>
            <textarea name="allergy_justifications[${conflict.drug_id}]" rows="2" minlength="${justificationMinLength}"
                      class="w-full mt-1 px-3 py-2 bg-white border border-red-200 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-red-400"
                      placeholder="Why is this drug still the right choice?"></textarea>
        ` : ''}
    `;
    warn.classList.remove('hidden');
}

// Keep the editing lock warm. The interval sits well inside the lock timeout so
// one dropped request does not surrender the case.
setInterval(() => {
    fetch("{{ route('consultations.heartbeat', $consultation) }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
        const indicator = document.getElementById('heartbeat-indicator');
        if (ok && data.status === 'success') {
            indicator.className = 'w-2 h-2 rounded-full bg-green-500 animate-pulse';
        } else {
            indicator.className = 'w-2 h-2 rounded-full bg-red-500';
            document.getElementById('save-status').innerText = 'Lock lost';
            alert('Lock lost. This case is now being edited by ' + (data.locked_by ?? 'another clinician') + '.');
        }
    })
    .catch(() => {});
}, {{ Consultation::HEARTBEAT_SECONDS * 1000 }});

// Server-side draft storage, so an interrupted consultation is never lost.
setInterval(() => {
    const form = document.getElementById('consultationForm');
    if (!form) return;

    const body = new FormData(form);
    body.delete('outcome');

    fetch("{{ route('consultations.autosave', $consultation) }}", {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body,
    })
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
        const status = document.getElementById('save-status');
        if (ok && data.status === 'saved') {
            status.innerText = 'Draft saved ' + new Date().toTimeString().slice(0, 5);
        } else if (data.status === 'locked') {
            status.innerText = 'Not saved — lock lost';
        }
    })
    .catch(() => {});
}, {{ Consultation::AUTOSAVE_SECONDS * 1000 }});

/**
 * Ordering an investigation posts a different form and navigates away, which
 * would otherwise discard whatever the clinician has typed but not yet saved.
 * Autosave the draft first, then let the request go through.
 */
document.querySelectorAll('form[data-saves-draft-first]').forEach(form => {
    form.addEventListener('submit', async event => {
        if (form.dataset.draftSaved === 'yes') {
            return;
        }

        event.preventDefault();

        const consultationForm = document.getElementById('consultationForm');

        if (consultationForm) {
            const body = new FormData(consultationForm);
            body.delete('outcome');

            try {
                const response = await fetch("{{ route('consultations.autosave', $consultation) }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body,
                });

                if (response.status === 409) {
                    const data = await response.json();
                    alert('Your notes were not saved: ' + (data.message ?? 'another clinician holds the lock.')
                        + ' The request was not sent.');
                    return;
                }
            } catch (error) {
                // A failed autosave must not stop an urgent investigation being ordered.
                console.error('Draft autosave failed before ordering', error);
            }
        }

        form.dataset.draftSaved = 'yes';
        form.submit();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    if (existingItems.length > 0) {
        existingItems.forEach(item => addPrescriptionRow(item));
    } else {
        addPrescriptionRow();
    }
});
</script>
@endunless
@endsection
