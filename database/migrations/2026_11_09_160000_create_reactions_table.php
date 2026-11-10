<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reactions of members to posts and comments, later chat and direct messages (#1176).
 *
 * One nullable foreign key per kind of item (exactly one is set), instead of a polymorphic relation: the database
 * deletes the reactions with their item, also when the item goes through a cascade (a post deletes its comments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->cascadeOnDelete();
            // The five first kinds; any emoji since #1204 (2026_11_10_160000_use_any_emoji_for_reactions)
            $table->enum('kind', ['like', 'love', 'insightful', 'funny', 'celebrate']);
            $table->timestamp('created_at')->useCurrent();

            // One reaction of each kind per member and item (rows where the column is null do not collide)
            $table->unique(['post_id', 'user_id', 'kind']);
            $table->unique(['comment_id', 'user_id', 'kind']);
        });

        // Exactly one item per reaction
        DB::statement('ALTER TABLE reactions ADD CONSTRAINT reactions_one_item CHECK (num_nonnulls(post_id, comment_id) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
