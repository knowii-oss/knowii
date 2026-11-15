<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follows and space levels (#1475, ADR 0042): the choices of a member about what reaches them.
 *
 * - notification_follows: one row per member and thread. A thread is a post with its comments, or a first-level chat
 *   message with its replies, so the thread follows ADR 0014: one nullable foreign key per kind of thread, each deleted
 *   with its thread, and a check that exactly one is set. `state` has three values: following, muted and stopped (the
 *   member stopped by hand: the row remembers it, so that writing in the thread again does not follow it again).
 * - notification_space_levels: the level (all, mine, none) that a member chose for a space. No row means the default.
 * - spaces.default_notification_level: the default that a manager chose for the members who did not choose. Null means
 *   the built-in default of the kind of space.
 * - notifications.page_id: the lesson of a `lesson_new` row (ADR 0014: a new kind of item adds a column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_follows', static function (Blueprint $table) {
            $table->id();
            // The rows go with the account and with the community (a member who leaves loses them, ADR 0042)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained()->cascadeOnDelete();
            // A backed enum (KnowiiFollowState) and (KnowiiFollowReason), strings like the other enums of the schema
            $table->string('state');
            $table->string('reason');
            $table->timestamps();

            $table->index('community_id');
            $table->index('post_id');
            $table->index('chat_message_id');
        });

        DB::statement('ALTER TABLE notification_follows ADD CONSTRAINT notification_follows_one_thread CHECK (num_nonnulls(post_id, chat_message_id) = 1)');
        // One row per member and thread
        DB::statement('CREATE UNIQUE INDEX notification_follows_post_unique ON notification_follows (user_id, post_id) WHERE post_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX notification_follows_chat_message_unique ON notification_follows (user_id, chat_message_id) WHERE chat_message_id IS NOT NULL');

        Schema::create('notification_space_levels', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // A backed enum (KnowiiSpaceNotificationLevel)
            $table->string('level');
            $table->timestamps();

            $table->unique(['user_id', 'space_id']);
            $table->index('space_id');
        });

        Schema::table('spaces', static function (Blueprint $table) {
            $table->string('default_notification_level')->nullable();
        });

        Schema::table('notifications', static function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('page_id');
        });
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_one_item');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_one_item CHECK (num_nonnulls(post_id, comment_id, chat_message_id, page_id) = 1)');
        // A retry of the fan-out never writes a second row for the same lesson (the other items check it in the action)
        DB::statement('CREATE UNIQUE INDEX notifications_page_unique ON notifications (user_id, kind, page_id) WHERE page_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifications_page_unique');
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_one_item');
        Schema::table('notifications', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_id');
        });
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_one_item CHECK (num_nonnulls(post_id, comment_id, chat_message_id) = 1)');

        Schema::table('spaces', static function (Blueprint $table) {
            $table->dropColumn('default_notification_level');
        });

        Schema::dropIfExists('notification_space_levels');
        Schema::dropIfExists('notification_follows');
    }
};
