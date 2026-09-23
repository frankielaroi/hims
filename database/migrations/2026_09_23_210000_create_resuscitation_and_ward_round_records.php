<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two records the emergency and inpatient modules had nowhere to put.
 *
 * A resuscitation is the most scrutinised half hour in any hospital and had no
 * record at all — who led it, what was given, how many shocks, and when it
 * stopped.
 *
 * Ward rounds were equally absent: an admission recorded who came in and who
 * went out, with nothing in between, so there was no way to show a deteriorating
 * patient had been seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resuscitation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');

            $table->string('arrest_type');
            $table->boolean('was_witnessed')->default(false);
            $table->string('initial_rhythm')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->json('interventions')->nullable();
            $table->unsignedSmallInteger('shocks_delivered')->nullable();
            $table->unsignedSmallInteger('adrenaline_doses')->nullable();

            $table->string('outcome')->nullable();
            $table->foreignId('team_lead_id')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
        });

        Schema::create('ward_round_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->onDelete('cascade');
            $table->timestamp('rounded_at');

            // Kept as SOAP because that is how the paper notes are written and
            // how they are read back in a review.
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment');
            $table->text('plan');

            $table->boolean('seen_by_senior')->default(false);
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['admission_id', 'rounded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ward_round_notes');
        Schema::dropIfExists('resuscitation_records');
    }
};
