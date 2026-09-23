<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widens the lifecycle beyond draft/finalized, and adds the editing lock,
     * note version and the pregnancy snapshot taken at this encounter.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            // A string rather than an enum: the lifecycle gains states over time
            // and rebuilding a CHECK constraint on every addition is not worth it.
            $table->string('status')->default('draft')->change();
            $table->foreignId('locked_by')->nullable()->after('doctor_id')->constrained('users');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->integer('version')->default(1)->after('updated_at');
            $table->string('pregnancy_status')->nullable()->after('version');
            $table->date('lmp')->nullable()->after('pregnancy_status');
            $table->date('edd')->nullable()->after('lmp');
            $table->string('gestational_age')->nullable()->after('edd');
            $table->string('lactation_status')->nullable()->after('gestational_age');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn([
                'locked_by', 'locked_at', 'version', 'pregnancy_status',
                'lmp', 'edd', 'gestational_age', 'lactation_status',
            ]);
            $table->enum('status', ['draft', 'finalized'])->default('draft')->change();
        });
    }
};
