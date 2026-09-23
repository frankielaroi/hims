<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One baby. A delivery can produce several, and twins do not always share an
 * outcome, so each is recorded separately with its own birth order.
 *
 * `patient_id` is the baby's own record in the registry. It stays null until
 * the baby is registered, which is what finally links mother to child.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newborn_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('birth_order')->default(1);
            $table->string('sex')->nullable();
            $table->unsignedInteger('birth_weight_grams')->nullable();

            // Apgar is scored at 1 and 5 minutes, and again at 10 when the
            // 5-minute score is low.
            $table->unsignedTinyInteger('apgar_one_minute')->nullable();
            $table->unsignedTinyInteger('apgar_five_minutes')->nullable();
            $table->unsignedTinyInteger('apgar_ten_minutes')->nullable();

            $table->string('outcome')->default('live_birth');
            $table->boolean('resuscitation_required')->default(false);
            $table->text('resuscitation_detail')->nullable();
            $table->boolean('breastfed_within_hour')->default(false);
            $table->boolean('vitamin_k_given')->default(false);
            $table->boolean('eye_prophylaxis_given')->default(false);
            $table->text('congenital_anomaly')->nullable();
            $table->timestamp('died_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'birth_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newborn_records');
    }
};
