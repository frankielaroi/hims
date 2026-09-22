<?php

namespace App\Services;

use App\Models\Immunization;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The childhood immunisation schedule, and what a given child still needs.
 *
 * Due dates are worked out from the child's date of birth against the schedule
 * rather than stored, so a correction to the date of birth flows through.
 */
class ImmunizationSchedule
{
    /**
     * Ghana Expanded Programme on Immunisation routine childhood schedule.
     *
     * `age_weeks` is the earliest age the dose is due.
     *
     * @var list<array{code: string, name: string, dose: int, age_weeks: int}>
     */
    public const SCHEDULE = [
        ['code' => 'BCG', 'name' => 'BCG', 'dose' => 1, 'age_weeks' => 0],
        ['code' => 'OPV', 'name' => 'Oral Polio Vaccine', 'dose' => 0, 'age_weeks' => 0],
        ['code' => 'HEPB', 'name' => 'Hepatitis B birth dose', 'dose' => 1, 'age_weeks' => 0],

        ['code' => 'OPV', 'name' => 'Oral Polio Vaccine', 'dose' => 1, 'age_weeks' => 6],
        ['code' => 'PENTA', 'name' => 'Pentavalent (DTP-HepB-Hib)', 'dose' => 1, 'age_weeks' => 6],
        ['code' => 'PCV', 'name' => 'Pneumococcal Conjugate', 'dose' => 1, 'age_weeks' => 6],
        ['code' => 'ROTA', 'name' => 'Rotavirus', 'dose' => 1, 'age_weeks' => 6],

        ['code' => 'OPV', 'name' => 'Oral Polio Vaccine', 'dose' => 2, 'age_weeks' => 10],
        ['code' => 'PENTA', 'name' => 'Pentavalent (DTP-HepB-Hib)', 'dose' => 2, 'age_weeks' => 10],
        ['code' => 'PCV', 'name' => 'Pneumococcal Conjugate', 'dose' => 2, 'age_weeks' => 10],
        ['code' => 'ROTA', 'name' => 'Rotavirus', 'dose' => 2, 'age_weeks' => 10],

        ['code' => 'OPV', 'name' => 'Oral Polio Vaccine', 'dose' => 3, 'age_weeks' => 14],
        ['code' => 'PENTA', 'name' => 'Pentavalent (DTP-HepB-Hib)', 'dose' => 3, 'age_weeks' => 14],
        ['code' => 'PCV', 'name' => 'Pneumococcal Conjugate', 'dose' => 3, 'age_weeks' => 14],
        ['code' => 'IPV', 'name' => 'Inactivated Polio Vaccine', 'dose' => 1, 'age_weeks' => 14],

        ['code' => 'MR', 'name' => 'Measles-Rubella', 'dose' => 1, 'age_weeks' => 39],
        ['code' => 'YF', 'name' => 'Yellow Fever', 'dose' => 1, 'age_weeks' => 39],
        ['code' => 'MENA', 'name' => 'Meningococcal A', 'dose' => 1, 'age_weeks' => 78],
        ['code' => 'MR', 'name' => 'Measles-Rubella', 'dose' => 2, 'age_weeks' => 78],
    ];

    /**
     * The age past which the routine childhood schedule no longer applies.
     */
    public const SCHEDULE_UPPER_AGE_YEARS = 5;

    /**
     * Build the child's immunisation status: every scheduled dose, whether it has
     * been given, and whether it is due or overdue.
     *
     * @return Collection<int, array{code: string, name: string, dose: int, due_on: Carbon|null, given_at: Carbon|null, status: string}>
     */
    public function statusFor(Patient $patient): Collection
    {
        $given = Immunization::where('patient_id', $patient->id)
            ->get()
            ->keyBy(fn (Immunization $dose) => $dose->vaccine_code.':'.$dose->dose_number);

        $birth = $patient->date_of_birth ? Carbon::parse($patient->date_of_birth) : null;

        return collect(self::SCHEDULE)->map(function (array $entry) use ($given, $birth) {
            $key = $entry['code'].':'.$entry['dose'];
            $dose = $given->get($key);
            $dueOn = $birth?->copy()->addWeeks($entry['age_weeks']);

            return [
                'code' => $entry['code'],
                'name' => $entry['name'],
                'dose' => $entry['dose'],
                'due_on' => $dueOn,
                'given_at' => $dose?->given_at,
                'status' => $this->status($dose, $dueOn),
            ];
        });
    }

    /**
     * Doses that are due or overdue right now.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function outstandingFor(Patient $patient): Collection
    {
        return $this->statusFor($patient)
            ->filter(fn (array $entry) => in_array($entry['status'], ['due', 'overdue'], true))
            ->values();
    }

    /**
     * Whether the routine childhood schedule still applies to this patient.
     */
    public function appliesTo(Patient $patient): bool
    {
        return $patient->age !== null && $patient->age <= self::SCHEDULE_UPPER_AGE_YEARS;
    }

    /**
     * A dose is overdue once it is more than a month past due.
     */
    protected function status(?Immunization $dose, ?Carbon $dueOn): string
    {
        if ($dose !== null) {
            return 'given';
        }

        if ($dueOn === null) {
            return 'unknown';
        }

        if ($dueOn->isFuture()) {
            return 'scheduled';
        }

        return $dueOn->copy()->addMonth()->isPast() ? 'overdue' : 'due';
    }
}
