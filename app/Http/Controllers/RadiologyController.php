<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\RadiologyAttachment;
use App\Models\RadiologyOrder;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RadiologyController extends Controller
{
    /**
     * Attachment types accepted from the imaging department. DICOM is not
     * rendered in the browser but is kept so the study travels with the record.
     *
     * @var list<string>
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/dicom',
    ];

    protected const MAX_UPLOAD_KILOBYTES = 20480; // 20 MB

    public function index()
    {
        $this->authorizeRadiology();

        $orders = RadiologyOrder::with(['visit.patient', 'visit.department', 'orderedBy'])
            ->outstanding()
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(20);

        return view('radiology.index', compact('orders'));
    }

    public function show(RadiologyOrder $radiologyOrder)
    {
        $radiologyOrder->load([
            'visit.patient', 'orderedBy', 'performedBy', 'reportedBy', 'reviewedBy',
            'attachments.uploadedBy',
        ]);

        return view('radiology.show', compact('radiologyOrder'));
    }

    /**
     * Raise an imaging request from the consultation workspace.
     */
    public function store(Request $request, Visit $visit)
    {
        if (! auth()->user()->hasPermission('order_imaging')) {
            abort(403, 'You are not permitted to order imaging.');
        }

        $validated = $request->validate([
            'modality' => ['required', Rule::in(RadiologyOrder::MODALITIES)],
            'body_part' => 'required|string|max:255',
            'clinical_indication' => 'required|string',
            'priority' => ['required', Rule::in(RadiologyOrder::PRIORITIES)],
            'contrast_required' => 'nullable|boolean',
        ]);

        $order = DB::transaction(function () use ($validated, $visit, $request) {
            $order = RadiologyOrder::create([
                'visit_id' => $visit->id,
                'consultation_id' => $visit->consultations()->latest('id')->value('id'),
                'ordered_by' => auth()->id(),
                'modality' => $validated['modality'],
                'body_part' => $validated['body_part'],
                'clinical_indication' => $validated['clinical_indication'],
                'priority' => $validated['priority'],
                'contrast_required' => $request->boolean('contrast_required'),
                'status' => RadiologyOrder::STATUS_ORDERED,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'radiology_order_created',
                'description' => "Imaging request #{$order->id} ({$order->study_label}) for visit {$visit->visit_number}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['visit_id' => $visit->id, 'radiology_order_id' => $order->id],
            ]);

            return $order;
        });

        return back()->with('success', "Imaging requested: {$order->study_label}. Sent to radiology.");
    }

    /**
     * Radiographer records that the study has been carried out.
     */
    public function markPerformed(Request $request, RadiologyOrder $radiologyOrder)
    {
        $this->authorizeRadiology();

        abort_if(
            in_array($radiologyOrder->status, RadiologyOrder::RESOLVED_STATUSES, true),
            422,
            'This imaging request is already closed.'
        );

        $radiologyOrder->update([
            'status' => RadiologyOrder::STATUS_PERFORMED,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
        ]);

        return back()->with('success', 'Study marked as performed.');
    }

    /**
     * Radiologist files the report. Charging happens here rather than at
     * ordering, so a cancelled request never reaches the patient's bill.
     */
    public function saveReport(Request $request, RadiologyOrder $radiologyOrder)
    {
        $this->authorizeRadiology();

        $validated = $request->validate([
            'findings' => 'required|string',
            'impression' => 'required|string',
            'is_critical' => 'nullable|boolean',
        ]);

        $wasAlreadyReported = $radiologyOrder->isReported();

        DB::transaction(function () use ($validated, $radiologyOrder, $request, $wasAlreadyReported) {
            $radiologyOrder->update([
                'findings' => $validated['findings'],
                'impression' => $validated['impression'],
                'is_critical' => $request->boolean('is_critical'),
                'status' => RadiologyOrder::STATUS_REPORTED,
                'reported_by' => auth()->id(),
                'reported_at' => now(),
                // A re-issued report has to be read again.
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            if (! $wasAlreadyReported) {
                $this->chargeForStudy($radiologyOrder);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $wasAlreadyReported ? 'radiology_report_amended' : 'radiology_report_filed',
                'description' => "Report filed for imaging request #{$radiologyOrder->id} ({$radiologyOrder->study_label}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'visit_id' => $radiologyOrder->visit_id,
                    'radiology_order_id' => $radiologyOrder->id,
                    'is_critical' => $radiologyOrder->is_critical,
                ],
            ]);
        });

        return back()->with('success', $wasAlreadyReported ? 'Report amended.' : 'Report filed.');
    }

    /**
     * The ordering clinician records that they have read the report.
     */
    public function review(Request $request, RadiologyOrder $radiologyOrder)
    {
        abort_unless($radiologyOrder->isReported(), 422, 'There is no report to review yet.');

        $validated = $request->validate([
            'review_note' => 'nullable|string',
        ]);

        $radiologyOrder->update([
            'status' => RadiologyOrder::STATUS_REVIEWED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'radiology_report_reviewed',
            'description' => "Report for imaging request #{$radiologyOrder->id} reviewed.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'visit_id' => $radiologyOrder->visit_id,
                'radiology_order_id' => $radiologyOrder->id,
            ],
        ]);

        return back()->with('success', 'Report marked as reviewed.');
    }

    public function cancel(Request $request, RadiologyOrder $radiologyOrder)
    {
        abort_if($radiologyOrder->isReported(), 422, 'A reported study cannot be cancelled.');

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:5',
        ]);

        $radiologyOrder->update([
            'status' => RadiologyOrder::STATUS_CANCELLED,
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return redirect()->route('radiology.index')->with('success', 'Imaging request cancelled.');
    }

    /**
     * Attach an image or report document to the study.
     */
    public function storeAttachment(Request $request, RadiologyOrder $radiologyOrder)
    {
        $this->authorizeRadiology();

        $request->validate([
            'file' => [
                'required', 'file',
                'max:'.self::MAX_UPLOAD_KILOBYTES,
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'kind' => ['required', Rule::in(RadiologyAttachment::KINDS)],
        ]);

        $file = $request->file('file');

        // Stored on a private disk under the order id; served only through
        // downloadAttachment(), never as a public URL.
        $path = $file->store("radiology/{$radiologyOrder->id}", 'local');

        $radiologyOrder->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'kind' => $request->input('kind'),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    /**
     * Stream an attachment. Going through the controller keeps the file behind
     * authentication and lets the access be logged.
     */
    public function downloadAttachment(Request $request, RadiologyOrder $radiologyOrder, RadiologyAttachment $attachment)
    {
        abort_unless($attachment->radiology_order_id === $radiologyOrder->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'radiology_attachment_viewed',
            'description' => "Viewed attachment {$attachment->original_name} on imaging request #{$radiologyOrder->id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'radiology_order_id' => $radiologyOrder->id,
                'attachment_id' => $attachment->id,
            ],
        ]);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(RadiologyOrder $radiologyOrder, RadiologyAttachment $attachment)
    {
        $this->authorizeRadiology();
        abort_unless($attachment->radiology_order_id === $radiologyOrder->id, 404);

        $attachment->deleteWithFile();

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * Bill the study against the visit, using the service priced for this
     * modality where one exists.
     */
    protected function chargeForStudy(RadiologyOrder $order): void
    {
        $service = Service::where('code', 'RAD-'.strtoupper($order->modality))->first()
            ?? Service::where('code', 'RAD-GEN')->first();

        if ($service === null) {
            return;
        }

        Invoice::addCharge(
            $order->visit,
            $service->name.' — '.$order->body_part,
            'Radiology',
            (float) $service->price,
            $service->is_nhis_covered,
        );
    }

    protected function authorizeRadiology(): void
    {
        if (! auth()->user()->hasPermission('process_radiology_orders')) {
            abort(403, 'You are not permitted to work on imaging requests.');
        }
    }
}
