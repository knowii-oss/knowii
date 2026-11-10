<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reactions with any emoji (#1204, ADR 0032): the five fixed kinds become their emoji. App\Support\Emoji checks the
 * emoji against an allow-list. The order of the reactions comes from their creation time, not from a fixed list.
 */
return new class extends Migration
{
    private const array KIND_EMOJI = [
        'like' => '👍',
        'love' => '❤️',
        'insightful' => '💡',
        'funny' => '😄',
        'celebrate' => '🎉',
    ];

    public function up(): void
    {
        Schema::table('reactions', static function (Blueprint $table) {
            // An emoji with skin tone and joiners takes up to about 30 bytes
            $table->string('emoji', 64)->nullable();
        });

        foreach (self::KIND_EMOJI as $kind => $emoji) {
            DB::table('reactions')->where('kind', $kind)->update(['emoji' => $emoji]);
        }

        Schema::table('reactions', static function (Blueprint $table) {
            $table->dropUnique(['post_id', 'user_id', 'kind']);
            $table->dropUnique(['comment_id', 'user_id', 'kind']);
            $table->dropUnique(['chat_message_id', 'user_id', 'kind']);
            $table->dropColumn('kind');
        });

        Schema::table('reactions', static function (Blueprint $table) {
            $table->string('emoji', 64)->nullable(false)->change();
            // One reaction with each emoji per member and item (rows where the column is null do not collide)
            $table->unique(['post_id', 'user_id', 'emoji']);
            $table->unique(['comment_id', 'user_id', 'emoji']);
            $table->unique(['chat_message_id', 'user_id', 'emoji']);
            // The emoji that a member uses most (GET /api/v1/user/frequent-emoji)
            $table->index(['user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::table('reactions', static function (Blueprint $table) {
            $table->string('kind', 32)->nullable();
        });

        // Reactions with another emoji cannot go back to a fixed kind
        DB::table('reactions')->whereNotIn('emoji', array_values(self::KIND_EMOJI))->delete();
        foreach (self::KIND_EMOJI as $kind => $emoji) {
            DB::table('reactions')->where('emoji', $emoji)->update(['kind' => $kind]);
        }

        Schema::table('reactions', static function (Blueprint $table) {
            $table->dropIndex(['user_id', 'emoji']);
            $table->dropUnique(['post_id', 'user_id', 'emoji']);
            $table->dropUnique(['comment_id', 'user_id', 'emoji']);
            $table->dropUnique(['chat_message_id', 'user_id', 'emoji']);
            $table->dropColumn('emoji');
            $table->string('kind', 32)->nullable(false)->change();
            $table->unique(['post_id', 'user_id', 'kind']);
            $table->unique(['comment_id', 'user_id', 'kind']);
            $table->unique(['chat_message_id', 'user_id', 'kind']);
        });
    }
};
