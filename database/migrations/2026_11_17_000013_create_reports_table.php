<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->constrained('cases')->restrictOnDelete();
            $table->foreignUuid('specialist_id')->constrained('specialists')->restrictOnDelete();
            $table->date('session_date');
            $table->string('file_path', 255)->unique();
            $table->string('file_original_name', 255);
            $table->string('file_mime', 100);
            $table->integer('file_size');
            $table->char('file_checksum', 64);
            $table->boolean('visible_to_parent')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('specialist_id');
        });

        DB::statement('CREATE INDEX reports_case_session_date_index ON reports (case_id, session_date DESC)');
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_file_mime_check CHECK (file_mime = 'application/pdf')");
        DB::statement('ALTER TABLE reports ADD CONSTRAINT reports_file_size_check CHECK (file_size > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
