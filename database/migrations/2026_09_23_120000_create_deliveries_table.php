<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One birth episode. This is the legal record that a woman delivered in this
 * facility, and the row every maternal and perinatal return is counted from.
 *
 * Babies live in `newborn_records` rather than here, because a delivery can
 * produce more than one and they do not always share an outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('labour_onset_at')->nullable();
            $table->timestamp('admitted_in_labour_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->unsignedSmallInteger('gestation_weeks')->nullable();
            $table->string('mode')->nullable();
            $table->string('presentation')->nullable();
            $table->string('place')->default('facility');

            // Active management of the third stage prevents most postpartum
            // haemorrhage, so whether it was done is recorded explicitly.
            $table->string('third_stage_management')->nullable();
            $table->boolean('uterotonic_given')->default(false);
            $table->string('placenta_state')->nullable();
            $table->timestamp('placenta_delivered_at')->nullable();

            $table->unsignedInteger('blood_loss_ml')->nullable();
            $table->string('perineum')->nullable();
            $table->json('complications')->nullable();

            $table->string('status')->default('in_labour');
            $table->foreignId('delivered_by')->nullable()->constrained('users');
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
