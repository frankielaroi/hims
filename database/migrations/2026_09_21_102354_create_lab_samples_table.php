<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A barcoded specimen. Several ordered tests can run off one sample, so the
     * sample is tracked separately from the order items.
     */
    public function up(): void
    {
        Schema::create('lab_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->onDelete('cascade');
            $table->string('barcode_number');
            $table->string('specimen_type');
            $table->foreignId('collected_by')->nullable()->constrained('users');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['pending', 'collected', 'received', 'rejected', 'processed'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_samples');
    }
};
