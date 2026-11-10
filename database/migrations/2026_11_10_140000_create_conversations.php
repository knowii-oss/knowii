<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Direct messages (#1186, ADR 0028): conversations of 2 to 10 members of a community. Their messages are chat
 * messages: a chat message belongs to a chat space or to a conversation, never both, and only space messages have
 * threads. Reactions, mentions and upload usages already point to chat messages.
 *
 * - conversation_participants keeps the last message each participant read (unread counts).
 * - direct_key ("12:34", the two user ids in order) makes a one-to-one conversation unique in its community.
 * - user_blocks: a member blocks another one, in every community.
 * - communities.direct_messages_enabled: the owner turns direct messages off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->boolean('direct_messages_enabled')->default(true);
        });

        Schema::create('conversations', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // A deleted account keeps the conversations of the others (ADR 0024)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // One-to-one conversations only: the two user ids in order
            $table->string('direct_key')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'direct_key']);
            $table->index(['community_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The id of the last message the participant read
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('user_blocks', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
        DB::statement('ALTER TABLE user_blocks ADD CONSTRAINT user_blocks_not_self CHECK (blocker_id <> blocked_id)');

        Schema::table('chat_messages', static function (Blueprint $table) {
            $table->foreignId('space_id')->nullable()->change();
            $table->foreignId('conversation_id')->nullable()->after('space_id')->constrained()->cascadeOnDelete();
            $table->index(['conversation_id', 'id']);
        });
        DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_one_place CHECK (num_nonnulls(space_id, conversation_id) = 1)');
        DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT chat_messages_threads_in_spaces CHECK (parent_id IS NULL OR space_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::table('chat_messages')->whereNotNull('conversation_id')->delete();
        DB::statement('ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_threads_in_spaces');
        DB::statement('ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS chat_messages_one_place');
        Schema::table('chat_messages', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->foreignId('space_id')->nullable(false)->change();
        });

        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');

        Schema::table('communities', static function (Blueprint $table) {
            $table->dropColumn('direct_messages_enabled');
        });
    }
};
