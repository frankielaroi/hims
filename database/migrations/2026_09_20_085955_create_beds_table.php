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
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->onDelete('cascade');
            $table->string('bed_number');
            $table->enum('status', ['available', 'occupied', 'cleaning', 'maintenance'])->default('available');
            $table->timestamps();

            $table->unique(['ward_id', 'bed_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
