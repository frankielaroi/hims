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
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('bed_id')->constrained();
            $table->string('admitting_diagnosis')->nullable();
            $table->foreignId('admitted_by')->constrained('users');
            $table->timestamp('admission_date');
            $table->enum('status', ['admitted', 'discharged'])->default('admitted');
            $table->timestamp('discharge_date')->nullable();
            $table->text('discharge_notes')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
