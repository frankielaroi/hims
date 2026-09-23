@extends('layouts.app')

@section('title', 'Labour record - ' . $delivery->patient->full_name)

@section('content')
@php
    $card = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 transition focus:bg-white focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 focus:outline-none';
    $label = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
    $statusStyles = [
        'latent_phase' => 'bg-slate-50 text-slate-600 border-slate-200',
        'on_track' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'alert' => 'bg-amber-50 text-amber-700 border-amber-100',
        'action' => 'bg-red-50 text-red-700 border-red-100',
    ];
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 tracking-tight">{{ $delivery->patient->full_name }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                {{ $delivery->patient->hospital_id }}
                @if($delivery->gestation_weeks) &bull; {{ $delivery->gestation_weeks }} weeks @endif
                &bull; {{ $delivery->isDelivered() ? 'Delivered '.$delivery->delivered_at->format('d M Y H:i') : 'In labour' }}
                &bull; {{ $delivery->location() }}
            </p>
        </div>
        <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-2 bg-white border border-slate-200 shadow-sm text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Labour ward
        </a>
    </div>

    {{-- Partograph progress against the alert and action lines --}}
    <div class="p-5 rounded-2xl border {{ $statusStyles[$progress['status']] }}">
        <div class="text-xs font-bold uppercase tracking-widest">Partograph — {{ str_replace('_', ' ', $progress['status']) }}</div>
        <p class="text-sm font-medium mt-1">{{ $progress['message'] }}</p>
        @if($progress['latest_cm'] !== null)
            <p class="text-xs mt-2 opacity-80">
                Latest {{ $progress['latest_cm'] }} cm
                @if($progress['expected_cm'] !== null) &bull; expected {{ number_format($progress['expected_cm'], 1) }} cm @endif
            </p>
        @endif
    </div>

    @if($warnings !== [])
        <div class="{{ $card }} border-amber-200">
            <h2 class="text-sm font-bold text-amber-700 uppercase tracking-tight mb-3">Observations needing attention</h2>
            <ul class="space-y-1 text-sm text-slate-700 list-disc list-inside">
                @foreach($warnings as $warning)<li>{{ $warning }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($delivery->isDelivered())
        @php $alerts = $delivery->alerts(); @endphp
        @if($alerts !== [])
            <div class="{{ $card }} border-amber-200">
                <h2 class="text-sm font-bold text-amber-700 uppercase tracking-tight mb-3">Delivery alerts</h2>
                <ul class="space-y-1 text-sm text-slate-700 list-disc list-inside">
                    @foreach($alerts as $alert)<li>{{ $alert }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Delivery</h2>
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Mode</dt>
                    <dd class="font-medium text-slate-900">{{ ucfirst(str_replace('_', ' ', $delivery->mode)) }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Blood loss</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->blood_loss_ml !== null ? $delivery->blood_loss_ml.' ml' : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Third stage</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->third_stage_management ? ucfirst($delivery->third_stage_management) : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Uterotonic</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->uterotonic_given ? 'Given' : 'Not given' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Placenta</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->placenta_state ? ucfirst($delivery->placenta_state) : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Perineum</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->perineum ? ucfirst(str_replace('_', ' ', $delivery->perineum)) : '—' }}</dd></div>
                <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Delivered by</dt>
                    <dd class="font-medium text-slate-900">{{ $delivery->deliveredBy->name ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Babies</h2>
            @foreach($delivery->newborns as $baby)
                <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="font-semibold text-slate-900">
                            Baby {{ $baby->birth_order }} &bull; {{ ucfirst($baby->sex) }}
                            <span class="text-slate-400 font-medium">{{ ucfirst(str_replace('_', ' ', $baby->outcome)) }}</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ $baby->birth_weight_grams ? $baby->birth_weight_grams.' g' : 'weight not recorded' }}
                            &bull; Apgar {{ $baby->apgar_one_minute ?? '—' }}/{{ $baby->apgar_five_minutes ?? '—' }}
                        </div>
                    </div>

                    @php $babyAlerts = $baby->alerts(); @endphp
                    @if($babyAlerts !== [])
                        <ul class="mt-3 space-y-1 text-sm text-amber-700 list-disc list-inside">
                            @foreach($babyAlerts as $alert)<li>{{ $alert }}</li>@endforeach
                        </ul>
                    @endif

                    @if($baby->isLiveBirth() && $baby->patient_id === null)
                        <form action="{{ route('deliveries.newborns.register', [$delivery, $baby]) }}" method="POST" class="flex flex-wrap gap-2 mt-4">
                            @csrf
                            <input type="text" name="full_name" required class="{{ $input }} max-w-xs" placeholder="Baby's name">
                            <button type="submit" class="px-5 py-2.5 bg-rose-600 text-white rounded-xl text-sm font-bold hover:bg-rose-700 transition-all">
                                Register baby
                            </button>
                        </form>
                    @elseif($baby->patient)
                        <a href="{{ route('patients.show', $baby->patient) }}" class="inline-block mt-3 text-xs font-bold text-rose-600 uppercase tracking-widest hover:text-rose-800">
                            {{ $baby->patient->hospital_id }} — open record
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($delivery->isDelivered())
        {{-- Postnatal care: 6 hours, 6 days, 6 weeks --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Postnatal care</h2>

            @php
                $pncStyles = [
                    'done' => 'bg-emerald-50 border-emerald-100 text-emerald-700',
                    'due' => 'bg-amber-50 border-amber-100 text-amber-700',
                    'overdue' => 'bg-red-50 border-red-100 text-red-700',
                    'not_yet_due' => 'bg-slate-50 border-slate-200 text-slate-500',
                ];
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                @foreach($postnatal as $contact)
                    <div class="p-4 rounded-2xl border {{ $pncStyles[$contact['status']] }}">
                        <div class="text-xs font-bold uppercase tracking-wider">Contact {{ $contact['number'] }} &bull; {{ $contact['label'] }}</div>
                        <div class="text-sm font-bold text-slate-900 mt-1">{{ str_replace('_', ' ', $contact['status']) }}</div>
                        <div class="text-xs mt-1 opacity-80">
                            @if($contact['seen_at'])
                                Seen {{ $contact['seen_at']->format('d M H:i') }}
                            @elseif($contact['due_at'])
                                Due {{ $contact['due_at']->format('d M H:i') }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach($delivery->postnatalVisits as $pnc)
                @php $pncDangers = $pnc->dangerSigns(); @endphp
                <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-semibold text-slate-900">Contact {{ $pnc->contact_number }}</span>
                        <span class="text-xs text-slate-500">{{ $pnc->seen_at->format('d M Y H:i') }} &bull; {{ $pnc->recordedBy->name ?? '' }}</span>
                    </div>
                    @if($pncDangers !== [])
                        <ul class="mt-2 space-y-1 text-sm text-amber-700 list-disc list-inside">
                            @foreach($pncDangers as $danger)<li>{{ $danger }}</li>@endforeach
                        </ul>
                    @endif
                    @foreach($pnc->newbornChecks as $check)
                        @php $checkAlerts = $check->alerts(); @endphp
                        @if($checkAlerts !== [])
                            <ul class="mt-2 space-y-1 text-sm text-amber-700 list-disc list-inside">
                                @foreach($checkAlerts as $alert)<li>Baby {{ $check->newbornRecord->birth_order }}: {{ $alert }}</li>@endforeach
                            </ul>
                        @endif
                    @endforeach
                </div>
            @endforeach

            @php $nextContact = collect($postnatal)->firstWhere('status', '!=', 'done'); @endphp
            @if($nextContact)
                <form action="{{ route('postnatal.store', $delivery) }}" method="POST" class="mt-4 pt-4 border-t border-slate-100">
                    @csrf
                    <input type="hidden" name="contact_number" value="{{ $nextContact['number'] }}">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">
                        Record contact {{ $nextContact['number'] }} ({{ $nextContact['label'] }}) — mother
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div><label class="{{ $label }}">Temperature (°C)</label><input type="number" step="0.1" name="temperature" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Pulse</label><input type="number" name="pulse" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Systolic BP</label><input type="number" name="systolic_bp" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Diastolic BP</label><input type="number" name="diastolic_bp" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Haemoglobin</label><input type="number" step="0.1" name="haemoglobin" class="{{ $input }}"></div>
                        @foreach(['lochia' => \App\Models\PostnatalVisit::LOCHIA_STATES, 'uterus_involution' => \App\Models\PostnatalVisit::UTERUS_STATES, 'perineum_state' => \App\Models\PostnatalVisit::PERINEUM_STATES, 'breast_state' => \App\Models\PostnatalVisit::BREAST_STATES, 'emotional_wellbeing' => \App\Models\PostnatalVisit::EMOTIONAL_STATES] as $field => $options)
                            <div>
                                <label class="{{ $label }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</label>
                                <select name="{{ $field }}" class="{{ $input }}">
                                    <option value="">—</option>
                                    @foreach($options as $option)
                                        <option value="{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div><label class="{{ $label }}">Family planning method</label><input type="text" name="family_planning_method" class="{{ $input }}"></div>
                    </div>
                    <div class="flex flex-wrap gap-4 mt-3">
                        @foreach(['family_planning_counselled' => 'Family planning counselled', 'ifa_given' => 'Iron &amp; folic acid', 'vitamin_a_given' => 'Vitamin A'] as $field => $title)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="{{ $field }}" value="1" class="rounded border-slate-300 text-rose-600">
                                <span class="text-xs text-slate-700">{!! $title !!}</span>
                            </label>
                        @endforeach
                    </div>

                    @foreach($delivery->newborns->where('outcome', \App\Models\NewbornRecord::OUTCOME_LIVE_BIRTH) as $baby)
                        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-5 mb-3">Baby {{ $baby->birth_order }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            <div><label class="{{ $label }}">Weight (g)</label><input type="number" name="newborns[{{ $baby->id }}][weight_grams]" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Temperature (°C)</label><input type="number" step="0.1" name="newborns[{{ $baby->id }}][temperature]" class="{{ $input }}"></div>
                            <div>
                                <label class="{{ $label }}">Cord</label>
                                <select name="newborns[{{ $baby->id }}][cord_state]" class="{{ $input }}">
                                    <option value="">—</option>
                                    @foreach(\App\Models\PostnatalNewbornCheck::CORD_STATES as $state)
                                        <option value="{{ $state }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $label }}">Feeding</label>
                                <select name="newborns[{{ $baby->id }}][feeding]" class="{{ $input }}">
                                    <option value="">—</option>
                                    @foreach(\App\Models\PostnatalNewbornCheck::FEEDING_STATES as $state)
                                        <option value="{{ $state }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3 mt-3">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="newborns[{{ $baby->id }}][jaundice]" value="1" class="rounded border-slate-300 text-amber-600">
                                <span class="text-xs text-slate-700">Jaundice</span>
                            </label>
                            @foreach(\App\Models\PostnatalNewbornCheck::DANGER_SIGNS as $code => $title)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="newborns[{{ $baby->id }}][danger_signs][]" value="{{ $code }}" class="rounded border-slate-300 text-red-600">
                                    <span class="text-xs text-slate-700">{{ $title }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="mt-4">
                        <label class="{{ $label }}">Notes</label>
                        <textarea name="notes" rows="2" class="{{ $input }}"></textarea>
                    </div>

                    <button type="submit" class="mt-4 px-8 py-3 bg-rose-600 text-white rounded-[2rem] text-sm font-black hover:bg-rose-700 transition-all">
                        Record postnatal contact
                    </button>
                </form>
            @else
                <p class="text-sm text-emerald-700 font-medium">Postnatal schedule complete.</p>
            @endif
        </div>

        {{-- Theatre: a caesarean needs an operation record behind it --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Operations</h2>

            @forelse($delivery->operations as $operation)
                <div class="p-4 rounded-2xl border border-slate-100 mb-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-semibold text-slate-900">{{ $operation->procedure }}</span>
                        <span class="text-xs text-slate-500">
                            {{ ucfirst($operation->status) }}
                            @if($operation->durationMinutes()) &bull; {{ $operation->durationMinutes() }} min @endif
                            &bull; {{ $operation->surgeon->name ?? 'no surgeon named' }}
                        </span>
                    </div>
                    @php $operationAlerts = $operation->alerts(); @endphp
                    @if($operationAlerts !== [])
                        <ul class="mt-2 space-y-1 text-sm text-amber-700 list-disc list-inside">
                            @foreach($operationAlerts as $alert)<li>{{ $alert }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            @empty
                @if($delivery->isCaesarean())
                    <p class="text-sm text-amber-700 font-medium">
                        This was a caesarean with no operation record. Open one from theatre so the surgeon,
                        anaesthetic and swab count are on the notes.
                    </p>
                @else
                    <p class="text-sm text-slate-500">No operations recorded.</p>
                @endif
            @endforelse
        </div>
    @endif

    {{-- Partograph observations --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Partograph observations</h2>

        @forelse($delivery->partographEntries as $entry)
            <div class="flex flex-wrap items-center gap-4 py-2 border-b border-slate-100 text-sm">
                <span class="font-medium text-slate-900 w-28">{{ $entry->recorded_at->format('d M H:i') }}</span>
                <span class="text-slate-600">{{ $entry->cervical_dilatation_cm !== null ? $entry->cervical_dilatation_cm.' cm' : '—' }}</span>
                <span class="text-slate-600">FHR {{ $entry->fetal_heart_rate ?? '—' }}</span>
                <span class="text-slate-600">{{ $entry->liquor ? str_replace('_', ' ', $entry->liquor) : '—' }}</span>
                <span class="text-slate-400 text-xs ml-auto">{{ $entry->recordedBy->name ?? '' }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500 py-4">No observations recorded yet.</p>
        @endforelse

        @unless($delivery->isDelivered())
            <form action="{{ route('deliveries.observations.store', $delivery) }}" method="POST" class="mt-5">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $label }}">Cervix (cm)</label>
                        <input type="number" name="cervical_dilatation_cm" min="0" max="10" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Descent (fifths)</label>
                        <input type="number" name="descent_fifths" min="0" max="5" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Fetal heart rate</label>
                        <input type="number" name="fetal_heart_rate" class="{{ $input }}" placeholder="110-160">
                    </div>
                    <div>
                        <label class="{{ $label }}">Contractions / 10 min</label>
                        <input type="number" name="contractions_per_ten_minutes" min="0" max="10" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Liquor</label>
                        <select name="liquor" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach(\App\Models\PartographEntry::LIQUOR_STATES as $state)
                                <option value="{{ $state }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Moulding</label>
                        <select name="moulding" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach(\App\Models\PartographEntry::MOULDING_GRADES as $grade)
                                <option value="{{ $grade }}">{{ str_replace(['plus_plus_plus', 'plus_plus', 'plus'], ['+++', '++', '+'], $grade) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Maternal pulse</label>
                        <input type="number" name="maternal_pulse" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Systolic BP</label>
                        <input type="number" name="systolic_bp" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Diastolic BP</label>
                        <input type="number" name="diastolic_bp" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Urine protein</label>
                        <input type="text" name="urine_protein" class="{{ $input }}" placeholder="Nil, +, ++">
                    </div>
                    <div>
                        <label class="{{ $label }}">Oxytocin (units)</label>
                        <input type="number" step="0.1" name="oxytocin_units" class="{{ $input }}">
                    </div>
                </div>
                <button type="submit" class="mt-4 px-8 py-3 bg-slate-900 text-white rounded-[2rem] text-sm font-black hover:bg-slate-800 transition-all">
                    Record observation
                </button>
            </form>
        @endunless
    </div>

    {{-- Closing the labour --}}
    @unless($delivery->isDelivered())
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Record the delivery</h2>

            <form action="{{ route('deliveries.outcome.store', $delivery) }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div>
                        <label class="{{ $label }}">Delivered at</label>
                        <input type="datetime-local" name="delivered_at" required value="{{ now()->format('Y-m-d\TH:i') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Mode</label>
                        <select name="mode" required class="{{ $input }}">
                            @foreach(\App\Models\Delivery::MODES as $mode)
                                <option value="{{ $mode }}">{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Place</label>
                        <select name="place" required class="{{ $input }}">
                            @foreach(\App\Models\Delivery::PLACES as $place)
                                <option value="{{ $place }}">{{ ucfirst(str_replace('_', ' ', $place)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Gestation (weeks)</label>
                        <input type="number" name="gestation_weeks" min="20" max="45" value="{{ $delivery->gestation_weeks }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Third stage</label>
                        <select name="third_stage_management" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach(\App\Models\Delivery::THIRD_STAGE_MANAGEMENT as $management)
                                <option value="{{ $management }}">{{ ucfirst($management) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Placenta</label>
                        <select name="placenta_state" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach(\App\Models\Delivery::PLACENTA_STATES as $state)
                                <option value="{{ $state }}">{{ ucfirst($state) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">Blood loss (ml)</label>
                        <input type="number" name="blood_loss_ml" min="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">Perineum</label>
                        <select name="perineum" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach(\App\Models\Delivery::PERINEUM_STATES as $state)
                                <option value="{{ $state }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="uterotonic_given" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                        <span class="text-sm font-medium text-slate-700">Uterotonic given (active management of the third stage)</span>
                    </label>
                </div>

                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-6 mb-3">Complications</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    @foreach(\App\Models\Delivery::COMPLICATIONS as $complication)
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200">
                            <input type="checkbox" name="complications[]" value="{{ $complication }}" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-xs text-slate-700">{{ ucfirst(str_replace('_', ' ', $complication)) }}</span>
                        </label>
                    @endforeach
                </div>

                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-6 mb-3">Babies</h3>
                <div id="newborn-container" class="space-y-4"></div>
                <button type="button" onclick="addNewborn()" class="mt-3 text-xs font-bold text-rose-600 uppercase tracking-widest hover:text-rose-800">
                    + Add another baby
                </button>

                <div class="mt-4">
                    <label class="{{ $label }}">Notes</label>
                    <textarea name="notes" rows="2" class="{{ $input }}"></textarea>
                </div>

                <button type="submit" class="mt-5 px-10 py-3.5 bg-rose-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-rose-500/20 hover:bg-rose-700 transition-all">
                    Record delivery
                </button>
            </form>
        </div>

        <script>
        let newbornCount = 0;

        function addNewborn() {
            const index = newbornCount++;
            const row = document.createElement('div');
            row.className = 'p-4 bg-slate-50 rounded-2xl border border-slate-100';
            row.innerHTML = `
                <div class="text-sm font-bold text-slate-900 mb-3">Baby ${index + 1}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div>
                        <label class="{!! $label !!}">Sex</label>
                        <select name="newborns[${index}][sex]" required class="{!! $input !!}">
                            <option value="female">Female</option>
                            <option value="male">Male</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="{!! $label !!}">Outcome</label>
                        <select name="newborns[${index}][outcome]" required class="{!! $input !!}">
                            @foreach(\App\Models\NewbornRecord::OUTCOMES as $outcome)
                                <option value="{{ $outcome }}">{{ ucfirst(str_replace('_', ' ', $outcome)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{!! $label !!}">Birth weight (g)</label>
                        <input type="number" name="newborns[${index}][birth_weight_grams]" min="200" max="7000" class="{!! $input !!}">
                    </div>
                    <div>
                        <label class="{!! $label !!}">Apgar 1 / 5 min</label>
                        <div class="flex gap-2">
                            <input type="number" name="newborns[${index}][apgar_one_minute]" min="0" max="10" class="{!! $input !!}" aria-label="Apgar at one minute">
                            <input type="number" name="newborns[${index}][apgar_five_minutes]" min="0" max="10" class="{!! $input !!}" aria-label="Apgar at five minutes">
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 mt-3">
                    <label class="flex items-center gap-2"><input type="checkbox" name="newborns[${index}][breastfed_within_hour]" value="1" class="rounded border-slate-300 text-rose-600"><span class="text-xs text-slate-700">Breastfed within the hour</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="newborns[${index}][vitamin_k_given]" value="1" class="rounded border-slate-300 text-rose-600"><span class="text-xs text-slate-700">Vitamin K</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="newborns[${index}][eye_prophylaxis_given]" value="1" class="rounded border-slate-300 text-rose-600"><span class="text-xs text-slate-700">Eye prophylaxis</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="newborns[${index}][resuscitation_required]" value="1" class="rounded border-slate-300 text-rose-600"><span class="text-xs text-slate-700">Resuscitated</span></label>
                </div>
            `;
            document.getElementById('newborn-container').appendChild(row);
        }

        // A delivery always produces at least one baby.
        addNewborn();
        </script>
    @endunless
</div>
@endsection
