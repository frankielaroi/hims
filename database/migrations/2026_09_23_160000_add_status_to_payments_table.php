<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every payment row was assumed settled the moment it was written, because
 * `Invoice::recalculateTotals()` sums them all. Mobile money does not work that
 * way: the patient approves on their handset seconds or minutes later, so a
 * pending row would mark the invoice paid before any money moved.
 *
 * Existing rows are cash and card taken at the desk, so they backfill as
 * successful.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('successful')->after('method');
            $table->string('provider')->nullable()->after('status');
            $table->string('payer_msisdn')->nullable()->after('reference_number');
            $table->string('failure_reason')->nullable()->after('payer_msisdn');
            $table->timestamp('confirmed_at')->nullable()->after('failure_reason');
        });

        DB::table('payments')->update([
            'status' => 'successful',
            'confirmed_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'provider', 'payer_msisdn', 'failure_reason', 'confirmed_at']);
        });
    }
};
