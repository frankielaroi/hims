<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\NewbornRecord;
use App\Models\PostnatalNewbornCheck;
use App\Models\PostnatalVisit;
use App\Services\PostnatalSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostnatalController extends Controller
{
    public function __construct(protected PostnatalSchedule $schedule) {}

    /**
     * Every mother whose postnatal contact is due or already missed.
     */
    public function index()
    {
        $deliveries = Delivery::with(['patient', 'postnatalVisits', 'newborns'])
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', now()->subMonths(3))
            ->get();

        $outstanding = $deliveries
            ->map(fn (Delivery $delivery) => [
                'delivery' => $delivery,
                'contacts' => $this->schedule->outstandingFor($delivery),
            ])
            ->filter(fn (array $row) => $row['contacts'] !== [])
            ->values();

        return view('postnatal.index', compact('outstanding'));
    }

    /**
     * Record one postnatal contact: the mother, and each of her babies.
     */
    public function store(Request $request, Delivery $delivery)
    {
        abort_unless($delivery->isDelivered(), 422, 'Postnatal care begins after delivery.');

        $validated = $request->validate([
            'contact_number' => 'required|integer|min:1|max:'.count(PostnatalSchedule::CONTACTS),
            'seen_at' => 'nullable|date|before_or_equal:now',
            'temperature' => 'nullable|numeric|between:30,45',
            'pulse' => 'nullable|integer|between:20,250',
            'systolic_bp' => 'nullable|integer|between:50,300',
            'diastolic_bp' => 'nullable|integer|between:30,200',
            'haemoglobin' => 'nullable|numeric|between:1,25',
            'lochia' => ['nullable', Rule::in(PostnatalVisit::LOCHIA_STATES)],
            'uterus_involution' => ['nullable', Rule::in(PostnatalVisit::UTERUS_STATES)],
            'perineum_state' => ['nullable', Rule::in(PostnatalVisit::PERINEUM_STATES)],
            'breast_state' => ['nullable', Rule::in(PostnatalVisit::BREAST_STATES)],
            'emotional_wellbeing' => ['nullable', Rule::in(PostnatalVisit::EMOTIONAL_STATES)],
            'family_planning_counselled' => 'nullable|boolean',
            'family_planning_method' => 'nullable|string|max:120',
            'ifa_given' => 'nullable|boolean',
            'vitamin_a_given' => 'nullable|boolean',
            'notes' => 'nullable|string',

            'newborns' => 'nullable|array',
            'newborns.*.weight_grams' => 'nullable|integer|min:200|max:20000',
            'newborns.*.temperature' => 'nullable|numeric|between:25,45',
            'newborns.*.cord_state' => ['nullable', Rule::in(PostnatalNewbornCheck::CORD_STATES)],
            'newborns.*.feeding' => ['nullable', Rule::in(PostnatalNewbornCheck::FEEDING_STATES)],
            'newborns.*.jaundice' => 'nullable|boolean',
            'newborns.*.danger_signs' => 'nullable|array',
            'newborns.*.danger_signs.*' => [Rule::in(array_keys(PostnatalNewbornCheck::DANGER_SIGNS))],
            'newborns.*.notes' => 'nullable|string',
        ]);

        $alreadyRecorded = $delivery->postnatalVisits()
            ->where('contact_number', $validated['contact_number'])
            ->exists();

        if ($alreadyRecorded) {
            return back()->withInput()->withErrors([
                'contact_number' => 'That postnatal contact has already been recorded.',
            ]);
        }

        $postnatalVisit = DB::transaction(function () use ($validated, $delivery, $request) {
            $postnatalVisit = PostnatalVisit::create(array_merge(
                collect($validated)->except(['newborns'])->all(),
                [
                    'delivery_id' => $delivery->id,
                    'patient_id' => $delivery->patient_id,
                    'visit_id' => $delivery->visit_id,
                    'seen_at' => $validated['seen_at'] ?? now(),
                    'family_planning_counselled' => $request->boolean('family_planning_counselled'),
                    'ifa_given' => $request->boolean('ifa_given'),
                    'vitamin_a_given' => $request->boolean('vitamin_a_given'),
                    'recorded_by' => auth()->id(),
                ]
            ));

            // Only live babies are followed up; a stillbirth has no postnatal
            // check to make.
            foreach ($delivery->newborns as $baby) {
                if (! $baby->isLiveBirth()) {
                    continue;
                }

                $submitted = $validated['newborns'][$baby->id] ?? [];

                $postnatalVisit->newbornChecks()->create([
                    'newborn_record_id' => $baby->id,
                    'weight_grams' => $submitted['weight_grams'] ?? null,
                    'temperature' => $submitted['temperature'] ?? null,
                    'cord_state' => $submitted['cord_state'] ?? null,
                    'feeding' => $submitted['feeding'] ?? null,
                    'jaundice' => (bool) ($submitted['jaundice'] ?? false),
                    'danger_signs' => array_values($submitted['danger_signs'] ?? []),
                    'notes' => $submitted['notes'] ?? null,
                ]);
            }

            return $postnatalVisit;
        });

        $postnatalVisit->load('newbornChecks.newbornRecord');

        $alerts = $postnatalVisit->dangerSigns();

        foreach ($postnatalVisit->newbornChecks as $check) {
            foreach ($check->alerts() as $alert) {
                $alerts[] = 'Baby '.$check->newbornRecord->birth_order.': '.$alert;
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'postnatal_contact_recorded',
            'description' => "Postnatal contact {$validated['contact_number']} recorded for {$delivery->patient->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'delivery_id' => $delivery->id,
                'patient_id' => $delivery->patient_id,
                'contact_number' => $validated['contact_number'],
                'danger_signs' => $alerts,
            ],
        ]);

        if ($alerts !== []) {
            return back()->with('warning', 'Contact recorded. '.implode('; ', $alerts).'.');
        }

        return back()->with('success', "Postnatal contact {$validated['contact_number']} recorded.");
    }

    /**
     * Only live babies need a postnatal check.
     *
     * @return Collection<int, NewbornRecord>
     */
    public static function followableNewborns(Delivery $delivery)
    {
        return $delivery->newborns->filter(fn (NewbornRecord $baby) => $baby->isLiveBirth());
    }
}
