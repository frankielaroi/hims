<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interactions between two drugs or two classes. A rule stated one way round
     * applies both ways.
     */
    public function up(): void
    {
        Schema::create('drug_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('drug_class')->nullable();
            $table->foreignId('interacts_with_drug_id')->nullable()->constrained('drugs')->cascadeOnDelete();
            $table->string('interacts_with_class')->nullable();
            $table->string('severity')->default('moderate');
            $table->string('effect');
            $table->text('management')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['drug_id', 'interacts_with_drug_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_interactions');
    }
};
