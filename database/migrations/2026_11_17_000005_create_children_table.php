<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global, name-independent sequence (D-003/D-041). Gaps are acceptable;
        // codes are never reused, even after a soft delete.
        // `IF NOT EXISTS` keeps the migration re-runnable: `migrate:fresh` drops
        // tables but leaves standalone sequences behind, and the global sequence
        // must stay monotonic (codes are never reused).
        DB::statement('CREATE SEQUENCE IF NOT EXISTS child_code_sequence START 1');

        Schema::create('children', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->constrained('parents')->restrictOnDelete();
            $table->string('child_code', 20)->unique();
            $table->bigInteger('sequence_number')->unique();
            $table->smallInteger('age');
            $table->date('age_recorded_at')->default(DB::raw('CURRENT_DATE'));
            $table->string('nationality', 60);
            $table->string('neighborhood', 120);
            $table->text('initial_problem');
            $table->foreignUuid('knowledge_source_id')->nullable()
                ->constrained('knowledge_sources')->restrictOnDelete();
            $table->boolean('identity_verified')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('parent_id');
            $table->index('knowledge_source_id');
        });

        DB::statement('ALTER TABLE children ADD CONSTRAINT children_age_check CHECK (age BETWEEN 0 AND 120)');
        DB::statement("ALTER TABLE children ADD CONSTRAINT children_child_code_format_check CHECK (child_code ~ '^[A-Z]{4}[0-9]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
        DB::statement('DROP SEQUENCE IF EXISTS child_code_sequence');
    }
};
