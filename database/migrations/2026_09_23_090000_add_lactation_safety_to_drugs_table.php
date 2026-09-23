<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pregnancy_category` has existed since the safety-limits migration but nothing
 * ever read it. These columns give it a narrative counterpart and a lactation
 * equivalent, mirroring the renal/hepatic guidance pair the engine already uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->text('pregnancy_guidance')->nullable()->after('pregnancy_category');
            $table->string('lactation_category')->nullable()->after('pregnancy_guidance');
            $table->text('lactation_guidance')->nullable()->after('lactation_category');
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn(['pregnancy_guidance', 'lactation_category', 'lactation_guidance']);
        });
    }
};
