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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_id')->unique(); // Unique system identifier

            // Identity
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->integer('estimated_age')->nullable(); // Fallback for unknown DOB
            $table->boolean('is_age_estimated')->default(false);
            $table->enum('sex', ['male', 'female', 'other']);
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable(); // Free text for landmarks/community

            // Next of Kin
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_contact')->nullable();

            // Insurance/Payment
            $table->string('nhis_number')->nullable()->index();
            $table->date('nhis_expiry')->nullable();
            $table->string('payment_method')->default('cash'); // cash, nhis, corporate, etc.

            // Clinical Safety Data
            $table->json('allergies')->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->string('blood_group')->nullable();

            $table->timestamps();
            $table->softDeletes(); // For potential merge operations (never hard delete)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
