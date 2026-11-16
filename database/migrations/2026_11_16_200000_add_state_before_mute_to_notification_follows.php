<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The undo of "Mute this thread" gives back the exact earlier state (#1509, ADR 0042): a mute remembers what it replaced.
 * `state_before_mute` and `reason_before_mute` are null while the row is not muted, and also when the thread had no row
 * before the mute (the undo then deletes the row). Any other change of the state forgets them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_follows', static function (Blueprint $table) {
            $table->string('state_before_mute')->nullable();
            $table->string('reason_before_mute')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_follows', static function (Blueprint $table) {
            $table->dropColumn(['state_before_mute', 'reason_before_mute']);
        });
    }
};
