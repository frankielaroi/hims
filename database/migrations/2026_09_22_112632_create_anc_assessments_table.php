<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One antenatal contact: what was found at the visit, as opposed to the
     * obstetric snapshot on the consultation.
     */
    public function up(): void
    {
        Schema::create('anc_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('contact_number')->default(1);
            $table->unsignedSmallInteger('gestational_age_weeks')->nullable();
            $table->decimal('fundal_height_cm', 5, 2)->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->string('presentation')->nullable();
            $table->unsignedSmallInteger('fetal_movements')->nullable();
            $table->decimal('maternal_weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->decimal('haemoglobin', 5, 2)->nullable();
            $table->string('urinalysis_protein')->nullable();
            $table->boolean('tt_given')->default(false);
            $table->boolean('iptp_given')->default(false);
            $table->boolean('itn_given')->default(false);
            $table->text('notes')->nullable();
            $table->date('next_contact_due')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anc_assessments');
    }
};
