<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two separate NHIS gaps.
 *
 * Exemptions: Ghana exempts pregnant women and children under five from NHIS
 * premiums and from many point-of-service charges. Nothing encoded that, so an
 * exempt patient was billed like anyone else.
 *
 * Tariffs: claims were valued at the facility's own price. NHIA reimburses at
 * the G-DRG tariff, which is a different number, so claims could never
 * reconcile. The facility price stays for cash patients; the tariff is what is
 * claimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('exemption_category')->nullable()->after('payment_method');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->decimal('nhis_tariff', 10, 2)->nullable()->after('is_nhis_covered');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->decimal('nhis_tariff', 10, 2)->nullable()->after('is_nhis_covered');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('is_exempt')->default(false)->after('payer');
            $table->string('exemption_reason')->nullable()->after('is_exempt');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('exemption_category');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('nhis_tariff');
        });

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn('nhis_tariff');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['is_exempt', 'exemption_reason']);
        });
    }
};
