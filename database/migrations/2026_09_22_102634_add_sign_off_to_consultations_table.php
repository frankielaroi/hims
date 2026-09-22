<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the clinical sign-off trail and autosave marker used by the
     * consultation lifecycle (draft -> ready_for_sign_off -> signed -> finalized).
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('signed_by')->nullable()->after('version')->constrained('users');
            $table->timestamp('signed_at')->nullable()->after('signed_by');
            $table->timestamp('autosaved_at')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropForeign(['signed_by']);
            $table->dropColumn(['signed_by', 'signed_at', 'autosaved_at']);
        });
    }
};
