<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One baby's check at one postnatal contact. Twins are seen separately, so this
 * hangs off the newborn record rather than off the mother's contact alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postnatal_newborn_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postnatal_visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('newborn_record_id')->constrained()->onDelete('cascade');

            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('cord_state')->nullable();
            $table->string('feeding')->nullable();
            $table->boolean('jaundice')->default(false);
            $table->json('danger_signs')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['postnatal_visit_id', 'newborn_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postnatal_newborn_checks');
    }
};
