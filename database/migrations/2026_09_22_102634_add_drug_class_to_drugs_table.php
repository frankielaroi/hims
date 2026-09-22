<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drug class (e.g. penicillin, sulfonamide, nsaid) backs class-based
     * allergy matching in the allergy engine.
     */
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->string('drug_class')->nullable()->after('formulation');
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('drug_class');
        });
    }
};
