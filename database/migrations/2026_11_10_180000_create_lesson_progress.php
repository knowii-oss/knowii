<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course progress per member (#1304, ADR 0034): one row per lesson that a member marked as done. Like chat_reads, the
 * rows are private to their member and never exposed by id, so they have no cuid. They go with the account (ADR 0024),
 * the lesson, the space and the community (cascades), and when the member leaves the community (Community::removeUser).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'page_id']);
            $table->index(['user_id', 'space_id']);
            $table->index(['community_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
