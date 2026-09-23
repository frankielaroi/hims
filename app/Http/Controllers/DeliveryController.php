<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\NewbornRecord;
use App\Models\PartographEntry;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\PartographMonitor;
use App\Services\PostnatalSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function __construct(protected PartographMonitor $partograph) {}

    /**
     * The labour ward board: who is in labour, and who delivered today.
     */
    public function index()
    {
        $inLabour = Delivery::with(['patient', 'visit', 'visit.admission.bed.ward'])
            ->where('status', Delivery::STATUS_IN_LABOUR)
            ->orderBy('admitted_in_labour_at')
            ->get();

        $delivered = Delivery::with(['patient', 'newborns', 'visit.admission.bed.ward'])
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereDate('delivered_at', today())
            ->latest('delivered_at')
            ->get();

        return view('deliveries.index', compact('inLabour', 'delivered'));
    }

    /**
     * Open a labour record for a woman who has been admitted in labour.
     */
    public function store(Request $request, Visit $visit)
    {
        $validated = $request->validate([
            'labour_onset_at' => 'nullable|date|before_or_equal:now',
            'gestation_weeks' => 'nullable|integer|min:20|max:45',
            'presentation' => 'nullable|string|max:64',
        ]);

        $existing = Delivery::where('visit_id', $visit->id)->first();

        if ($existing !== null) {
            return redirect()->route('deliveries.show', $existing)
                ->with('warning', 'A labour record is already open for this visit.');
        }

        $delivery = Delivery::create(array_merge($validated, [
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'admission_id' => $visit->admission?->id,
            'admitted_in_labour_at' => now(),
            'status' => Delivery::STATUS_IN_LABOUR,
            'recorded_by' => auth()->id(),
        ]));

        $this->audit($request, 'labour_record_opened', "Labour record opened for {$visit->patient->full_name}.", $delivery);

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Labour record opened. Record partograph observations as labour progresses.');
    }

    public function show(Delivery $delivery)
    {
        $delivery->load([
            'patient',
            'visit.admission.bed.ward',
            'newborns.patient',
            'partographEntries.recordedBy',
            'postnatalVisits.newbornChecks.newbornRecord',
            'operations.surgeon',
            'deliveredBy',
        ]);

        $progress = $this->partograph->progressFor($delivery);
        $warnings = $this->partograph->warningsFor($delivery);
        $postnatal = app(PostnatalSchedule::class)->statusFor($delivery);

        return view('deliveries.show', compact('delivery', 'progress', 'warnings', 'postnatal'));
    }

    /**
     * One set of partograph observations.
     */
    public function storeObservation(Request $request, Delivery $delivery)
    {
        abort_if($delivery->isDelivered(), 422, 'This labour has ended; observations cannot be added.');

        $validated = $request->validate([
            'recorded_at' => 'nullable|date|before_or_equal:now',
            'cervical_dilatation_cm' => 'nullable|integer|min:0|max:10',
            'descent_fifths' => 'nullable|integer|min:0|max:5',
            'contractions_per_ten_minutes' => 'nullable|integer|min:0|max:10',
            'contraction_strength' => ['nullable', Rule::in(PartographEntry::CONTRACTION_STRENGTHS)],
            'fetal_heart_rate' => 'nullable|integer|min:40|max:220',
            'liquor' => ['nullable', Rule::in(PartographEntry::LIQUOR_STATES)],
            'moulding' => ['nullable', Rule::in(PartographEntry::MOULDING_GRADES)],
            'maternal_pulse' => 'nullable|integer|min:20|max:250',
            'systolic_bp' => 'nullable|integer|min:50|max:300',
            'diastolic_bp' => 'nullable|integer|min:30|max:200',
            'temperature' => 'nullable|numeric|between:30,45',
            'urine_protein' => 'nullable|string|max:32',
            'oxytocin_units' => 'nullable|numeric|min:0|max:100',
            'drugs_given' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $entry = $delivery->partographEntries()->create(array_merge($validated, [
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'recorded_by' => auth()->id(),
        ]));

        $progress = $this->partograph->progressFor($delivery->refresh());
        $messages = array_merge($entry->warnings(), []);

        if (in_array($progress['status'], [PartographMonitor::STATUS_ALERT, PartographMonitor::STATUS_ACTION], true)) {
            $messages[] = $progress['message'];

            $this->audit(
                $request,
                'partograph_'.$progress['status'].'_line_crossed',
                $progress['message'],
                $delivery
            );
        }

        if ($messages !== []) {
            return back()->with('warning', 'Observation recorded. '.implode(' ', $messages));
        }

        return back()->with('success', 'Observation recorded.');
    }

    /**
     * Close the labour: how she delivered, and every baby.
     */
    public function storeOutcome(Request $request, Delivery $delivery)
    {
        abort_if($delivery->isDelivered(), 422, 'This delivery has already been recorded.');

        $validated = $request->validate([
            'delivered_at' => 'required|date|before_or_equal:now',
            'mode' => ['required', Rule::in(Delivery::MODES)],
            'place' => ['required', Rule::in(Delivery::PLACES)],
            'gestation_weeks' => 'nullable|integer|min:20|max:45',
            'third_stage_management' => ['nullable', Rule::in(Delivery::THIRD_STAGE_MANAGEMENT)],
            'uterotonic_given' => 'nullable|boolean',
            'placenta_state' => ['nullable', Rule::in(Delivery::PLACENTA_STATES)],
            'placenta_delivered_at' => 'nullable|date|before_or_equal:now',
            'blood_loss_ml' => 'nullable|integer|min:0|max:10000',
            'perineum' => ['nullable', Rule::in(Delivery::PERINEUM_STATES)],
            'complications' => 'nullable|array',
            'complications.*' => [Rule::in(Delivery::COMPLICATIONS)],
            'notes' => 'nullable|string',

            'newborns' => 'required|array|min:1',
            'newborns.*.sex' => 'required|in:male,female,other',
            'newborns.*.outcome' => ['required', Rule::in(NewbornRecord::OUTCOMES)],
            'newborns.*.birth_weight_grams' => 'nullable|integer|min:200|max:7000',
            'newborns.*.apgar_one_minute' => 'nullable|integer|min:0|max:10',
            'newborns.*.apgar_five_minutes' => 'nullable|integer|min:0|max:10',
            'newborns.*.apgar_ten_minutes' => 'nullable|integer|min:0|max:10',
            'newborns.*.resuscitation_required' => 'nullable|boolean',
            'newborns.*.resuscitation_detail' => 'nullable|string',
            'newborns.*.breastfed_within_hour' => 'nullable|boolean',
            'newborns.*.vitamin_k_given' => 'nullable|boolean',
            'newborns.*.eye_prophylaxis_given' => 'nullable|boolean',
            'newborns.*.congenital_anomaly' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $delivery) {
            $delivery->update([
                'delivered_at' => $validated['delivered_at'],
                'mode' => $validated['mode'],
                'place' => $validated['place'],
                'gestation_weeks' => $validated['gestation_weeks'] ?? $delivery->gestation_weeks,
                'third_stage_management' => $validated['third_stage_management'] ?? null,
                'uterotonic_given' => (bool) ($validated['uterotonic_given'] ?? false),
                'placenta_state' => $validated['placenta_state'] ?? null,
                'placenta_delivered_at' => $validated['placenta_delivered_at'] ?? null,
                'blood_loss_ml' => $validated['blood_loss_ml'] ?? null,
                'perineum' => $validated['perineum'] ?? null,
                'complications' => array_values($validated['complications'] ?? []),
                'notes' => $validated['notes'] ?? $delivery->notes,
                'status' => Delivery::STATUS_DELIVERED,
                'delivered_by' => auth()->id(),
            ]);

            // Birth order is assigned here rather than taken from the form, so
            // twins are always numbered in the order they were entered.
            foreach (array_values($validated['newborns']) as $index => $baby) {
                $delivery->newborns()->create([
                    'birth_order' => $index + 1,
                    'sex' => $baby['sex'],
                    'outcome' => $baby['outcome'],
                    'birth_weight_grams' => $baby['birth_weight_grams'] ?? null,
                    'apgar_one_minute' => $baby['apgar_one_minute'] ?? null,
                    'apgar_five_minutes' => $baby['apgar_five_minutes'] ?? null,
                    'apgar_ten_minutes' => $baby['apgar_ten_minutes'] ?? null,
                    'resuscitation_required' => (bool) ($baby['resuscitation_required'] ?? false),
                    'resuscitation_detail' => $baby['resuscitation_detail'] ?? null,
                    'breastfed_within_hour' => (bool) ($baby['breastfed_within_hour'] ?? false),
                    'vitamin_k_given' => (bool) ($baby['vitamin_k_given'] ?? false),
                    'eye_prophylaxis_given' => (bool) ($baby['eye_prophylaxis_given'] ?? false),
                    'congenital_anomaly' => $baby['congenital_anomaly'] ?? null,
                ]);
            }
        });

        $delivery->refresh()->load('newborns');

        $this->audit(
            $request,
            'delivery_recorded',
            "Delivery recorded for {$delivery->patient->full_name} ({$delivery->newborns->count()} baby/babies).",
            $delivery
        );

        $alerts = $delivery->alerts();

        if ($alerts !== []) {
            return redirect()->route('deliveries.show', $delivery)
                ->with('warning', 'Delivery recorded. '.implode('; ', $alerts).'.');
        }

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Delivery recorded.');
    }

    /**
     * Give a live-born baby its own record in the registry, which is what links
     * mother to child and gives immunisation and growth somewhere to live.
     */
    public function registerNewborn(Request $request, Delivery $delivery, NewbornRecord $newborn)
    {
        abort_unless($newborn->delivery_id === $delivery->id, 404);
        abort_unless($newborn->isLiveBirth(), 422, 'Only a live birth can be registered as a patient.');
        abort_if($newborn->patient_id !== null, 422, 'This baby already has a patient record.');

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
        ]);

        $baby = DB::transaction(function () use ($validated, $delivery, $newborn) {
            $baby = Patient::create([
                'hospital_id' => Patient::generateHospitalId(),
                'full_name' => $validated['full_name'],
                'date_of_birth' => $delivery->delivered_at->toDateString(),
                'is_age_estimated' => false,
                'sex' => $newborn->sex,
                'payment_method' => $delivery->patient->payment_method,
                'phone_number' => $delivery->patient->phone_number,
                'address' => $delivery->patient->address,
                'next_of_kin_name' => $delivery->patient->full_name,
                'next_of_kin_contact' => $delivery->patient->phone_number,
            ]);

            $newborn->update(['patient_id' => $baby->id]);

            return $baby;
        });

        $this->audit($request, 'newborn_registered', "Newborn {$baby->full_name} registered as {$baby->hospital_id}.", $delivery);

        return back()->with('success', "Baby registered as {$baby->hospital_id}. Birth-dose immunisations are now due on the child's record.");
    }

    protected function audit(Request $request, string $action, string $description, Delivery $delivery): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => substr($description, 0, 250),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'delivery_id' => $delivery->id,
                'patient_id' => $delivery->patient_id,
                'visit_id' => $delivery->visit_id,
            ],
        ]);
    }
}
