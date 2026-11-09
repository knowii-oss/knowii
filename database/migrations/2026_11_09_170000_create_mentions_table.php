<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Members mentioned (@username) in posts and comments (#1177), for the notifications of M4.
 * One nullable foreign key per kind of item, exactly one set (ADR 0014).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // The mentioned member
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'user_id']);
            $table->unique(['comment_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement('ALTER TABLE mentions ADD CONSTRAINT mentions_one_item CHECK (num_nonnulls(post_id, comment_id) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
