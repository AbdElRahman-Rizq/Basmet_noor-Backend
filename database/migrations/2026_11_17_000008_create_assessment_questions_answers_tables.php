<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('section_name', 120);
            $table->text('question_text');
            $table->string('question_type', 20);
            $table->jsonb('options')->nullable();
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE assessment_questions ADD CONSTRAINT assessment_questions_type_check
            CHECK (question_type IN ('text', 'boolean', 'single_choice', 'number'))
        SQL);

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('initial_assessments')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('assessment_questions')->restrictOnDelete();
            $table->text('question_text_snapshot');
            $table->string('section_name_snapshot', 120);
            $table->string('question_type_snapshot', 20);
            $table->text('answer_value')->nullable();
            $table->timestampsTz();

            $table->unique(['assessment_id', 'question_id']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_questions');
    }
};
