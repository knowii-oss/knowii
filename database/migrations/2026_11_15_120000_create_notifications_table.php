<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications (#1474, ADR 0042): one row per recipient. A row says who is told (user_id), where (community_id,
 * space_id), why (kind), by whom (actor_id) and about which item. It never holds text of content: titles and excerpts
 * are built from the live content when the list is read.
 *
 * The item follows ADR 0014: one nullable foreign key per kind of item, each deleted with its item, and a check that
 * exactly one is set. dedupe_key is a text key for bundling and idempotency, not a reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // The recipient. The rows go with the account (ADR 0024)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // The scope of the access check. Null when the item has no space
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            // A backed enum (KnowiiNotificationKind), a string like the other enums of the schema
            $table->string('kind');
            // The member who caused the row. Deleted accounts show as "Deleted member": the row stays
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('dedupe_key');
            // Seen: the line was on screen. Read: the line was opened or marked done (design 6.1)
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index('community_id');
            $table->index('space_id');
            $table->index('actor_id');
            $table->index('post_id');
            $table->index('comment_id');
            $table->index('chat_message_id');
        });

        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_one_item CHECK (num_nonnulls(post_id, comment_id, chat_message_id) = 1)');
        // One open row per recipient and thing: a read row never blocks a new one
        DB::statement('CREATE UNIQUE INDEX notifications_open_dedupe_unique ON notifications (user_id, dedupe_key) WHERE read_at IS NULL');
        // The count of the bell: the rows that the member did not see
        DB::statement('CREATE INDEX notifications_unseen_index ON notifications (user_id) WHERE seen_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
