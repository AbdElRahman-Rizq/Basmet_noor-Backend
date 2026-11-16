<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->text('description');
            $table->boolean('is_required')->default(false);
            $table->smallInteger('display_order');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('child_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('child_id')->constrained('children')->restrictOnDelete();
            $table->foreignUuid('consent_type_id')->constrained('consent_types')->restrictOnDelete();
            $table->timestampTz('accepted_at');
            $table->foreignUuid('accepted_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->foreignUuid('recorded_by_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->string('consent_version', 20);
            $table->text('consent_text_snapshot');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['child_id', 'consent_type_id']);
            $table->index('consent_type_id');
            $table->index('accepted_by_user_id');
            $table->index('recorded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_consents');
        Schema::dropIfExists('consent_types');
    }
};
