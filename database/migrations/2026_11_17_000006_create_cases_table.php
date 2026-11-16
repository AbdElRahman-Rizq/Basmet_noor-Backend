<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_id')->constrained('children')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('specialist_id')->nullable()
                ->constrained('specialists')->restrictOnDelete();
            $table->timestampTz('assigned_at')->nullable();
            $table->foreignUuid('assigned_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('child_id');
            $table->index('branch_id');
            $table->index('specialist_id');
            $table->index(['specialist_id', 'status']);
            $table->index('assigned_by_user_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE cases ADD CONSTRAINT cases_assignment_consistency_check CHECK (
                (specialist_id IS NULL AND assigned_at IS NULL AND assigned_by_user_id IS NULL)
                OR
                (specialist_id IS NOT NULL AND assigned_at IS NOT NULL AND assigned_by_user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
