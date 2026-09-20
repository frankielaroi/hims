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
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('nhis_verified')->default(false)->after('nhis_expiry_snapshot');
            $table->foreignId('nhis_verified_by')->nullable()->after('nhis_verified')->constrained('users');
            $table->timestamp('nhis_verified_at')->nullable()->after('nhis_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nhis_verified_by');
            $table->dropColumn(['nhis_verified', 'nhis_verified_at']);
        });
    }
};
