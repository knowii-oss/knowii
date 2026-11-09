<?php

use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Captured content belongs to the community resource, never to the global resource (ADR 0018 step 1, #1244): content
 * captured in one community never shows in another one.
 *
 * - community_resources gets type (copied from resources), level (moved from resources), capture_status and
 *   capture_failure_reason.
 * - resource_captures: the Markdown of one community resource.
 * - The legacy capture goes: resource_text_articles (with the stored HTML), resources.cover_image_base64 and
 *   resources.cover_image_url (the stored copies of ADR 0016 stay).
 *
 * The Markdown of the old text articles is copied to the new table, for the development data: there is no production
 * instance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_resources', static function (Blueprint $table) {
            // The types of that time (the fixed list of #1246 replaces them in a later migration)
            $table->enum('type', ['textArticle'])->default('textArticle')->index();
            $table->enum('level', KnowiiResourceLevel::toStringArray())->default(KnowiiResourceLevel::Unknown->value)->index();
            $table->enum('capture_status', KnowiiCaptureStatus::toStringArray())->default(KnowiiCaptureStatus::Pending->value)->index();
            // Why the last capture failed (shown to the curator), null otherwise
            $table->string('capture_failure_reason')->nullable();
        });

        Schema::create('resource_captures', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_resource_id')->unique()->constrained('community_resources')->cascadeOnDelete();
            $table->longText('markdown');
            $table->integer('word_count');
            // Minutes
            $table->integer('reading_time');
            $table->enum('source', KnowiiCaptureSource::toStringArray());
            $table->timestamp('captured_at');
            $table->timestamps();
        });

        // The existing rows: type and level from their resource, captured (the old capture ran in the request)
        DB::statement(<<<'SQL'
            UPDATE community_resources cr
            SET type = r.type, level = r.level, capture_status = 'captured'
            FROM resources r
            WHERE r.id = cr.resource_id
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO resource_captures (community_resource_id, markdown, word_count, reading_time, source, captured_at, created_at, updated_at)
            SELECT cr.id, a.markdown, COALESCE(a.word_count, 0), COALESCE(a.reading_time, 0), 'server', a.updated_at, NOW(), NOW()
            FROM community_resources cr
            JOIN resource_text_articles a ON a.id = cr.resource_text_article_id
            WHERE a.markdown IS NOT NULL AND a.markdown <> ''
        SQL);

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('resource_text_article_id');
        });
        Schema::dropIfExists('resource_text_articles');

        Schema::table('resources', static function (Blueprint $table) {
            $table->dropColumn(['level', 'cover_image_base64', 'cover_image_url']);
        });
    }

    public function down(): void
    {
        Schema::table('resources', static function (Blueprint $table) {
            $table->string('cover_image_url')->nullable();
            $table->longText('cover_image_base64')->nullable();
            $table->enum('level', KnowiiResourceLevel::toStringArray())->default(KnowiiResourceLevel::Unknown->value)->index();
        });

        Schema::create('resource_text_articles', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique()->index();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->text('markdown')->nullable();
            $table->text('html')->nullable();
            $table->integer('word_count')->nullable();
            $table->integer('reading_time')->nullable();
            $table->timestamps();
        });

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->foreignId('resource_text_article_id')->nullable()->constrained('resource_text_articles')->cascadeOnDelete();
        });

        // The level goes back to the resource (the last community wins); the captured content is lost
        DB::statement(<<<'SQL'
            UPDATE resources r SET level = cr.level FROM community_resources cr WHERE cr.resource_id = r.id
        SQL);

        Schema::dropIfExists('resource_captures');

        Schema::table('community_resources', static function (Blueprint $table) {
            $table->dropColumn(['type', 'level', 'capture_status', 'capture_failure_reason']);
        });
    }
};
