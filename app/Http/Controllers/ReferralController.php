<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Referral;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $incoming = Referral::with(['visit.patient', 'fromDepartment'])
            ->where('type', 'internal')
            ->where('status', 'pending')
            ->when($user->department_id, fn ($q) => $q->where('to_department_id', $user->department_id))
            ->orderByRaw("CASE urgency WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->get();

        $sent = Referral::with(['visit.patient', 'toDepartment'])
            ->where('referred_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $external = Referral::with(['visit.patient', 'fromDepartment'])
            ->where('type', 'external')
            ->where('status', '!=', 'completed')
            ->orderBy('created_at')
            ->get();

        return view('referrals.index', compact('incoming', 'sent', 'external'));
    }

    public function accept(Request $request, Referral $referral)
    {
        abort_unless($referral->status === 'pending', 422, 'This referral is no longer pending.');
        abort_unless($referral->type === 'internal', 422, 'Only internal referrals are accepted this way.');

        $newVisit = Visit::create([
            'patient_id' => $referral->visit->patient_id,
            'department_id' => $referral->to_department_id,
            'visit_number' => Visit::generateVisitNumber(),
            'queue_number' => Visit::nextQueueNumber($referral->to_department_id),
            'type' => 'Referred Consultation',
            'priority' => $referral->urgency,
            'payment_method' => $referral->visit->payment_method,
            'created_by' => auth()->id(),
            'reason_for_visit' => $referral->reason,
            'status' => 'waiting_doctor',
        ]);
        $newVisit->updateStatus('waiting_doctor');

        $referral->update([
            'status' => 'accepted',
            'accepted_by' => auth()->id(),
            'accepted_at' => now(),
            'new_visit_id' => $newVisit->id,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'referral_accepted',
            'description' => "Referral #{$referral->id} accepted, new visit {$newVisit->visit_number} opened in {$referral->toDepartment->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['referral_id' => $referral->id, 'new_visit_id' => $newVisit->id],
        ]);

        return redirect()->route('consultations.create', $newVisit)
            ->with('success', "Referral accepted. {$referral->visit->patient->full_name} is now waiting in {$referral->toDepartment->name}.");
    }

    public function decline(Request $request, Referral $referral)
    {
        abort_unless($referral->status === 'pending', 422, 'This referral is no longer pending.');

        $validated = $request->validate(['feedback_notes' => 'required|string']);

        $referral->update([
            'status' => 'declined',
            'feedback_notes' => $validated['feedback_notes'],
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'referral_declined',
            'description' => "Referral #{$referral->id} declined",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['referral_id' => $referral->id],
        ]);

        return back()->with('success', 'Referral declined.');
    }

    public function complete(Request $request, Referral $referral)
    {
        abort_unless($referral->type === 'external', 422, 'Only external referrals are closed this way.');
        abort_unless($referral->status === 'pending', 422, 'This referral is already closed.');

        $referral->update(['status' => 'completed', 'completed_at' => now()]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'referral_completed',
            'description' => "External referral #{$referral->id} marked as sent/completed",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['referral_id' => $referral->id],
        ]);

        return back()->with('success', 'Referral marked as completed.');
    }

    /**
     * Record the first doses given before the patient travels.
     *
     * The receiving facility needs to know what was already given and when, or
     * the patient is double-dosed on arrival or left untreated for the journey.
     */
    public function recordPreReferralTreatment(Request $request, Referral $referral)
    {
        $validated = $request->validate([
            'pre_referral_treatments' => 'required|array|min:1',
            'pre_referral_treatments.*' => [Rule::in(array_keys(Referral::PRE_REFERRAL_TREATMENTS))],
            'pre_referral_notes' => 'nullable|string',
            'pre_referral_given_at' => 'nullable|date|before_or_equal:now',
        ]);

        $referral->update([
            'pre_referral_treatments' => array_values($validated['pre_referral_treatments']),
            'pre_referral_notes' => $validated['pre_referral_notes'] ?? null,
            'pre_referral_given_at' => $validated['pre_referral_given_at'] ?? now(),
            'pre_referral_given_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'pre_referral_treatment_recorded',
            'description' => "Pre-referral treatment recorded for referral #{$referral->id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'referral_id' => $referral->id,
                'visit_id' => $referral->visit_id,
                'treatments' => $referral->pre_referral_treatments,
            ],
        ]);

        return back()->with('success', 'Pre-referral treatment recorded. It will print on the referral letter.');
    }

    public function print(Referral $referral)
    {
        $referral->load(['visit.patient', 'fromDepartment', 'toDepartment', 'referredBy']);

        return view('referrals.print', compact('referral'));
    }
}
