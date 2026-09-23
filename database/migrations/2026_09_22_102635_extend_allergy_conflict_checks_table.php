<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how a conflict was detected and whether it was overridden.
     * Justification becomes nullable: a non-severe conflict is logged as an
     * acknowledged warning without narrative text.
     */
    public function up(): void
    {
        Schema::table('allergy_conflict_checks', function (Blueprint $table) {
            $table->text('doctor_justification')->nullable()->change();
            $table->string('match_type')->default('exact');
            $table->string('severity')->default('moderate');
            $table->boolean('was_overridden')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('allergy_conflict_checks', function (Blueprint $table) {
            $table->dropColumn(['match_type', 'severity', 'was_overridden']);
        });
    }
};
