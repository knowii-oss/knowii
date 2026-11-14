<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplementary materials of a course space (#1336, ADR 0037): files and links that the managers of the course add
 * to the whole course (page_id null), to a section or to a lesson. A material is a file on a private disk or a link,
 * never both (check constraint). The rows go with the lesson or the section, the space and the community (cascades);
 * the files are deleted by App\Support\ContentFiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // null: a material of the whole course
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->string('kind');
            // A link
            $table->string('url', 2048)->nullable();
            // A file
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'page_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE course_materials ADD CONSTRAINT course_materials_file_or_link CHECK (
                (kind = 'file' AND url IS NULL AND num_nonnulls(disk, path, file_name, mime_type, size_bytes) = 5)
                OR (kind = 'link' AND url IS NOT NULL AND num_nonnulls(disk, path, file_name, mime_type, size_bytes) = 0)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
