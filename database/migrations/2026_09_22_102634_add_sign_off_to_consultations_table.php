<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sign-off trail and autosave marker for the consultation lifecycle.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('signed_by')->nullable()->after('lactation_status')->constrained('users');
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
