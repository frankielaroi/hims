@extends('layouts.app')

@section('title', 'Patient Profile')

@section('content')
<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-20 w-20 rounded-3xl bg-teal-600 flex items-center justify-center text-white text-3xl font-black shadow-lg shadow-teal-100">
                {{ substr($patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">{{ $patient->sex }} • {{ $patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('patients.edit', $patient) }}" class="bg-white text-slate-700 px-6 py-3 rounded-2xl font-semibold border border-slate-200 hover:bg-slate-50 transition-all flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Edit details
            </a>
            @if(auth()->user()->hasPermission('register_patients'))
                <a href="{{ route('visits.create', $patient) }}" class="bg-teal-600 text-white px-6 py-3 rounded-2xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Start new visit
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Identity & Social -->
        <div class="space-y-8">
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Contact details</h3>

                <div class="space-y-6">
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">Phone number</p>
                        <p class="text-sm font-semibold text-slate-900">{{ $patient->phone_number ?? 'No contact recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">Address</p>
                        <p class="text-sm font-semibold text-slate-900 leading-relaxed">{{ $patient->address ?? 'Not recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-400 mb-1">Next of kin</p>
                        <p class="text-sm font-semibold text-slate-900">{{ $patient->next_of_kin_name ?? 'Not recorded' }}</p>
                        @if($patient->next_of_kin_contact)
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $patient->next_of_kin_contact }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Insurance</h3>
                <div class="p-4 rounded-2xl @if($patient->payment_method == 'nhis') bg-blue-50 border border-blue-100 @else bg-slate-50 border border-slate-100 @endif">
                    <p class="text-xs font-medium text-slate-500 mb-1">Method</p>
                    <p class="text-lg font-bold text-slate-900">{{ ucfirst($patient->payment_method) }}</p>
                    @if($patient->nhis_number)
                        <div class="mt-3 pt-3 border-t border-blue-100/50">
                            <p class="text-xs font-medium text-slate-500 mb-1">ID number</p>
                            <p class="text-sm font-semibold text-slate-900">{{ $patient->nhis_number }}</p>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs font-medium text-slate-500 mb-1">Expiry date</p>
                            <p class="text-sm font-semibold @if($patient->nhis_expiry && $patient->nhis_expiry->isPast()) text-red-600 @else text-slate-900 @endif">
                                {{ $patient->nhis_expiry ? $patient->nhis_expiry->format('d M, Y') : 'Unknown' }}
                                @if($patient->nhis_expiry && $patient->nhis_expiry->isPast()) &mdash; expired @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Clinical Alerts (Central Truth) -->
        <div class="lg:col-span-2 space-y-8">
            @if(auth()->user()->hasPermission('view_clinical_history'))
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-lg font-bold text-slate-900">Important to know</h3>
                    @if(auth()->user()->hasPermission('update_clinical_safety'))
                        <button onclick="openClinicalModal()" class="text-sm font-semibold text-blue-600 hover:underline">Update</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-6 rounded-2xl bg-red-50 border border-red-100">
                        <div class="flex items-center text-red-600 mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <span class="text-sm font-semibold">Allergies</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($patient->allergies ?? [] as $allergy)
                                <span class="px-3 py-1 bg-white text-red-700 text-xs font-semibold rounded-lg border border-red-100">{{ $allergy }}</span>
                            @empty
                                <span class="text-sm text-red-400 italic">No known allergies</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="p-6 rounded-2xl bg-orange-50 border border-orange-100">
                        <div class="flex items-center text-orange-600 mb-4">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1.01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span class="text-sm font-semibold">Chronic conditions</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($patient->chronic_conditions ?? [] as $condition)
                                <span class="px-3 py-1 bg-white text-orange-700 text-xs font-semibold rounded-lg border border-orange-100">{{ $condition }}</span>
                            @empty
                                <span class="text-sm text-orange-400 italic">None recorded</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="md:col-span-2 p-6 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-between">
                        <div class="flex items-center text-blue-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.212 1.212 3.18 1.212 4.392 0z"></path></svg>
                            <span class="text-sm font-semibold">Blood group</span>
                        </div>
                        <span class="text-xl font-bold text-blue-900">{{ $patient->blood_group ?? 'Not tested' }}</span>
                    </div>
                </div>
            </div>

            <!-- Identity Audit Logs -->
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 mb-6">Changes to this record</h3>
                <div class="space-y-4">
                    @forelse($patient->identityLogs()->with('user')->latest()->get() as $log)
                        <div class="flex items-start p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 mr-4 mt-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-900">Updated {{ str_replace('_', ' ', $log->field_name) }}</p>
                                <p class="text-xs text-slate-500 font-medium">Changed from <span class="text-slate-800">{{ $log->old_value ?? 'nothing' }}</span> to <span class="text-slate-800">{{ $log->new_value ?? 'nothing' }}</span></p>
                                <p class="text-xs text-slate-400 mt-1">By {{ $log->user->name }} &bull; {{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-6 text-slate-400 text-sm italic">No changes have been made to this record yet</p>
                    @endforelse
                </div>
            </div>

            <!-- Visit History -->
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-900 mb-6">Recent visits</h3>
                <div class="space-y-4">
                    @forelse($patient->visits()->latest()->limit(5)->get() as $visit)
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:border-teal-200 transition-all group">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-teal-600 shadow-sm mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $visit->type }}</p>
                                    <p class="text-xs text-slate-500 font-medium">{{ $visit->visit_number }} &bull; {{ $visit->created_at->format('d M, Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    @if($visit->priority == 'emergency') bg-red-100 text-red-700
                                    @elseif($visit->priority == 'urgent') bg-orange-100 text-orange-700
                                    @else bg-blue-100 text-blue-700 @endif">
                                    {{ ucfirst($visit->priority) }}
                                </span>
                                <span class="px-3 py-1 text-xs font-semibold bg-slate-200 text-slate-600 rounded-full">{{ ucfirst(str_replace('_', ' ', $visit->status)) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                            <svg class="w-12 h-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <p class="text-sm font-medium">No visits recorded yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @else
            <div class="bg-white/50 border-2 border-dashed border-slate-200 rounded-[2rem] p-20 flex flex-col items-center justify-center text-center">
                <svg class="w-12 h-12 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                <h3 class="text-base font-semibold text-slate-500">Clinical details are restricted</h3>
                <p class="text-sm text-slate-400 mt-2 max-w-xs">Full clinical history and safety records are only visible to medical staff.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Clinical Data Modal -->
<div id="clinicalModal" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center transition-all duration-300">
    <div class="bg-white p-8 rounded-[2rem] shadow-2xl w-[32rem] transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-bold text-slate-900">Update clinical details</h3>
            <button onclick="closeClinicalModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form action="{{ route('patients.clinical.update', $patient) }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Known allergies</label>
                    <textarea name="allergies" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. Penicillin, Peanuts">{{ implode(', ', $patient->allergies ?? []) }}</textarea>
                    <p class="text-xs text-slate-400 mt-2 ml-1">Separate multiple allergies with commas.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Chronic conditions</label>
                    <textarea name="chronic_conditions" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500" placeholder="e.g. Diabetes Type 2, Hypertension">{{ implode(', ', $patient->chronic_conditions ?? []) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Blood group</label>
                    <select name="blood_group" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-teal-500">
                        <option value="">Select a group</option>
                        @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $group)
                            <option value="{{ $group }}" @if($patient->blood_group == $group) selected @endif>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col space-y-3 mt-10">
                <button type="submit" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-semibold hover:bg-teal-700 shadow-lg shadow-teal-100 transition-all">
                    Save changes
                </button>
                <button type="button" onclick="closeClinicalModal()" class="w-full py-4 text-slate-500 font-semibold hover:bg-slate-50 rounded-2xl transition-all">
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
