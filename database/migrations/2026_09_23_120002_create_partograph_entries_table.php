<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serial observations through labour — the partograph.
 *
 * Nothing is plotted here. The alert and action lines are worked out from the
 * readings by PartographMonitor, so a corrected entry moves the lines with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partograph_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->timestamp('recorded_at');

            $table->unsignedTinyInteger('cervical_dilatation_cm')->nullable();
            $table->unsignedTinyInteger('descent_fifths')->nullable();
            $table->unsignedTinyInteger('contractions_per_ten_minutes')->nullable();
            $table->string('contraction_strength')->nullable();

            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->string('liquor')->nullable();
            $table->string('moulding')->nullable();

            $table->unsignedSmallInteger('maternal_pulse')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('urine_protein')->nullable();

            $table->decimal('oxytocin_units', 5, 2)->nullable();
            $table->string('drugs_given')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['delivery_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partograph_entries');
    }
};
