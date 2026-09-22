<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serial growth measurements. Plotting a child's own trajectory over time is
     * what catches faltering growth, so each measurement is kept rather than
     * overwriting a "current" weight.
     */
    public function up(): void
    {
        Schema::create('growth_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('measured_at');
            $table->unsignedInteger('age_months')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('head_circumference_cm', 5, 2)->nullable();
            $table->decimal('muac_cm', 5, 2)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_measurements');
    }
};
