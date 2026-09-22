<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_chronic_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name');
            $table->string('strength')->nullable();
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_chronic_medications');
    }
};
