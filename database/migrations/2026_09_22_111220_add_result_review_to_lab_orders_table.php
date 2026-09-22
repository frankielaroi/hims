<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the ordering clinician's review of returned results. Verification by
     * the laboratory says the numbers are correct; review says a clinician has
     * actually read them and decided what to do.
     */
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('verified_at')->constrained('users');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_action')->nullable()->after('reviewed_at');
            $table->text('review_note')->nullable()->after('review_action');
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_action', 'review_note']);
        });
    }
};
