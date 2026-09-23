<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prescribing limits and organ-handling flags. All nullable: a drug with no
     * limit recorded is simply not dose-checked, rather than wrongly flagged.
     */
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->decimal('max_daily_dose', 10, 3)->nullable()->after('drug_class');
            $table->decimal('max_single_dose', 10, 3)->nullable()->after('max_daily_dose');
            $table->string('dose_unit')->nullable()->after('max_single_dose');
            $table->decimal('paediatric_max_per_kg', 10, 3)->nullable()->after('dose_unit');
            $table->boolean('is_renally_cleared')->default(false)->after('paediatric_max_per_kg');
            $table->boolean('is_hepatically_cleared')->default(false)->after('is_renally_cleared');
            $table->string('pregnancy_category')->nullable()->after('is_hepatically_cleared');
            $table->text('renal_guidance')->nullable()->after('pregnancy_category');
            $table->text('hepatic_guidance')->nullable()->after('renal_guidance');
        });
    }

    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn([
                'max_daily_dose', 'max_single_dose', 'dose_unit', 'paediatric_max_per_kg',
                'is_renally_cleared', 'is_hepatically_cleared', 'pregnancy_category',
                'renal_guidance', 'hepatic_guidance',
            ]);
        });
    }
};
