@extends('layouts.app')

@section('title', 'Patient Profile')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-5">
            <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 flex items-center justify-center text-white text-2xl font-black border border-emerald-500/30">
                {{ substr($patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-100 tracking-tight">{{ $patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-400">{{ $patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-400">{{ $patient->sex }} &bull; {{ $patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('patients.edit', $patient) }}" class="bg-slate-800 text-slate-200 px-5 py-2.5 rounded-lg font-semibold text-sm border border-slate-700 hover:bg-slate-700 transition-all flex items-center">
                <i data-lucide="pencil" class="w-4 h-4 mr-2"></i>
                Edit details
            </a>
            @if(auth()->user()->hasPermission('register_patients'))
                <a href="{{ route('visits.create', $patient) }}" class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all flex items-center">
                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                    Start new visit
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Identity & Social -->
        <div class="space-y-6">
            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <h3 class="text-sm font-semibold text-slate-400 mb-5">Contact details</h3>

                <div class="space-y-5">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Phone number</p>
                        <p class="text-sm font-semibold text-slate-100">{{ $patient->phone_number ?? 'No contact recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Address</p>
                        <p class="text-sm font-semibold text-slate-100 leading-relaxed">{{ $patient->address ?? 'Not recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">Next of kin</p>
                        <p class="text-sm font-semibold text-slate-100">{{ $patient->next_of_kin_name ?? 'Not recorded' }}</p>
                        @if($patient->next_of_kin_contact)
                            <p class="text-xs font-medium text-slate-400 mt-0.5">{{ $patient->next_of_kin_contact }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <h3 class="text-sm font-semibold text-slate-400 mb-5">Insurance</h3>
                <div class="p-4 rounded-lg @if($patient->payment_method == 'nhis') bg-blue-950/40 border border-blue-800 @else bg-slate-950 border border-slate-800 @endif">
                    <p class="text-xs font-medium text-slate-500 mb-1">Method</p>
                    <p class="text-lg font-bold text-slate-100">{{ ucfirst($patient->payment_method) }}</p>
                    @if($patient->nhis_number)
                        <div class="mt-3 pt-3 border-t border-blue-900/50">
                            <p class="text-xs font-medium text-slate-500 mb-1">ID number</p>
                            <p class="text-sm font-semibold text-slate-100">{{ $patient->nhis_number }}</p>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs font-medium text-slate-500 mb-1">Expiry date</p>
                            <p class="text-sm font-semibold @if($patient->nhis_expiry && $patient->nhis_expiry->isPast()) text-rose-400 @else text-slate-100 @endif">
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
            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-base font-bold text-slate-100">Important to know</h3>
                    @if(auth()->user()->hasPermission('update_clinical_safety'))
                        <button onclick="openClinicalModal()" class="text-sm font-semibold text-blue-400 hover:underline">Update</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-5 rounded-lg bg-rose-950/40 border border-rose-800">
                        <div class="flex items-center text-rose-400 mb-3">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Allergies</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($patient->allergies ?? [] as $allergy)
                                <span class="px-3 py-1 bg-slate-950 text-rose-300 text-xs font-semibold rounded-lg border border-rose-900">{{ $allergy }}</span>
                            @empty
                                <span class="text-sm text-rose-500 italic">No known allergies</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="p-5 rounded-lg bg-amber-950/40 border border-amber-800">
                        <div class="flex items-center text-amber-400 mb-3">
                            <i data-lucide="heart-pulse" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Chronic conditions</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($patient->chronic_conditions ?? [] as $condition)
                                <span class="px-3 py-1 bg-slate-950 text-amber-300 text-xs font-semibold rounded-lg border border-amber-900">{{ $condition }}</span>
                            @empty
                                <span class="text-sm text-amber-500 italic">None recorded</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="md:col-span-2 p-5 rounded-lg bg-blue-950/40 border border-blue-800 flex items-center justify-between">
                        <div class="flex items-center text-blue-400">
                            <i data-lucide="droplet" class="w-4 h-4 mr-2"></i>
                            <span class="text-sm font-semibold">Blood group</span>
                        </div>
                        <span class="text-xl font-bold text-blue-300">{{ $patient->blood_group ?? 'Not tested' }}</span>
                    </div>
                </div>
            </div>

            <!-- Identity Audit Logs -->
            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <h3 class="text-base font-bold text-slate-100 mb-5">Changes to this record</h3>
                <div class="space-y-3">
                    @forelse($patient->identityLogs()->with('user')->latest()->get() as $log)
                        <div class="flex items-start p-4 bg-slate-950 rounded-lg border border-slate-800">
                            <div class="w-8 h-8 bg-blue-950 border border-blue-800 rounded-lg flex items-center justify-center text-blue-400 mr-4 mt-1">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-100">Updated {{ str_replace('_', ' ', $log->field_name) }}</p>
                                <p class="text-xs text-slate-400 font-medium">Changed from <span class="text-slate-200">{{ $log->old_value ?? 'nothing' }}</span> to <span class="text-slate-200">{{ $log->new_value ?? 'nothing' }}</span></p>
                                <p class="text-xs text-slate-500 mt-1">By {{ $log->user->name }} &bull; {{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-6 text-slate-500 text-sm italic">No changes have been made to this record yet</p>
                    @endforelse
                </div>
            </div>

            <!-- Visit History -->
            <div class="bg-slate-900 p-6 rounded-lg border border-slate-800">
                <h3 class="text-base font-bold text-slate-100 mb-5">Recent visits</h3>
                <div class="space-y-3">
                    @forelse($patient->visits()->latest()->limit(5)->get() as $visit)
                        <div class="flex items-center justify-between p-4 bg-slate-950 rounded-lg border border-slate-800 hover:border-emerald-800 transition-all group">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-slate-900 rounded-lg flex items-center justify-center text-emerald-400 border border-slate-800 mr-4">
                                    <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">{{ $visit->type }}</p>
                                    <p class="text-xs text-slate-500 font-medium">{{ $visit->visit_number }} &bull; {{ $visit->created_at->format('d M, Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($visit->priority == 'emergency') bg-rose-950 text-rose-400 border border-rose-800
                                    @elseif($visit->priority == 'urgent') bg-amber-950 text-amber-400 border border-amber-800
                                    @else bg-blue-950 text-blue-400 border border-blue-800 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                                <span class="px-3 py-1 text-xs font-semibold bg-slate-800 text-slate-400 rounded-full">{{ ucfirst(str_replace('_', ' ', $visit->status)) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                            <i data-lucide="clock" class="w-10 h-10 mb-4"></i>
                            <p class="text-sm font-medium">No visits recorded yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="bg-slate-900/50 border-2 border-dashed border-slate-800 rounded-lg p-20 flex flex-col items-center justify-center text-center">
                <i data-lucide="lock" class="w-10 h-10 text-slate-600 mb-4"></i>
                <h3 class="text-base font-semibold text-slate-400">Clinical details are restricted</h3>
                <p class="text-sm text-slate-500 mt-2 max-w-xs">Full clinical history and safety records are only visible to medical staff.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Clinical Data Modal -->
<div id="clinicalModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center transition-all duration-300">
    <div class="bg-slate-900 border border-slate-800 p-8 rounded-lg shadow-2xl w-[32rem] transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-100">Update clinical details</h3>
            <button onclick="closeClinicalModal()" class="text-slate-500 hover:text-slate-300 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="{{ route('patients.clinical.update', $patient) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Known allergies</label>
                    <textarea name="allergies" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. Penicillin, Peanuts">{{ implode(', ', $patient->allergies ?? []) }}</textarea>
                    <p class="text-xs text-slate-500 mt-2 ml-1">Separate multiple allergies with commas.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Chronic conditions</label>
                    <textarea name="chronic_conditions" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="e.g. Diabetes Type 2, Hypertension">{{ implode(', ', $patient->chronic_conditions ?? []) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2 ml-1">Blood group</label>
                    <select name="blood_group" class="w-full px-4 py-3 bg-slate-950 border border-slate-700 rounded-lg text-slate-100 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">Select a group</option>
                        @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group)
                            <option value="{{ $group }}" @if($patient->blood_group == $group) selected @endif>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col space-y-3 mt-8">
                <button type="submit" class="w-full py-3 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-500 shadow-lg shadow-emerald-950/50 transition-all">
                    Save changes
                </button>
                <button type="button" onclick="closeClinicalModal()" class="w-full py-3 text-slate-400 font-semibold hover:bg-slate-800 rounded-lg transition-all">
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
</script>
@endsection
