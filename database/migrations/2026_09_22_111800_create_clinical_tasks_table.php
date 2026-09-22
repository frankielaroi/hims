<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outstanding clinical work that would otherwise only live in someone's head:
     * results nobody has read, critical findings nobody has acknowledged, and
     * follow-ups that were promised to a patient.
     *
     * Tasks are keyed by `source_type` + `source_id` so the generator can be run
     * repeatedly without creating duplicates.
     */
    public function up(): void
    {
        Schema::create('clinical_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                       // pending_result, critical_result, unreviewed_report, follow_up
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('routine'); // routine, urgent, critical
            $table->string('status')->default('open');      // open, done, cancelled
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
