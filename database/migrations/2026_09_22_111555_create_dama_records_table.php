<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discharge Against Medical Advice. The clinical and legal record of what
     * the patient was told and who witnessed it, kept separately from the
     * visit outcome because it has to stand on its own.
     */
    public function up(): void
    {
        Schema::create('dama_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->text('reason_given')->nullable();
            $table->text('risks_explained');
            $table->boolean('patient_has_capacity')->default(true);
            $table->boolean('patient_signature_obtained')->default(false);
            $table->string('witness_name');
            $table->string('witness_relationship')->nullable();
            $table->foreignId('counselled_by')->constrained('users');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('left_at');
            $table->timestamps();

            $table->unique('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dama_records');
    }
};
