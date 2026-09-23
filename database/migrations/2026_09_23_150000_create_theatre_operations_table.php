<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The operation record. `caesarean` was selectable as a mode of delivery with
 * nothing behind it — no surgeon, no anaesthetic, no findings — which is the
 * part of the notes a medico-legal review actually asks for.
 *
 * `delivery_id` links a caesarean back to its birth episode; other operations
 * leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theatre_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();

            $table->string('procedure');
            $table->text('indication')->nullable();
            $table->string('urgency')->default('elective');
            $table->string('anaesthesia_type')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->foreignId('surgeon_id')->nullable()->constrained('users');
            $table->foreignId('anaesthetist_id')->nullable()->constrained('users');
            $table->string('scrub_nurse')->nullable();

            $table->text('findings')->nullable();
            $table->text('procedure_notes')->nullable();
            $table->json('complications')->nullable();
            $table->unsignedInteger('estimated_blood_loss_ml')->nullable();
            $table->boolean('specimen_sent')->default(false);
            $table->boolean('swab_count_correct')->nullable();

            $table->string('status')->default('scheduled');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theatre_operations');
    }
};
