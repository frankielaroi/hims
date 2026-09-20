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
        Schema::create('nhis_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nhis_claim_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_item_id')->constrained();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhis_claim_items');
    }
};
