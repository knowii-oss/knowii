<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on events (#1516, ADR 0053): one more nullable foreign key per kind of item, with the check constraint of
 * ADR 0014. A comment belongs to exactly one post, one resource of a resources space or one event of an events space.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', static function (Blueprint $table) {
            // Deleting an event (or its space, or its community) deletes its comments, with their reactions, mentions,
            // upload usages and notification rows by the cascades that exist
            $table->foreignId('event_id')->nullable()->after('community_resource_id')->constrained()->cascadeOnDelete();
            $table->index(['event_id', 'created_at']);
        });

        DB::statement('ALTER TABLE comments DROP CONSTRAINT comments_one_item_check');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_one_item_check CHECK (num_nonnulls(post_id, community_resource_id, event_id) = 1)');
    }

    /**
     * Deletes the comments on events: the old schema has no place for them.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE comments DROP CONSTRAINT IF EXISTS comments_one_item_check');
        DB::table('comments')->whereNotNull('event_id')->delete();

        Schema::table('comments', static function (Blueprint $table) {
            $table->dropIndex(['event_id', 'created_at']);
            $table->dropConstrainedForeignId('event_id');
        });

        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_one_item_check CHECK (num_nonnulls(post_id, community_resource_id) = 1)');
    }
};
