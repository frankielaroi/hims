<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Ghana Card PIN as an alternate key, and the demographics DHIMS2 returns
 * ask for.
 *
 * The PIN is nullable and unique: not every patient has a card, but no two
 * patients share one. Making it a first-class identifier now is what lets the
 * record be matched against a national registry later.
 *
 * Staff get their AHPC or NMC licence number, which is what HEFRA inspects when
 * it asks who is permitted to do what.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('ghana_card_pin')->nullable()->unique()->after('hospital_id');
            $table->string('occupation')->nullable()->after('address');
            $table->string('religion')->nullable()->after('occupation');
            $table->string('marital_status')->nullable()->after('religion');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('licence_body')->nullable()->after('department_id');
            $table->string('licence_number')->nullable()->after('licence_body');
            $table->date('licence_expires_on')->nullable()->after('licence_number');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['ghana_card_pin', 'occupation', 'religion', 'marital_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['licence_body', 'licence_number', 'licence_expires_on']);
        });
    }
};
