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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->onDelete('cascade');
            $table->foreignId('drug_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('movement_type', ['receipt', 'dispense', 'adjustment', 'expiry_writeoff']);
            $table->integer('quantity_change'); // negative for stock leaving, positive for stock arriving
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
