<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postnatal contacts on Ghana's 6-hour, 6-day, 6-week model.
 *
 * Most maternal and newborn deaths happen in the days right after birth, so the
 * contact that did not happen matters as much as the one that did. Due dates are
 * derived from the delivery rather than stored, so a corrected delivery time
 * moves the whole schedule.
 *
 * The mother is checked here; each baby is checked in
 * `postnatal_newborn_checks`, because twins are seen separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postnatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('contact_number');
            $table->timestamp('seen_at');

            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->decimal('haemoglobin', 5, 2)->nullable();

            $table->string('lochia')->nullable();
            $table->string('uterus_involution')->nullable();
            $table->string('perineum_state')->nullable();
            $table->string('breast_state')->nullable();
            $table->string('emotional_wellbeing')->nullable();

            $table->boolean('family_planning_counselled')->default(false);
            $table->string('family_planning_method')->nullable();
            $table->boolean('ifa_given')->default(false);
            $table->boolean('vitamin_a_given')->default(false);

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->unique(['delivery_id', 'contact_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postnatal_visits');
    }
};
