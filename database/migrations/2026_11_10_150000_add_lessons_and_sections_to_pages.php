<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course spaces (#1188, ADR 0029): a lesson is a page with an optional video, and a section is a row of kind
 * "section" in the same ordered list (a heading for the pages after it). The value list is a literal: the enum
 * changes over time, a migration must not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', static function (Blueprint $table) {
            $table->string('kind', 16)->default('page')->after('space_id');
            // Lessons of a course space only: a YouTube or Vimeo address (App\Support\VideoEmbed)
            $table->string('video_url', 2048)->nullable()->after('body_html');
        });
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_kind_check CHECK (kind IN ('page', 'section'))");
        DB::statement("ALTER TABLE pages ADD CONSTRAINT pages_section_without_video CHECK (kind = 'page' OR video_url IS NULL)");
    }

    public function down(): void
    {
        DB::table('pages')->where('kind', 'section')->delete();
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_section_without_video');
        DB::statement('ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_kind_check');
        Schema::table('pages', static function (Blueprint $table) {
            $table->dropColumn(['kind', 'video_url']);
        });
    }
};
