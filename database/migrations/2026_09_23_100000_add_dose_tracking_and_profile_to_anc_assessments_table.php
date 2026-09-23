<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IPTp and tetanus were recorded as booleans, which cannot show what Ghana's
 * antenatal programme is actually audited on: at least three doses of IPTp-SP,
 * and where the woman has reached on the TT1-TT5 schedule. A yes/no per contact
 * can never prove a third dose was given, so both become dose numbers.
 *
 * The antenatal profile — the booking bloods every pregnancy needs — had
 * nowhere to live at all, and is added alongside the obstetric history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anc_assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('iptp_dose_number')->nullable()->after('urinalysis_protein');
            $table->unsignedTinyInteger('tt_dose_number')->nullable()->after('iptp_dose_number');
            $table->boolean('ifa_given')->default(false)->after('tt_dose_number');
            $table->boolean('deworming_given')->default(false)->after('ifa_given');

            $table->unsignedTinyInteger('gravida')->nullable()->after('deworming_given');
            $table->unsignedTinyInteger('para')->nullable()->after('gravida');
            $table->unsignedTinyInteger('abortions')->nullable()->after('para');
            $table->unsignedTinyInteger('living_children')->nullable()->after('abortions');

            $table->string('blood_group')->nullable()->after('living_children');
            $table->string('rhesus')->nullable()->after('blood_group');
            $table->string('sickling_status')->nullable()->after('rhesus');
            $table->string('hiv_status')->nullable()->after('sickling_status');
            $table->string('syphilis_status')->nullable()->after('hiv_status');
            $table->string('hepatitis_b_status')->nullable()->after('syphilis_status');
            $table->string('urinalysis_glucose')->nullable()->after('hepatitis_b_status');
        });

        // A recorded dose is clinical history: carry the booleans across as a
        // first dose rather than dropping what was already entered.
        DB::table('anc_assessments')->where('iptp_given', true)->update(['iptp_dose_number' => 1]);
        DB::table('anc_assessments')->where('tt_given', true)->update(['tt_dose_number' => 1]);

        Schema::table('anc_assessments', function (Blueprint $table) {
            $table->dropColumn(['iptp_given', 'tt_given']);
        });
    }

    public function down(): void
    {
        Schema::table('anc_assessments', function (Blueprint $table) {
            $table->boolean('iptp_given')->default(false)->after('urinalysis_protein');
            $table->boolean('tt_given')->default(false)->after('iptp_given');
        });

        DB::table('anc_assessments')->whereNotNull('iptp_dose_number')->update(['iptp_given' => true]);
        DB::table('anc_assessments')->whereNotNull('tt_dose_number')->update(['tt_given' => true]);

        Schema::table('anc_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'iptp_dose_number',
                'tt_dose_number',
                'ifa_given',
                'deworming_given',
                'gravida',
                'para',
                'abortions',
                'living_children',
                'blood_group',
                'rhesus',
                'sickling_status',
                'hiv_status',
                'syphilis_status',
                'hepatitis_b_status',
                'urinalysis_glucose',
            ]);
        });
    }
};
