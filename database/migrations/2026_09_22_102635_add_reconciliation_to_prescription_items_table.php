<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a prescribed item back to the chronic medication it continues so
     * medication reconciliation is auditable.
     */
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->foreignId('chronic_medication_id')->nullable()->after('drug_id')
                ->constrained('patient_chronic_medications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropForeign(['chronic_medication_id']);
            $table->dropColumn('chronic_medication_id');
        });
    }
};
