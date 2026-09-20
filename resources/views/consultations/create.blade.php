@extends('layouts.app')

@section('title', 'Clinical Consultation')

@section('content')
<div class="space-y-8 pb-20">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-6">
            <div class="h-16 w-16 rounded-2xl bg-blue-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-blue-100">
                {{ substr($visit->patient->full_name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $visit->patient->full_name }}</h1>
                <div class="flex items-center space-x-3 mt-1">
                    <span class="text-sm font-medium text-slate-500">{{ $visit->patient->hospital_id }}</span>
                    <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                    <span class="text-sm font-medium text-slate-500">{{ $visit->patient->sex }} &bull; {{ $visit->patient->age }} years</span>
                </div>
            </div>
        </div>
        <div class="text-right">
            <span class="px-4 py-2 bg-white rounded-xl border border-slate-200 text-sm font-medium text-slate-500 shadow-sm">
                Visit {{ $visit->visit_number }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Patient Clinical History Sidebar -->
        <div class="space-y-8 lg:col-span-1">
            <!-- Vitals Today -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Vitals from triage</h3>
                @if($visit->latestVitals)
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">Temp</span>
                        <span class="text-sm font-bold {{ $visit->latestVitals->temperature > 37.5 ? 'text-red-600' : 'text-slate-900' }}">{{ $visit->latestVitals->temperature }}°C</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">BP</span>
                        <span class="text-sm font-bold text-slate-900">{{ $visit->latestVitals->systolic_bp }}/{{ $visit->latestVitals->diastolic_bp }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">Pulse</span>
                        <span class="text-sm font-bold text-slate-900">{{ $visit->latestVitals->pulse_rate }} bpm</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-slate-500">Weight</span>
                        <span class="text-sm font-bold text-slate-900">{{ $visit->latestVitals->weight }} kg</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-slate-50">
                        <span class="text-sm text-slate-500">BMI</span>
                        <span class="text-sm font-bold text-blue-600">{{ $visit->latestVitals->bmi }}</span>
                    </div>
                </div>
                @else
                    <p class="text-sm text-slate-400 italic">No vitals recorded</p>
                @endif
            </div>

            <!-- Alerts -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Allergies</h3>
                <div class="space-y-3">
                    @forelse($visit->patient->allergies ?? [] as $allergy)
                        <div class="px-3 py-2 bg-red-50 text-red-700 text-sm font-semibold rounded-lg border border-red-100">{{ $allergy }}</div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No known allergies</p>
                    @endforelse
                </div>
            </div>

            <!-- Past Diagnoses -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Past diagnoses</h3>
                <div class="space-y-4">
                    @forelse($pastDiagnoses->take(5) as $diag)
                        <div>
                            <p class="text-sm font-semibold text-slate-900 leading-tight">{{ $diag->description }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $diag->consultation->visit->created_at->format('d M, Y') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No past diagnoses on file</p>
                    @endforelse
                </div>
            </div>

            <!-- Investigations (Lab) -->
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                <h3 class="text-sm font-semibold text-slate-500 mb-6">Investigations</h3>

                @if($visit->labOrders->isNotEmpty())
                <div class="space-y-4 mb-6">
                    @foreach($visit->labOrders as $order)
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-slate-500">{{ $order->created_at->format('d M, Y') }}</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                    @if($order->status === 'verified') bg-green-50 text-green-700
                                    @else bg-slate-200 text-slate-600 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </div>
                            @foreach($order->items as $item)
                                <div class="text-sm mt-2">
                                    <span class="font-semibold text-slate-900">{{ $item->labTest->name }}:</span>
                                    @if($item->performed_at)
                                        <span class="@if($item->result_flag === 'critical') text-red-600 @elseif($item->result_flag === 'abnormal') text-orange-600 @else text-slate-600 @endif">{{ $item->result_value }}</span>
                                    @else
                                        <span class="text-slate-400 italic">awaiting result</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @endif

                @if($visit->pendingLabOrder)
                    <p class="text-sm text-slate-400 italic">Investigation results are still pending from the lab.</p>
                @elseif(auth()->user()->hasPermission('order_lab_tests'))
                    <form action="{{ route('consultations.labs.store', $visit) }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="max-h-48 overflow-y-auto space-y-2 pr-1">
                            @foreach($labTests as $test)
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="lab_tests[]" value="{{ $test->id }}" class="rounded text-purple-600 focus:ring-purple-500">
                                    <span class="text-sm text-slate-700">{{ $test->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <select name="priority" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-purple-500">
                            <option value="routine">Routine</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT (immediate)</option>
                        </select>
                        <textarea name="clinical_indication" rows="2" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-purple-500" placeholder="Why these tests? (optional)"></textarea>
                        <button type="submit" class="w-full py-3 bg-purple-600 text-white rounded-xl font-semibold text-sm hover:bg-purple-700 transition-all">
                            Send to laboratory
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Consultation Workspace -->
        <div class="lg:col-span-3 space-y-8">
            <form action="{{ route('consultations.store', $visit) }}" method="POST" id="consultationForm">
                @csrf

                <!-- Notes -->
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200 mb-8">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6">Examination & history</h3>
                    <textarea name="clinical_notes" rows="8" required class="w-full px-6 py-6 bg-slate-50 border-none rounded-[2rem] text-slate-900 font-medium focus:ring-2 focus:ring-blue-500 shadow-inner leading-relaxed" placeholder="Record patient history, physical examination findings, and clinical reasoning..."></textarea>
                </div>

                <!-- Diagnosis -->
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200 mb-8">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6">Diagnosis (ICD-10)</h3>
                    <div id="diagnosis-container" class="space-y-4">
                        <div class="diagnosis-row grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-3">
                                <select name="diagnoses[0][code]" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select a code</option>
                                    @foreach($icd10Codes as $code)
                                        <option value="{{ $code->code }}">{{ $code->code }} - {{ $code->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-7">
                                <input type="text" name="diagnoses[0][description]" required class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-blue-500" placeholder="Any details or clinical nuance...">
                            </div>
                            <div class="md:col-span-2 flex items-center justify-end">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="diagnoses[0][is_primary]" value="1" checked class="rounded text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-500">Primary</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="mt-6 text-sm font-semibold text-blue-600 flex items-center hover:underline">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add another diagnosis
                    </button>
                </div>

                <!-- Prescription -->
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200 mb-8">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-sm font-semibold text-slate-500">Prescription</h3>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg">Sent straight to pharmacy</span>
                    </div>

                    <div id="prescription-container" class="space-y-4">
                        <!-- Dynamic items will be added here -->
                    </div>

                    <button type="button" onclick="addPrescriptionRow()" class="mt-6 text-sm font-semibold text-teal-600 flex items-center hover:underline">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add a medication
                    </button>
                </div>

                <!-- Outcome -->
                <div class="bg-white p-8 rounded-[3rem] shadow-sm border border-slate-200 mb-8">
                    <h3 class="text-sm font-semibold text-slate-500 mb-6">What happens next?</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <label class="relative flex flex-col p-6 bg-slate-50 rounded-[2rem] border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-green-50 has-[:checked]:border-green-500 group">
                            <input type="radio" name="outcome" value="discharge" required class="sr-only">
                            <span class="text-sm font-semibold text-slate-900 mb-1">Discharge</span>
                            <span class="text-xs text-slate-400 leading-tight">Patient goes home after treatment</span>
                        </label>

                        <label class="relative flex flex-col p-6 bg-slate-50 rounded-[2rem] border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-orange-50 has-[:checked]:border-orange-500 group">
                            <input type="radio" name="outcome" value="admit" class="sr-only">
                            <span class="text-sm font-semibold text-slate-900 mb-1">Admit to a ward</span>
                            <span class="text-xs text-slate-400 leading-tight">Hand over to inpatient nursing staff</span>
                        </label>

                        <label class="relative flex flex-col p-6 bg-slate-50 rounded-[2rem] border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 group">
                            <input type="radio" name="outcome" value="refer" class="sr-only">
                            <span class="text-sm font-semibold text-slate-900 mb-1">Refer elsewhere</span>
                            <span class="text-xs text-slate-400 leading-tight">Transfer to a higher-level facility</span>
                        </label>
                    </div>

                    <details class="mt-4">
                        <summary class="text-sm font-medium text-slate-500 cursor-pointer hover:text-slate-700">Discharged against advice, or patient deceased?</summary>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-amber-50 has-[:checked]:border-amber-500 group">
                                <input type="radio" name="outcome" value="dama" class="sr-only">
                                <span class="text-sm font-semibold text-slate-900">Discharged against medical advice</span>
                            </label>
                            <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent cursor-pointer hover:bg-slate-100 transition-all has-[:checked]:bg-slate-200 has-[:checked]:border-slate-500 group">
                                <input type="radio" name="outcome" value="deceased" class="sr-only">
                                <span class="text-sm font-semibold text-slate-900">Deceased</span>
                            </label>
                        </div>
                    </details>

                    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Follow-up date (optional)</label>
                            <input type="date" name="follow_up_date" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500 shadow-inner">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2 ml-1">Referral or discharge notes</label>
                            <input type="text" name="outcome_notes" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-blue-500 shadow-inner" placeholder="Reason for referral, or discharge instructions...">
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-6">
                    <button type="button" class="px-8 py-4 text-slate-400 font-semibold hover:text-slate-600 text-sm transition-all">
                        Save as draft
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-12 py-5 rounded-[2rem] font-semibold hover:bg-blue-700 shadow-xl shadow-blue-100 transition-all text-sm">
                        Complete consultation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const drugList = {!! json_encode($drugs) !!};
const patientAllergies = {!! json_encode($visit->patient->allergies ?? []) !!};
let prescriptionCount = 0;

function addPrescriptionRow() {
    const container = document.getElementById('prescription-container');
    const index = prescriptionCount++;

    const row = document.createElement('div');
    row.className = 'prescription-row p-6 bg-slate-50 rounded-2xl border border-slate-100 relative';
    row.id = `row-${index}`;

    row.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-slate-500 mb-1 ml-1">Medication</label>
                <select name="prescription_items[${index}][drug_id]" required onchange="handleDrugSelect(${index}, this.value)" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 shadow-sm">
                    <option value="">Select a drug</option>
                    ${drugList.map(d => `<option value="${d.id}">${d.name} (${d.strength})</option>`).join('')}
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1 ml-1">Dose</label>
                <input type="text" name="prescription_items[${index}][dose]" required class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 shadow-sm" placeholder="e.g. 500mg">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1 ml-1">How often</label>
                <select name="prescription_items[${index}][frequency]" id="freq-${index}" required onchange="calculateQty(${index})" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 shadow-sm">
                    <option value="1">Once a day (OD)</option>
                    <option value="2">Twice a day (BD)</option>
                    <option value="3">Three times a day (TDS)</option>
                    <option value="4">Four times a day (QDS)</option>
                    <option value="0.5">As needed (PRN)</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1 ml-1">Days</label>
                <input type="number" name="prescription_items[${index}][duration]" id="dur-${index}" required onchange="calculateQty(${index})" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 shadow-sm" placeholder="7">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1 ml-1">Total qty</label>
                <input type="number" name="prescription_items[${index}][quantity]" id="qty-${index}" readonly class="w-full px-4 py-3 bg-slate-100 border-none rounded-xl text-sm font-bold text-slate-600">
            </div>
        </div>

        <div id="allergy-warning-${index}" class="hidden mt-4 p-4 bg-red-100 border-2 border-red-200 rounded-xl">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-600 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-red-800">Heads up — allergy to <span id="allergy-name-${index}"></span></p>
                    <p class="text-xs text-red-700 mt-1 mb-4">This medication may conflict with a recorded allergy. Please explain why it's still appropriate before continuing.</p>
                    <input type="text" name="allergy_justifications[${drugList[0].id}]" id="justification-${index}" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-medium focus:ring-2 focus:ring-red-500" placeholder="Reason for prescribing anyway...">
                </div>
            </div>
        </div>

        <button type="button" onclick="removeRow(${index})" class="absolute -top-2 -right-2 bg-white border border-slate-200 text-slate-400 rounded-full p-1 hover:text-red-500 hover:border-red-200 shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    container.appendChild(row);
}

function removeRow(index) {
    document.getElementById(`row-${index}`).remove();
}

function calculateQty(index) {
    const freq = parseFloat(document.getElementById(`freq-${index}`).value);
    const dur = parseInt(document.getElementById(`dur-${index}`).value);
    const qtyInput = document.getElementById(`qty-${index}`);

    if (freq > 0 && dur > 0) {
        qtyInput.value = Math.ceil(freq * dur);
    }
}

function handleDrugSelect(index, drugId) {
    const drug = drugList.find(d => d.id == drugId);
    if (!drug) return;

    // Update justification field name to use the actual drug ID
    const justInput = document.getElementById(`justification-${index}`);
    justInput.name = `allergy_justifications[${drugId}]`;

    // Check allergies
    const warningDiv = document.getElementById(`allergy-warning-${index}`);
    const matchedAllergy = patientAllergies.find(a =>
        drug.name.toLowerCase().includes(a.toLowerCase()) ||
        drug.generic_name.toLowerCase().includes(a.toLowerCase())
    );

    if (matchedAllergy) {
        warningDiv.classList.remove('hidden');
        document.getElementById(`allergy-name-${index}`).innerText = matchedAllergy;
        justInput.required = true;
    } else {
        warningDiv.classList.add('hidden');
        justInput.required = false;
    }

    // Auto-fill dose if drug has strength
    const doseInput = document.querySelector(`input[name="prescription_items[${index}][dose]"]`);
    if (!doseInput.value) doseInput.value = drug.strength;
}

document.addEventListener('DOMContentLoaded', function () {
    const weightInput = document.getElementById('weight');
    const heightInput = document.getElementById('height');
    const bmiDisplay = document.getElementById('bmi_display');

    function calculateBMI() {
        const weight = parseFloat(weightInput.value);
        const height = parseFloat(heightInput.value);

        if (weight > 0 && height > 0) {
            const heightInMeters = height / 100;
            const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(1);
            bmiDisplay.innerText = bmi;
            bmiDisplay.classList.remove('text-slate-400');
            bmiDisplay.classList.add('text-slate-900');
        } else {
            bmiDisplay.innerText = '---';
            bmiDisplay.classList.remove('text-slate-900');
            bmiDisplay.classList.add('text-slate-400');
        }
    }

    if (weightInput) {
        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
    }

    addPrescriptionRow();
});
</script>
@endsection
