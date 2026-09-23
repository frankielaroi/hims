<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHO Emergency Triage Assessment and Treatment, which Ghana uses for children
 * under five. Triage recorded a single priority chosen by the nurse; ETAT asks
 * for the signs that decide it, and the priority follows from them.
 *
 * Blood glucose, MUAC and AVPU join the observations because ETAT turns on
 * them: glucose for any child with coma or convulsions, MUAC for wasting, and
 * AVPU for the coma sign itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vitals_records', function (Blueprint $table) {
            $table->decimal('blood_glucose', 5, 2)->nullable()->after('spo2');
            $table->decimal('muac_cm', 5, 2)->nullable()->after('height');
            $table->string('avpu')->nullable()->after('muac_cm');
        });

        Schema::table('triage_assessments', function (Blueprint $table) {
            $table->json('etat_emergency_signs')->nullable()->after('priority_level');
            $table->json('etat_priority_signs')->nullable()->after('etat_emergency_signs');
        });
    }

    public function down(): void
    {
        Schema::table('vitals_records', function (Blueprint $table) {
            $table->dropColumn(['blood_glucose', 'muac_cm', 'avpu']);
        });

        Schema::table('triage_assessments', function (Blueprint $table) {
            $table->dropColumn(['etat_emergency_signs', 'etat_priority_signs']);
        });
    }
};
