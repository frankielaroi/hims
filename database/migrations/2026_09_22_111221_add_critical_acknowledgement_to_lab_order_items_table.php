<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A critical result has to be acknowledged by name, separately from the
     * review of the order as a whole, so the read-back is auditable.
     */
    public function up(): void
    {
        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->foreignId('critical_acknowledged_by')->nullable()->after('authorized_at')->constrained('users');
            $table->timestamp('critical_acknowledged_at')->nullable()->after('critical_acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->dropForeign(['critical_acknowledged_by']);
            $table->dropColumn(['critical_acknowledged_by', 'critical_acknowledged_at']);
        });
    }
};
