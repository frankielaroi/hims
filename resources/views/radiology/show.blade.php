@extends('layouts.app')

@section('title', 'Imaging Request #' . $radiologyOrder->id)

@section('content')
@php
    $card  = 'bg-white p-6 rounded-2xl border border-slate-200 shadow-sm';
    $label = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5';
    $input = 'w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 transition focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-none';

    $canProcess = auth()->user()->hasPermission('process_radiology_orders');
    $isOrderingClinician = auth()->id() === $radiologyOrder->ordered_by;
@endphp

<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-slate-900 rounded-[2rem] p-6 text-white shadow-xl flex flex-wrap items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight">{{ $radiologyOrder->study_label }}</h1>
                @if($radiologyOrder->is_critical)
                    <span class="px-2 py-0.5 bg-red-500/20 border border-red-500/40 text-red-300 rounded-md text-[10px] font-bold uppercase tracking-widest">Critical finding</span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-4 mt-1 text-slate-400 text-sm font-medium">
                <span>{{ $radiologyOrder->visit->patient->full_name }}</span>
                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                <span>{{ $radiologyOrder->visit->patient->hospital_id }}</span>
                <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
                <span class="text-indigo-400">{{ ucfirst(str_replace('_', ' ', $radiologyOrder->status)) }}</span>
            </div>
        </div>
        <a href="{{ route('radiology.index') }}" class="px-4 py-2 bg-white/10 rounded-xl text-sm font-semibold hover:bg-white/20 transition">Back to worklist</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Request</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Modality</dt><dd class="text-slate-900 font-medium">{{ strtoupper($radiologyOrder->modality) }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Body part</dt><dd class="text-slate-900 font-medium">{{ $radiologyOrder->body_part }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Priority</dt><dd class="text-slate-900 font-medium">{{ ucfirst($radiologyOrder->priority) }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Contrast</dt><dd class="text-slate-900 font-medium">{{ $radiologyOrder->contrast_required ? 'Required' : 'Not required' }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Requested by</dt><dd class="text-slate-900 font-medium">{{ $radiologyOrder->orderedBy->name ?? 'Unknown' }}</dd></div>
                    <div><dt class="text-xs font-bold text-slate-400 uppercase tracking-wider">Indication</dt><dd class="text-slate-700">{{ $radiologyOrder->clinical_indication }}</dd></div>
                </dl>

                @if($canProcess && ! $radiologyOrder->performed_at && $radiologyOrder->status !== 'cancelled')
                    <form action="{{ route('radiology.performed', $radiologyOrder) }}" method="POST" class="mt-5">
                        @csrf
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition-colors">
                            Mark study performed
                        </button>
                    </form>
                @elseif($radiologyOrder->performed_at)
                    <p class="mt-5 text-xs text-slate-500">
                        Performed by {{ $radiologyOrder->performedBy->name ?? 'unknown' }}
                        on {{ $radiologyOrder->performed_at->format('d M Y, H:i') }}.
                    </p>
                @endif
            </div>

            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Attachments</h2>
                <div class="space-y-2">
                    @forelse($radiologyOrder->attachments as $attachment)
                        <div class="flex items-center justify-between gap-2 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="min-w-0">
                                <a href="{{ route('radiology.attachments.download', [$radiologyOrder, $attachment]) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 truncate block">
                                    {{ $attachment->original_name }}
                                </a>
                                <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">{{ $attachment->kind }} &bull; {{ $attachment->human_size }}</p>
                            </div>
                            @if($canProcess)
                                <form action="{{ route('radiology.attachments.destroy', [$radiologyOrder, $attachment]) }}" method="POST"
                                      onsubmit="return confirm('Remove this attachment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors" aria-label="Remove attachment">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 italic">No images or documents attached.</p>
                    @endforelse
                </div>

                @if($canProcess)
                    <form action="{{ route('radiology.attachments.store', $radiologyOrder) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="{{ $label }}">File</label>
                            <input type="file" name="file" required class="{{ $input }}">
                            <p class="text-xs text-slate-500 mt-1.5">JPEG, PNG, WebP, PDF or DICOM, up to 20 MB.</p>
                        </div>
                        <div>
                            <label class="{{ $label }}">Kind</label>
                            <select name="kind" class="{{ $input }}">
                                <option value="image">Image</option>
                                <option value="report">Report document</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-slate-100 text-slate-700 rounded-xl text-sm font-bold hover:bg-slate-200 transition-colors">
                            Upload
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Report</h2>

                @if($radiologyOrder->isReported())
                    <div class="mb-5 p-4 bg-slate-50 rounded-xl border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Findings</p>
                        <p class="text-sm text-slate-800 whitespace-pre-line mt-1">{{ $radiologyOrder->findings }}</p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-4">Impression</p>
                        <p class="text-sm text-slate-800 whitespace-pre-line mt-1">{{ $radiologyOrder->impression }}</p>
                        <p class="text-xs text-slate-500 mt-4">
                            Reported by {{ $radiologyOrder->reportedBy->name ?? 'unknown' }}
                            on {{ $radiologyOrder->reported_at->format('d M Y, H:i') }}.
                        </p>
                    </div>
                @endif

                @if($canProcess)
                    <form action="{{ route('radiology.report', $radiologyOrder) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="{{ $label }}">Findings</label>
                            <textarea name="findings" rows="6" required class="{{ $input }}">{{ old('findings', $radiologyOrder->findings) }}</textarea>
                        </div>
                        <div>
                            <label class="{{ $label }}">Impression</label>
                            <textarea name="impression" rows="3" required class="{{ $input }}">{{ old('impression', $radiologyOrder->impression) }}</textarea>
                        </div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_critical" value="1" @checked(old('is_critical', $radiologyOrder->is_critical))
                                   class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                            <span class="text-sm font-medium text-slate-700">This is a critical finding the clinician must act on now</span>
                        </label>
                        <button type="submit" class="px-8 py-3 bg-indigo-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-indigo-500/20 hover:bg-indigo-700 transition-all">
                            {{ $radiologyOrder->isReported() ? 'Amend report' : 'File report' }}
                        </button>
                        @if($radiologyOrder->isReported())
                            <p class="text-xs text-slate-500">Amending a report clears the clinician's review, so it has to be read again.</p>
                        @endif
                    </form>
                @elseif(! $radiologyOrder->isReported())
                    <p class="text-sm text-slate-400 italic">No report has been filed yet.</p>
                @endif
            </div>

            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight mb-4">Clinician review</h2>

                @if($radiologyOrder->isReviewed())
                    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                        <p class="text-sm font-bold text-emerald-900">
                            Read by {{ $radiologyOrder->reviewedBy->name ?? 'unknown' }}
                            on {{ $radiologyOrder->reviewed_at->format('d M Y, H:i') }}
                        </p>
                        @if($radiologyOrder->review_note)
                            <p class="text-sm text-emerald-800 mt-1">{{ $radiologyOrder->review_note }}</p>
                        @endif
                    </div>
                @elseif($radiologyOrder->isReported())
                    <form action="{{ route('radiology.review', $radiologyOrder) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="{{ $label }}">What are you doing about it? (optional)</label>
                            <textarea name="review_note" rows="3" class="{{ $input }}" placeholder="e.g. Started antibiotics, repeat film in 6 weeks."></textarea>
                        </div>
                        <button type="submit" class="px-8 py-3 bg-emerald-600 text-white rounded-[2rem] text-sm font-black shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 transition-all">
                            Mark report as read
                        </button>
                        @unless($isOrderingClinician)
                            <p class="text-xs text-slate-500">You did not order this study. Your name will be recorded as the reader.</p>
                        @endunless
                    </form>
                @else
                    <p class="text-sm text-slate-400 italic">There is nothing to review until a report has been filed.</p>
                @endif
            </div>

            @if($canProcess && ! $radiologyOrder->isReported() && $radiologyOrder->status !== 'cancelled')
                <form action="{{ route('radiology.cancel', $radiologyOrder) }}" method="POST" class="{{ $card }} space-y-3"
                      onsubmit="return confirm('Cancel this imaging request?');">
                    @csrf
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Cancel request</h2>
                    <input type="text" name="cancellation_reason" required minlength="5" placeholder="Reason for cancelling" class="{{ $input }}">
                    <button type="submit" class="px-6 py-2.5 bg-red-50 text-red-700 border border-red-200 rounded-xl text-sm font-bold hover:bg-red-100 transition-colors">
                        Cancel imaging request
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
