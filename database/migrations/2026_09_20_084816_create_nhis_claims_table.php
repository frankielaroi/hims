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
        Schema::create('nhis_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->onDelete('cascade');
            $table->string('claim_number')->unique();
            $table->enum('status', ['draft', 'submitted', 'vetted', 'paid', 'rejected'])->default('draft');
            $table->decimal('amount_claimed', 10, 2);
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('vetted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhis_claims');
    }
};
