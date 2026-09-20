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
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name');
            $table->string('strength')->nullable(); // e.g. 500mg
            $table->string('formulation')->nullable(); // Tablet, Syrup, etc.
            $table->boolean('is_nhis_covered')->default(false);
            $table->decimal('cash_price', 10, 2)->default(0);
            $table->decimal('nhis_price', 10, 2)->default(0);
            $table->integer('stock_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
