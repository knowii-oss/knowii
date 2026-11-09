<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat spaces with threads (#1185). A message has an optional parent: a reply in its thread (one level). A deleted
 * message stays as a marker ("This message was deleted"), so its thread keeps its place. Reactions, mentions and
 * upload usages get a chat_message_id, like posts and comments. chat_reads keeps the last message each member read in
 * each chat space (unread markers).
 */
return new class extends Migration
{
    private const array ITEM_TABLES = [
        'reactions' => ['post_id', 'comment_id'],
        'mentions' => ['post_id', 'comment_id'],
        'upload_usages' => ['post_id', 'comment_id', 'page_id'],
    ];

    public function up(): void
    {
        Schema::create('chat_messages', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // A deleted account keeps its messages, without an author (ADR 0024)
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // A reply in a thread; the thread goes with its first message
            $table->foreignId('parent_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
            $table->text('body');
            $table->text('body_html');
            // Replies of a first message, kept on it for the lists
            $table->integer('reply_count')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'parent_id', 'id']);
        });

        Schema::create('chat_reads', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // The id of the last first-level message the member read in the space
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'space_id']);
        });

        foreach (self::ITEM_TABLES as $table => $columns) {
            Schema::table($table, static function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('chat_message_id')->nullable()->constrained()->cascadeOnDelete();
                $blueprint->index('chat_message_id');
                if ($table === 'reactions') {
                    $blueprint->unique(['chat_message_id', 'user_id', 'kind']);
                } else {
                    $blueprint->unique([$table === 'upload_usages' ? 'upload_id' : 'user_id', 'chat_message_id']);
                }
            });
            $this->replaceCheck($table, [...$columns, 'chat_message_id']);
        }
    }

    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table => $columns) {
            DB::table($table)->whereNotNull('chat_message_id')->delete();
            $this->replaceCheck($table, $columns);
            Schema::table($table, static function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('chat_message_id');
            });
        }

        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_messages');
    }

    /**
     * @param  list<string>  $columns
     */
    private function replaceCheck(string $table, array $columns): void
    {
        $constraint = $table.'_one_item';
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (num_nonnulls(".implode(', ', $columns).') = 1)');
    }
};
