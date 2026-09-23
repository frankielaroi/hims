<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Death in care. Cause follows the certificate structure so the record can
     * support statutory notification.
     */
    public function up(): void
    {
        Schema::create('death_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->timestamp('died_at');
            $table->string('place_of_death')->nullable();
            $table->string('immediate_cause');
            $table->string('underlying_cause')->nullable();
            $table->text('contributing_factors')->nullable();
            $table->string('manner')->default('natural');
            $table->boolean('referred_to_coroner')->default(false);
            $table->boolean('autopsy_requested')->default(false);
            $table->string('certificate_number')->nullable();
            $table->foreignId('certified_by')->constrained('users');
            $table->string('body_released_to')->nullable();
            $table->timestamp('body_released_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('death_records');
    }
};
