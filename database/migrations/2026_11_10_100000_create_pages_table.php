<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page spaces hold ordered Markdown pages (#1179): Start Here, guides, resource libraries, and later the lessons of
 * course spaces. The uploads that a page shows are recorded like those of posts and comments (#1201).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // The last member who wrote the page; a deleted account keeps the page (ADR 0024)
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            // Unique within the space
            $table->string('slug');
            $table->text('body');
            $table->text('body_html');
            $table->integer('position');
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->index(['space_id', 'position']);
        });

        Schema::table('upload_usages', static function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['upload_id', 'page_id']);
            $table->index('page_id');
        });
        DB::statement('ALTER TABLE upload_usages DROP CONSTRAINT upload_usages_one_item');
        DB::statement('ALTER TABLE upload_usages ADD CONSTRAINT upload_usages_one_item CHECK (num_nonnulls(post_id, comment_id, page_id) = 1)');
    }

    public function down(): void
    {
        DB::table('upload_usages')->whereNotNull('page_id')->delete();
        DB::statement('ALTER TABLE upload_usages DROP CONSTRAINT upload_usages_one_item');
        DB::statement('ALTER TABLE upload_usages ADD CONSTRAINT upload_usages_one_item CHECK (num_nonnulls(post_id, comment_id) = 1)');
        Schema::table('upload_usages', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
        });

        Schema::dropIfExists('pages');
    }
};
