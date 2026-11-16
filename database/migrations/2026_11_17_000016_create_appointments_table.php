<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_id')->constrained('children')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('scheduled_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('appointment_date');
            $table->string('status', 20)->nullable();
            $table->foreignUuid('specialist_id')->nullable()
                ->constrained('specialists')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('appointment_date');
            $table->index('child_id');
            $table->index('branch_id');
            $table->index('specialist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
