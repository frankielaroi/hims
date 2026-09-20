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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->onDelete('cascade');
            $table->foreignId('drug_id')->constrained();
            $table->string('dose'); // 500mg
            $table->string('route'); // Oral
            $table->string('frequency'); // TDS
            $table->integer('duration_days'); // 7
            $table->integer('quantity_to_dispense'); // 21
            $table->integer('quantity_dispensed')->default(0);
            $table->boolean('nhis_covered_snapshot')->default(false);
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
