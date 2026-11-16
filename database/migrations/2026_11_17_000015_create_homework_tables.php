<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->constrained('cases')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('completed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['case_id', 'status']);
            $table->index('created_by_user_id');
        });

        Schema::create('homework_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['homework_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_comments');
        Schema::dropIfExists('homework');
    }
};
