<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHO Integrated Management of Newborn and Childhood Illness, for children from
 * two months to five years.
 *
 * IMCI works by asking a fixed set of questions and reading the answers off a
 * classification table, so what is stored here is the findings. The
 * classifications themselves are derived by ImciClassifier and kept as a
 * snapshot, because the chart the clinician acted on is part of the record even
 * if the tables are later revised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imci_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('age_months');

            // General danger signs — any one means urgent referral.
            $table->boolean('unable_to_drink')->default(false);
            $table->boolean('vomits_everything')->default(false);
            $table->boolean('convulsions')->default(false);
            $table->boolean('lethargic_or_unconscious')->default(false);
            $table->boolean('convulsing_now')->default(false);

            // Cough or difficult breathing
            $table->boolean('has_cough')->default(false);
            $table->unsignedSmallInteger('cough_days')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->boolean('chest_indrawing')->default(false);
            $table->boolean('stridor')->default(false);

            // Diarrhoea
            $table->boolean('has_diarrhoea')->default(false);
            $table->unsignedSmallInteger('diarrhoea_days')->nullable();
            $table->boolean('blood_in_stool')->default(false);
            $table->boolean('sunken_eyes')->default(false);
            $table->string('skin_pinch')->nullable();
            $table->boolean('restless_or_irritable')->default(false);
            $table->string('drinking')->nullable();

            // Fever
            $table->boolean('has_fever')->default(false);
            $table->unsignedSmallInteger('fever_days')->nullable();
            $table->string('malaria_test_result')->nullable();
            $table->boolean('stiff_neck')->default(false);
            $table->boolean('measles_rash')->default(false);

            // Ear problem
            $table->boolean('has_ear_problem')->default(false);
            $table->boolean('ear_pain')->default(false);
            $table->unsignedSmallInteger('ear_discharge_days')->nullable();
            $table->boolean('tender_swelling_behind_ear')->default(false);

            // Malnutrition and anaemia
            $table->decimal('muac_cm', 5, 2)->nullable();
            $table->boolean('oedema_both_feet')->default(false);
            $table->boolean('visible_severe_wasting')->default(false);
            $table->string('palmar_pallor')->nullable();

            $table->json('classifications')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imci_assessments');
    }
};
