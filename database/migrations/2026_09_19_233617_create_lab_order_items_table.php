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
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('lab_test_id')->constrained();
            $table->string('specimen_type');
            $table->enum('specimen_status', ['pending_collection', 'collected', 'rejected'])->default('pending_collection');
            $table->foreignId('specimen_collected_by')->nullable()->constrained('users');
            $table->timestamp('specimen_collected_at')->nullable();
            $table->text('result_value')->nullable();
            $table->enum('result_flag', ['normal', 'abnormal', 'critical'])->nullable();
            $table->string('reference_range')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_order_items');
    }
};
