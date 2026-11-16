<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinforcer_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 20);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('display_order');
            $table->timestampsTz();

            $table->unique(['category', 'name']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE reinforcer_catalog ADD CONSTRAINT reinforcer_catalog_category_check
            CHECK (category IN ('material', 'social', 'activity', 'food'))
        SQL);

        Schema::create('assessment_reinforcers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('initial_assessments')->cascadeOnDelete();
            $table->foreignUuid('reinforcer_id')->constrained('reinforcer_catalog')->restrictOnDelete();
            $table->string('reinforcer_name_snapshot', 120);
            $table->string('category_snapshot', 20);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['assessment_id', 'reinforcer_id']);
            $table->index('reinforcer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_reinforcers');
        Schema::dropIfExists('reinforcer_catalog');
    }
};
