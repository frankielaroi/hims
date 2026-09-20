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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained();
            $table->string('visit_number')->unique();
            $table->integer('queue_number'); // Scoped to dept + day
            $table->string('type'); // OPD General, Emergency, ANC, etc.
            $table->string('priority')->default('routine'); // routine, urgent, emergency
            $table->string('payment_method'); // cash, nhis, corporate
            $table->string('nhis_number_snapshot')->nullable();
            $table->date('nhis_expiry_snapshot')->nullable();
            $table->string('status')->default('waiting_triage'); // waiting_triage, in_triage, waiting_doctor, in_consultation, waiting_lab, lab_in_progress, waiting_pharmacy, completed, closed, abandoned
            $table->foreignId('created_by')->constrained('users');
            $table->text('reason_for_visit')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
