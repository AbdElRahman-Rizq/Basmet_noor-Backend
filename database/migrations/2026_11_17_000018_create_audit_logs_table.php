<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 40)->nullable();
            $table->string('action', 100);
            $table->string('entity_name', 100);
            $table->uuid('record_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['entity_name', 'record_id']);
        });

        DB::statement('CREATE INDEX audit_logs_user_created_index ON audit_logs (user_id, created_at DESC)');
        DB::statement('CREATE INDEX audit_logs_created_index ON audit_logs (created_at DESC)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_update_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Table % is append-only: % is not allowed', TG_TABLE_NAME, TG_OP;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_immutable
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION prevent_update_delete()
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
