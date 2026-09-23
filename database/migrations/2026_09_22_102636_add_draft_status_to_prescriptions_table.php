<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A prescription saved beside a consultation draft is itself a draft, which
     * the original enum had no room for.
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'dispensed', 'partially_dispensed', 'cancelled'])
                ->default('active')->change();
        });
    }
};
