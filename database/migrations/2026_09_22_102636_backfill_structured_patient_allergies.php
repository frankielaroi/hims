<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Moves the legacy free-text `patients.allergies` JSON list into the
     * structured `patient_allergies` table. The legacy column is left in place
     * rather than dropped so no recorded allergen is destroyed; clinical screens
     * now read the structured table instead.
     */
    public function up(): void
    {
        DB::table('patients')
            ->whereNotNull('allergies')
            ->orderBy('id')
            ->select('id', 'allergies')
            ->chunk(200, function ($patients) {
                foreach ($patients as $patient) {
                    $allergens = json_decode($patient->allergies ?? '[]', true);

                    if (! is_array($allergens)) {
                        continue;
                    }

                    foreach ($allergens as $allergen) {
                        $allergen = trim((string) $allergen);

                        if ($allergen === '') {
                            continue;
                        }

                        $exists = DB::table('patient_allergies')
                            ->where('patient_id', $patient->id)
                            ->whereRaw('LOWER(allergen) = ?', [strtolower($allergen)])
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('patient_allergies')->insert([
                            'patient_id' => $patient->id,
                            'allergen' => $allergen,
                            'reaction' => null,
                            'severity' => 'unknown',
                            'status' => 'active',
                            'recorded_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * Backfilled rows cannot be told apart from rows entered afterwards, so the
     * reverse migration intentionally does nothing rather than delete clinical data.
     */
    public function down(): void
    {
        //
    }
};
