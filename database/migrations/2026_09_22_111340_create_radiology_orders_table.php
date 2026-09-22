<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imaging requests and their reports. The lifecycle mirrors the laboratory:
     * the department performs and reports, then the ordering clinician reviews.
     */
    public function up(): void
    {
        Schema::create('radiology_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->constrained('users');
            $table->string('modality');            // xray, ultrasound, ct, mri, fluoroscopy
            $table->string('body_part');
            $table->text('clinical_indication')->nullable();
            $table->string('priority')->default('routine');   // routine, urgent, stat
            $table->string('status')->default('ordered');     // ordered, scheduled, performed, reported, reviewed, cancelled
            $table->boolean('contrast_required')->default(false);

            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at')->nullable();

            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->foreignId('reported_by')->nullable()->constrained('users');
            $table->timestamp('reported_at')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_orders');
    }
};
