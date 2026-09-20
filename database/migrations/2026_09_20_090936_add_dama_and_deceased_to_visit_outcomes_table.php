<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Widens the outcome enum without doctrine/dbal (not installed): SQLite
     * enforces enum() as a CHECK constraint that can only be changed by
     * rebuilding the table, so this rebuilds it directly for SQLite and
     * uses a plain MODIFY for other drivers.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE visit_outcomes RENAME TO visit_outcomes_old');

            DB::statement("
                CREATE TABLE visit_outcomes (
                    id integer primary key autoincrement not null,
                    visit_id integer not null,
                    outcome varchar check (\"outcome\" in ('discharge', 'admit', 'refer', 'dama', 'deceased')) not null,
                    follow_up_date date,
                    referral_destination varchar,
                    outcome_notes text,
                    doctor_id integer not null,
                    created_at datetime,
                    updated_at datetime,
                    foreign key(\"visit_id\") references \"visits\"(\"id\") on delete cascade,
                    foreign key(\"doctor_id\") references \"users\"(\"id\")
                )
            ");

            DB::statement('INSERT INTO visit_outcomes SELECT * FROM visit_outcomes_old');
            DB::statement('DROP TABLE visit_outcomes_old');

            return;
        }

        DB::statement("ALTER TABLE visit_outcomes MODIFY outcome ENUM('discharge', 'admit', 'refer', 'dama', 'deceased') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("DELETE FROM visit_outcomes WHERE outcome IN ('dama', 'deceased')");
            DB::statement('ALTER TABLE visit_outcomes RENAME TO visit_outcomes_old');

            DB::statement("
                CREATE TABLE visit_outcomes (
                    id integer primary key autoincrement not null,
                    visit_id integer not null,
                    outcome varchar check (\"outcome\" in ('discharge', 'admit', 'refer')) not null,
                    follow_up_date date,
                    referral_destination varchar,
                    outcome_notes text,
                    doctor_id integer not null,
                    created_at datetime,
                    updated_at datetime,
                    foreign key(\"visit_id\") references \"visits\"(\"id\") on delete cascade,
                    foreign key(\"doctor_id\") references \"users\"(\"id\")
                )
            ");

            DB::statement('INSERT INTO visit_outcomes SELECT * FROM visit_outcomes_old');
            DB::statement('DROP TABLE visit_outcomes_old');

            return;
        }

        DB::statement("ALTER TABLE visit_outcomes MODIFY outcome ENUM('discharge', 'admit', 'refer') NOT NULL");
    }
};
