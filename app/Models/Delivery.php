<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const MODES = [
        'spontaneous_vertex',
        'assisted_breech',
        'vacuum',
        'forceps',
        'caesarean',
    ];

    /**
     * @var list<string>
     */
    public const PLACES = ['facility', 'home', 'en_route'];

    /**
     * @var list<string>
     */
    public const THIRD_STAGE_MANAGEMENT = ['active', 'physiological'];

    /**
     * @var list<string>
     */
    public const PLACENTA_STATES = ['complete', 'incomplete', 'retained'];

    /**
     * @var list<string>
     */
    public const PERINEUM_STATES = [
        'intact',
        'episiotomy',
        'first_degree_tear',
        'second_degree_tear',
        'third_degree_tear',
        'fourth_degree_tear',
    ];

    /**
     * @var list<string>
     */
    public const COMPLICATIONS = [
        'postpartum_haemorrhage',
        'obstructed_labour',
        'pre_eclampsia',
        'eclampsia',
        'ruptured_uterus',
        'cord_prolapse',
        'shoulder_dystocia',
        'retained_placenta',
        'sepsis',
    ];

    public const STATUS_IN_LABOUR = 'in_labour';

    public const STATUS_DELIVERED = 'delivered';

    /**
     * Blood loss at or above this is postpartum haemorrhage after a vaginal
     * birth. The threshold after caesarean is 1000 ml.
     */
    public const PPH_BLOOD_LOSS_ML = 500;

    public const PPH_BLOOD_LOSS_ML_CAESAREAN = 1000;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'admission_id',
        'labour_onset_at',
        'admitted_in_labour_at',
        'delivered_at',
        'gestation_weeks',
        'mode',
        'presentation',
        'place',
        'third_stage_management',
        'uterotonic_given',
        'placenta_state',
        'placenta_delivered_at',
        'blood_loss_ml',
        'perineum',
        'complications',
        'status',
        'delivered_by',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'labour_onset_at' => 'datetime',
            'admitted_in_labour_at' => 'datetime',
            'delivered_at' => 'datetime',
            'placenta_delivered_at' => 'datetime',
            'uterotonic_given' => 'boolean',
            'complications' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function newborns(): HasMany
    {
        return $this->hasMany(NewbornRecord::class)->orderBy('birth_order');
    }

    public function partographEntries(): HasMany
    {
        return $this->hasMany(PartographEntry::class)->orderBy('recorded_at');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function postnatalVisits(): HasMany
    {
        return $this->hasMany(PostnatalVisit::class)->orderBy('contact_number');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(TheatreOperation::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCaesarean(): bool
    {
        return $this->mode === 'caesarean';
    }

    /**
     * Where the mother is, read through the admission rather than stored, so a
     * bed transfer is reflected without touching the delivery.
     */
    public function bed(): ?Bed
    {
        return $this->visit?->admission?->bed;
    }

    public function wardName(): ?string
    {
        return $this->bed()?->ward?->name;
    }

    /**
     * Ward and bed as one readable line, for the labour ward board.
     */
    public function location(): string
    {
        $bed = $this->bed();

        if ($bed === null) {
            return 'No bed assigned';
        }

        return trim(($bed->ward?->name ?? 'Unknown ward').' — bed '.$bed->bed_number);
    }

    /**
     * Blood loss past the threshold for how she delivered.
     */
    public function hasPostpartumHaemorrhage(): bool
    {
        if ($this->blood_loss_ml === null) {
            return false;
        }

        $threshold = $this->mode === 'caesarean'
            ? self::PPH_BLOOD_LOSS_ML_CAESAREAN
            : self::PPH_BLOOD_LOSS_ML;

        return $this->blood_loss_ml >= $threshold;
    }

    /**
     * Findings on this delivery that need acting on or reporting.
     *
     * @return list<string>
     */
    public function alerts(): array
    {
        $alerts = [];

        if ($this->hasPostpartumHaemorrhage()) {
            $alerts[] = 'Postpartum haemorrhage ('.$this->blood_loss_ml.' ml)';
        }

        if ($this->isDelivered() && ! $this->uterotonic_given) {
            $alerts[] = 'No uterotonic recorded — active management of the third stage prevents most postpartum haemorrhage';
        }

        if (in_array($this->placenta_state, ['incomplete', 'retained'], true)) {
            $alerts[] = 'Placenta '.$this->placenta_state.' — review for retained products';
        }

        if (in_array($this->perineum, ['third_degree_tear', 'fourth_degree_tear'], true)) {
            $alerts[] = 'Severe perineal tear — needs repair by an experienced clinician';
        }

        if ($this->gestation_weeks !== null && $this->gestation_weeks < 37 && $this->isDelivered()) {
            $alerts[] = 'Preterm delivery at '.$this->gestation_weeks.' weeks';
        }

        foreach ((array) $this->complications as $complication) {
            $alerts[] = 'Complication: '.ucfirst(str_replace('_', ' ', $complication));
        }

        if ($this->newborns->contains(fn (NewbornRecord $baby) => $baby->isStillbirth())) {
            $alerts[] = 'Stillbirth — notifiable perinatal outcome';
        }

        return $alerts;
    }
}
