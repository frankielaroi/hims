<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Doses actually given. What is still due is worked out against the
     * schedule rather than stored and kept in step by hand.
     */
    public function up(): void
    {
        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vaccine_code');
            $table->string('vaccine_name');
            $table->unsignedSmallInteger('dose_number')->default(1);
            $table->timestamp('given_at');
            $table->string('batch_number')->nullable();
            $table->string('site')->nullable();
            $table->text('adverse_reaction')->nullable();
            $table->foreignId('given_by')->constrained('users');
            $table->timestamps();

            $table->unique(['patient_id', 'vaccine_code', 'dose_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immunizations');
    }
};
