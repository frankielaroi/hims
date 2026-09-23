<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A diagnosis is more than a code: its role in the encounter and how sure
     * the clinician is both matter.
     */
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('type')->default('primary')->after('updated_at');
            $table->string('certainty')->default('confirmed')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn(['type', 'certainty']);
        });
    }
};
