<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organ function as assessed at this encounter, and the weight the dose
     * checks work from. Per consultation, because it changes.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('renal_impairment')->nullable()->after('autosaved_at');
            $table->string('hepatic_impairment')->nullable()->after('renal_impairment');
            $table->decimal('egfr', 6, 2)->nullable()->after('hepatic_impairment');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('egfr');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['renal_impairment', 'hepatic_impairment', 'egfr', 'weight_kg']);
        });
    }
};
