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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['internal', 'external']);
            $table->foreignId('from_department_id')->constrained('departments');
            $table->foreignId('to_department_id')->nullable()->constrained('departments');
            $table->string('external_facility_name')->nullable();
            $table->string('external_facility_contact')->nullable();
            $table->text('reason');
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', ['pending', 'accepted', 'declined', 'completed'])->default('pending');
            $table->foreignId('referred_by')->constrained('users');
            $table->foreignId('accepted_by')->nullable()->constrained('users');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('feedback_notes')->nullable();
            $table->foreignId('new_visit_id')->nullable()->constrained('visits');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
