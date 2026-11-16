<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functional_scales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('functional_scale_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('initial_assessments')->cascadeOnDelete();
            $table->foreignUuid('scale_id')->constrained('functional_scales')->restrictOnDelete();
            $table->string('scale_name_snapshot', 120);
            $table->smallInteger('score');
            $table->text('specialist_comment')->nullable();
            $table->timestampsTz();

            $table->unique(['assessment_id', 'scale_id']);
            $table->index('scale_id');
        });

        DB::statement('ALTER TABLE functional_scale_scores ADD CONSTRAINT functional_scale_scores_score_check CHECK (score BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('functional_scale_scores');
        Schema::dropIfExists('functional_scales');
    }
};
