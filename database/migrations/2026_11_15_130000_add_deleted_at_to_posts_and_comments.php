<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markers for the content that a member deletes with their account (#1344, ADR 0043). A post that still has comments of
 * other members, and a comment that still has replies, stay as an empty row with a `deleted_at` date, like the deleted
 * chat messages (#1185): no title, no text, no author. Ordinary deletes are unchanged (the row goes, with its comments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', static function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::table('comments', static function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', static function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('posts', static function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
