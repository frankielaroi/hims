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
        Schema::create('visit_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->enum('outcome', ['discharge', 'admit', 'refer']);
            $table->date('follow_up_date')->nullable();
            $table->string('referral_destination')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->foreignId('doctor_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_outcomes');
    }
};
