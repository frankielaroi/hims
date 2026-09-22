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
        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->foreignId('lab_sample_id')->nullable()->after('lab_test_id')->constrained()->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->after('result_flag')->constrained('users');
            $table->foreignId('authorized_by')->nullable()->after('performed_by')->constrained('users');
            $table->timestamp('authorized_at')->nullable()->after('performed_at');
            $table->foreignId('lab_reagent_id')->nullable()->after('authorized_at')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->dropForeign(['lab_sample_id']);
            $table->dropColumn('lab_sample_id');
            $table->dropForeign(['entered_by']);
            $table->dropColumn('entered_by');
            $table->dropForeign(['authorized_by']);
            $table->dropColumn('authorized_by');
            $table->dropColumn('authorized_at');
            $table->dropForeign(['lab_reagent_id']);
            $table->dropColumn('lab_reagent_id');
        });
    }
};
