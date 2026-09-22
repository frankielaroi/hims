<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Known interactions between two drugs or two drug classes. A rule may be
     * expressed either way round: by specific drug where the pair matters, or by
     * class where the whole group behaves the same.
     */
    public function up(): void
    {
        Schema::create('drug_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('drug_class')->nullable();
            $table->foreignId('interacts_with_drug_id')->nullable()->constrained('drugs')->cascadeOnDelete();
            $table->string('interacts_with_class')->nullable();
            $table->string('severity')->default('moderate'); // minor, moderate, major, contraindicated
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
