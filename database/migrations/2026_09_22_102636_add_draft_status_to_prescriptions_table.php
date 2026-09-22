<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prescriptions saved alongside a consultation draft are themselves drafts.
     * The original enum omitted that state, so widen it to a string to stay in
     * step with the consultation lifecycle.
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
