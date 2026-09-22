<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('status')->default('draft')->change(); // Overriding enum with string for flexibility, or use DB::statement for enum change
            // Actually, let's stick to string for now to avoid complexity of enum replacement in migrations
            $table->foreignId('locked_by')->nullable()->after('doctor_id')->constrained('users');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->integer('version')->default(1)->after('status');

            // Pregnancy Encounter Snapshot
            $table->string('pregnancy_status')->nullable()->after('finished_at'); // unknown, none, pregnant
            $table->date('lmp')->nullable()->after('pregnancy_status');
            $table->date('edd')->nullable()->after('lmp');
            $table->string('gestational_age')->nullable()->after('edd');
            $table->string('lactation_status')->nullable()->after('gestational_age'); // unknown, none, breastfeeding
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['locked_by', 'locked_at', 'version', 'pregnancy_status', 'lmp', 'edd', 'gestational_age', 'lactation_status']);
            // status reversion is tricky if changed to string, keeping as is for now in down()
        });
    }
};
