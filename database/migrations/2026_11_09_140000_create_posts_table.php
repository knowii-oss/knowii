<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posts of discussion spaces (#1174).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            // Deleting a space deletes its posts
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // Posts stay when their author deletes the account: they show "Deleted member"
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            // Markdown written by the member, and its sanitized HTML rendering (App\Support\Markdown)
            $table->text('body');
            $table->text('body_html');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            // Space page: pinned first, then newest
            $table->index(['space_id', 'is_pinned', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
