<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outstanding clinical work that would otherwise live only in someone's head.
     * Keyed on the source row so the generator reconciles rather than duplicates.
     */
    public function up(): void
    {
        Schema::create('clinical_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('routine');
            $table->string('status')->default('open');
            $table->timestamp('due_at')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'type']);
            $table->index(['status', 'priority', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_tasks');
    }
};
