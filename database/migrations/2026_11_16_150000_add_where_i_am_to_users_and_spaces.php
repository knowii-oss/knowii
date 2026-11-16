<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Where I am" (#1476, ADR 0048): what the platform needs to tell a member where they stand.
 *
 * - users.time_zone: an IANA name (Europe/Brussels), set only by a request of the member. Null: the zone of the instance.
 * - users.last_visit_on: a date without an hour, replaced at most once a day. Nobody reads it.
 * - users.notification_popups: the switch "Show pop-ups", on by default.
 * - spaces.last_content_at: when content was last added to the space (not chat messages: a chat space has its cursor).
 * - space_visits: one row per member and space, replaced at each visit. No history. The rows go with the account and
 *   with the space (cascade), and with the membership (Community::removeUser).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->text('time_zone')->nullable();
            $table->date('last_visit_on')->nullable();
            $table->boolean('notification_popups')->default(true);
        });

        Schema::table('spaces', static function (Blueprint $table) {
            $table->timestamp('last_content_at')->nullable();
        });

        Schema::create('space_visits', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamp('visited_at');

            $table->unique(['user_id', 'space_id']);
            $table->index('space_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_visits');

        Schema::table('spaces', static function (Blueprint $table) {
            $table->dropColumn('last_content_at');
        });

        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn(['time_zone', 'last_visit_on', 'notification_popups']);
        });
    }
};
