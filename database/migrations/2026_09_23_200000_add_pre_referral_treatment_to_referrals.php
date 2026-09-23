<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-referral treatment.
 *
 * WHO and the Ghana STG require that a severely ill patient is given a first
 * dose before being sent anywhere — rectal or injectable artesunate for severe
 * malaria, a first antibiotic dose for suspected sepsis or meningitis, glucose
 * for hypoglycaemia. The receiving facility needs to know what was already
 * given and when, or the child is either double-dosed or left untreated during
 * a journey that can take hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->json('pre_referral_treatments')->nullable()->after('urgency');
            $table->text('pre_referral_notes')->nullable()->after('pre_referral_treatments');
            $table->timestamp('pre_referral_given_at')->nullable()->after('pre_referral_notes');
            $table->foreignId('pre_referral_given_by')->nullable()->after('pre_referral_given_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['pre_referral_given_by']);
            $table->dropColumn([
                'pre_referral_treatments',
                'pre_referral_notes',
                'pre_referral_given_at',
                'pre_referral_given_by',
            ]);
        });
    }
};
