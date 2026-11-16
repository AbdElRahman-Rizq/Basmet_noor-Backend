<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->constrained('cases')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('plan_type', 20)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->nullable();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('case_id');
        });

        DB::statement('ALTER TABLE treatment_plans ADD CONSTRAINT treatment_plans_dates_check CHECK (end_date IS NULL OR end_date >= start_date)');

        Schema::create('plan_specialists', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->constrained('treatment_plans')->cascadeOnDelete();
            $table->foreignUuid('specialist_id')->constrained('specialists')->cascadeOnDelete();

            $table->primary(['plan_id', 'specialist_id']);
            $table->index('specialist_id');
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('treatment_plans')->restrictOnDelete();
            $table->foreignUuid('specialist_id')->constrained('specialists')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->smallInteger('display_order');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('plan_id');
            $table->index('specialist_id');
            $table->index(['plan_id', 'display_order']);
        });

        DB::statement('ALTER TABLE goals ADD CONSTRAINT goals_progress_percentage_check CHECK (progress_percentage BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::dropIfExists('plan_specialists');
        Schema::dropIfExists('treatment_plans');
    }
};
