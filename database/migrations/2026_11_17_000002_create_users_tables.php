<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('email', 190)->nullable()->unique();
            $table->string('mobile', 20)->nullable()->unique();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['role_id', 'is_active']);
            $table->index('created_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('is_granted');
            $table->foreignUuid('granted_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->primary(['user_id', 'permission_id']);
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('users');
    }
};
