<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments on posts, with one level of replies (#1175).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // Deleting a post deletes its comments
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Comments stay when their author deletes the account
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // A reply points to a top-level comment (one level only). Deleting a comment deletes its replies.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->text('body_html');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
