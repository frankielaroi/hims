@extends('layouts.app')

@section('title', 'Patient Profile')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center space-x-5">
            <div class="h-16 w-16 rounded-2xl bg-brand-50 flex items-center justify-center text-brand-700 text-2xl font-semibold">
                {{ strtoupper(substr($patient->full_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">{{ $patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm text-slate-500">{{ $patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm text-slate-500">{{ $patient->sex }} &bull; {{ $patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('patients.edit', $patient) }}" class="bg-white text-slate-700 px-4 py-2.5 rounded-lg font-medium text-sm border border-slate-300 hover:bg-slate-50 transition-colors flex items-center">
                <i data-lucide="pencil" class="w-4 h-4 mr-2 text-slate-400"></i>
                Edit details
            </a>
            @if(auth()->user()->hasPermission('register_patients'))
                <a href="{{ route('visits.create', $patient) }}" class="bg-brand-600 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-brand-700 transition-colors flex items-center">
                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                    Start new visit
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Identity & Social -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900 mb-5">Contact details</h3>

                <div class="space-y-5">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Phone number</p>
                        <p class="text-sm font-medium text-slate-900">{{ $patient->phone_number ?? 'No contact recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Address</p>
                        <p class="text-sm font-medium text-slate-900 leading-relaxed">{{ $patient->address ?? 'Not recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 mb-1">Next of kin</p>
                        <p class="text-sm font-medium text-slate-900">{{ $patient->next_of_kin_name ?? 'Not recorded' }}</p>
                        @if($patient->next_of_kin_contact)
                            <p class="text-xs text-slate-500 mt-0.5">{{ $patient->next_of_kin_contact }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900 mb-5">Insurance</h3>
                <div class="p-4 rounded-lg @if($patient->payment_method == 'nhis') bg-brand-50 border border-brand-100 @else bg-slate-50 border border-slate-200 @endif">
                    <p class="text-xs text-slate-500 mb-1">Method</p>
                    <p class="text-lg font-semibold text-slate-900">{{ ucfirst($patient->payment_method) }}</p>
                    @if($patient->nhis_number)
                        <div class="mt-3 pt-3 border-t border-brand-100">
                            <p class="text-xs text-slate-500 mb-1">ID number</p>
                            <p class="text-sm font-medium text-slate-900">{{ $patient->nhis_number }}</p>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs text-slate-500 mb-1">Expiry date</p>
                            <p class="text-sm font-medium @if($patient->nhis_expiry && $patient->nhis_expiry->isPast()) text-red-600 @else text-slate-900 @endif">
                                {{ $patient->nhis_expiry ? $patient->nhis_expiry->format('d M, Y') : 'Unknown' }}
                                @if($patient->nhis_expiry && $patient->nhis_expiry->isPast()) &mdash; expired @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Clinical Alerts (Central Truth) -->
        <div class="lg:col-span-2 space-y-6">
            @if(auth()->user()->hasPermission('view_clinical_history'))
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-base font-semibold text-slate-900">Important to know</h3>
                    @if(auth()->user()->hasPermission('update_clinical_safety'))
                        <button onclick="openClinicalModal()" class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">Update</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-5 rounded-lg bg-red-50 border border-red-100">
                        <div class="flex items-center text-red-700 mb-3">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Allergies</span>
                        </div>
                        <div class="space-y-2">
                            @forelse($patient->activeAllergies as $allergy)
                                <div class="flex items-center justify-between gap-2 px-3 py-1.5 bg-white rounded-lg border border-red-200">
                                    <span class="text-xs font-semibold text-red-700">{{ $allergy->allergen }}</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $allergy->isCritical() ? 'text-red-700' : 'text-red-500/80' }}">
                                        {{ $allergy->severity }}@if($allergy->reaction) &middot; {{ $allergy->reaction }}@endif
                                    </span>
                                </div>
                            @empty
                                <span class="text-sm text-red-600/80">No known allergies</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="p-5 rounded-lg bg-amber-50 border border-amber-100">
                        <div class="flex items-center text-amber-700 mb-3">
                            <i data-lucide="heart-pulse" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Chronic conditions</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($patient->chronic_conditions ?? [] as $condition)
                                <span class="px-3 py-1 bg-white text-amber-700 text-xs font-medium rounded-full border border-amber-200">{{ $condition }}</span>
                            @empty
                                <span class="text-sm text-amber-700/80">None recorded</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="md:col-span-2 p-5 rounded-lg bg-brand-50 border border-brand-100 flex items-center justify-between">
                        <div class="flex items-center text-brand-700">
                            <i data-lucide="droplet" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Blood group</span>
                        </div>
                        <span class="text-xl font-semibold text-brand-700">{{ $patient->blood_group ?? 'Not tested' }}</span>
                    </div>
                </div>
            </div>

            <!-- Identity Audit Logs -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900 mb-5">Changes to this record</h3>
                <div class="space-y-3">
                    @forelse($patient->identityLogs()->with('user')->latest()->get() as $log)
                        <div class="flex items-start p-4 bg-slate-50 rounded-lg">
                            <div class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center text-brand-600 mr-4 shrink-0">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-slate-900">Updated {{ str_replace('_', ' ', $log->field_name) }}</p>
                                <p class="text-xs text-slate-500">Changed from <span class="text-slate-800">{{ $log->old_value ?? 'nothing' }}</span> to <span class="text-slate-800">{{ $log->new_value ?? 'nothing' }}</span></p>
                                <p class="text-xs text-slate-400 mt-1">By {{ $log->user->name }} &bull; {{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-6 text-slate-500 text-sm">No changes have been made to this record yet</p>
                    @endforelse
                </div>
            </div>

            <!-- Visit History -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900 mb-5">Recent visits</h3>
                <div class="space-y-3">
                    @forelse($patient->visits()->latest()->limit(5)->get() as $visit)
                        <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-slate-200 hover:border-brand-200 hover:bg-brand-50/40 transition-colors">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-brand-50 rounded-lg flex items-center justify-center text-brand-600 mr-4">
                                    <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $visit->type }}</p>
                                    <p class="text-xs text-slate-500">{{ $visit->visit_number }} &bull; {{ $visit->created_at->format('d M, Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-1 text-xs font-medium rounded-full
                                    @if($visit->priority == 'emergency') bg-red-50 text-red-700 border border-red-100
                                    @elseif($visit->priority == 'urgent') bg-amber-50 text-amber-700 border border-amber-100
                                    @else bg-brand-50 text-brand-700 border border-brand-100 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                                <span class="px-3 py-1 text-xs font-medium bg-slate-100 text-slate-600 rounded-full">{{ ucfirst(str_replace('_', ' ', $visit->status)) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                            <i data-lucide="clock" class="w-8 h-8 mb-3"></i>
                            <p class="text-sm">No visits recorded yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="bg-white border border-dashed border-slate-300 rounded-xl p-20 flex flex-col items-center justify-center text-center">
                <i data-lucide="lock" class="w-8 h-8 text-slate-400 mb-4"></i>
                <h3 class="text-base font-semibold text-slate-700">Clinical details are restricted</h3>
                <p class="text-sm text-slate-500 mt-2 max-w-xs">Full clinical history and safety records are only visible to medical staff.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Clinical Data Modal -->
<div id="clinicalModal" role="dialog" aria-modal="true" aria-labelledby="clinicalModalTitle"
     class="fixed inset-0 z-50 bg-slate-900/40 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 p-8 rounded-2xl shadow-xl w-[32rem] max-w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 id="clinicalModalTitle" class="text-lg font-semibold text-slate-900">Update clinical details</h3>
            <button onclick="closeClinicalModal()" class="text-slate-400 hover:text-slate-700 transition-colors" aria-label="Close">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="{{ route('patients.clinical.update', $patient) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Known allergies</label>
                    <div id="allergy-rows" class="space-y-3">
                        @foreach($patient->activeAllergies as $i => $allergy)
                            <div class="allergy-row grid grid-cols-12 gap-2">
                                <input type="text" name="allergies[{{ $i }}][allergen]" value="{{ $allergy->allergen }}" placeholder="Allergen"
                                       class="col-span-5 px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none">
                                <select name="allergies[{{ $i }}][severity]"
                                        class="col-span-3 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 focus:border-brand-500 focus:outline-none">
                                    @foreach(\App\Models\PatientAllergy::SEVERITIES as $severity)
                                        <option value="{{ $severity }}" @selected($allergy->severity === $severity)>{{ ucfirst($severity) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="allergies[{{ $i }}][reaction]" value="{{ $allergy->reaction }}" placeholder="Reaction"
                                       class="col-span-4 px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none">
                            </div>
                        @endforeach
                    </div>
                    <button type="button" onclick="addAllergyRow()" class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-700">+ Add an allergy</button>
                    <p class="text-xs text-slate-500 mt-1.5">
                        Severe and life-threatening allergies require a written justification before a clinician can prescribe against them.
                        Clearing an allergen marks it inactive; it is never erased.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Chronic conditions</label>
                    <textarea name="chronic_conditions" rows="3" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" placeholder="e.g. Diabetes Type 2, Hypertension">{{ implode(', ', $patient->chronic_conditions ?? []) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Blood group</label>
                    <select name="blood_group" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                        <option value="">Select a group</option>
                        @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group)
                            <option value="{{ $group }}" @if($patient->blood_group == $group) selected @endif>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col space-y-2 mt-8">
                <button type="submit" class="w-full py-2.5 bg-brand-600 text-white rounded-lg text-sm font-semibold hover:bg-brand-700 transition-colors">
                    Save changes
                </button>
                <button type="button" onclick="closeClinicalModal()" class="w-full py-2.5 text-sm text-slate-600 font-medium hover:bg-slate-100 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openClinicalModal() {
    document.getElementById('clinicalModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeClinicalModal() {
    document.getElementById('clinicalModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

let allergyRowCount = {{ $patient->activeAllergies->count() }};

function addAllergyRow() {
    const container = document.getElementById('allergy-rows');
    const idx = allergyRowCount++;
    const row = document.createElement('div');
    row.className = 'allergy-row grid grid-cols-12 gap-2';
    row.innerHTML = `
        <input type="text" name="allergies[${idx}][allergen]" placeholder="Allergen"
               class="col-span-5 px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none">
        <select name="allergies[${idx}][severity]"
                class="col-span-3 px-2 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 focus:border-brand-500 focus:outline-none">
            @foreach(\App\Models\PatientAllergy::SEVERITIES as $severity)
                <option value="{{ $severity }}" @selected($severity === 'unknown')>{{ ucfirst($severity) }}</option>
            @endforeach
        </select>
        <input type="text" name="allergies[${idx}][reaction]" placeholder="Reaction"
               class="col-span-4 px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:outline-none">
    `;
    container.appendChild(row);
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeClinicalModal();
});
</script>
@endsection