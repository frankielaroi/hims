<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how each allergy conflict was detected and whether the clinician
     * overrode it. Justification becomes nullable because non-severe conflicts
     * are logged as acknowledged warnings without narrative text.
     */
    public function up(): void
    {
        Schema::table('allergy_conflict_checks', function (Blueprint $table) {
            $table->text('doctor_justification')->nullable()->change();
            $table->string('match_type')->default('exact')->after('drug_prescribed');
            $table->string('severity')->default('moderate')->after('match_type');
            $table->boolean('was_overridden')->default(false)->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('allergy_conflict_checks', function (Blueprint $table) {
            $table->dropColumn(['match_type', 'severity', 'was_overridden']);
            $table->text('doctor_justification')->nullable(false)->change();
        });
    }
};
