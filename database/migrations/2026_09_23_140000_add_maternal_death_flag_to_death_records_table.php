<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maternal Death Surveillance and Response.
 *
 * Ghana requires every maternal death to be notified within 24 hours and
 * reviewed afterwards. A death record could not say whether a death was
 * maternal at all, so none of that could be counted or chased.
 *
 * The WHO definition is the death of a woman while pregnant or within 42 days
 * of the end of the pregnancy, from any cause related to or aggravated by the
 * pregnancy — not from accidental or incidental causes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('death_records', function (Blueprint $table) {
            $table->boolean('is_maternal_death')->default(false)->after('manner');
            $table->string('maternal_death_timing')->nullable()->after('is_maternal_death');
            $table->timestamp('notified_at')->nullable()->after('maternal_death_timing');
            $table->foreignId('notified_by')->nullable()->after('notified_at')->constrained('users');
            $table->timestamp('reviewed_at')->nullable()->after('notified_by');
            $table->text('review_findings')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('death_records', function (Blueprint $table) {
            $table->dropForeign(['notified_by']);
            $table->dropColumn([
                'is_maternal_death',
                'maternal_death_timing',
                'notified_at',
                'notified_by',
                'reviewed_at',
                'review_findings',
            ]);
        });
    }
};
