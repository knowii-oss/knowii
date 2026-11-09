<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on resources (#1034): one nullable foreign key per kind of item, with a check constraint (ADR 0014).
 * A comment belongs to exactly one post or one resource of a resources space.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', static function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->change();
            // Deleting a resource (or its space) deletes its comments
            $table->foreignId('community_resource_id')->nullable()->after('post_id')->constrained()->cascadeOnDelete();
            $table->index(['community_resource_id', 'created_at']);
        });

        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_one_item_check CHECK (num_nonnulls(post_id, community_resource_id) = 1)');
    }

    /**
     * Deletes the comments on resources: the old schema has no place for them.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE comments DROP CONSTRAINT IF EXISTS comments_one_item_check');
        DB::table('comments')->whereNotNull('community_resource_id')->delete();

        Schema::table('comments', static function (Blueprint $table) {
            $table->dropIndex(['community_resource_id', 'created_at']);
            $table->dropConstrainedForeignId('community_resource_id');
            $table->foreignId('post_id')->nullable(false)->change();
        });
    }
};
