<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_progress_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goal_id')->constrained('goals')->restrictOnDelete();
            $table->foreignUuid('report_id')->nullable()->constrained('reports')->restrictOnDelete();
            $table->decimal('previous_percentage', 5, 2);
            $table->decimal('new_percentage', 5, 2);
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index('report_id');
        });

        DB::statement('CREATE INDEX goal_progress_history_goal_recorded_index ON goal_progress_history (goal_id, recorded_at DESC)');
        DB::statement('CREATE UNIQUE INDEX goal_progress_history_report_goal_unique ON goal_progress_history (report_id, goal_id) WHERE report_id IS NOT NULL');
        DB::statement('ALTER TABLE goal_progress_history ADD CONSTRAINT goal_progress_history_previous_check CHECK (previous_percentage BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE goal_progress_history ADD CONSTRAINT goal_progress_history_new_check CHECK (new_percentage BETWEEN 0 AND 100)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_update_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Table % is append-only: % is not allowed', TG_TABLE_NAME, TG_OP;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER goal_progress_history_immutable
            BEFORE UPDATE OR DELETE ON goal_progress_history
            FOR EACH ROW EXECUTE FUNCTION prevent_update_delete()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_progress_history');
        DB::statement('DROP FUNCTION IF EXISTS prevent_update_delete() CASCADE');
    }
};
