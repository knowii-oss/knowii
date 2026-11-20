<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.session_version (ADR 0059): a number that each session of the account holds from its sign-in. A takeover adds 1,
 * and a session that holds another number is signed out at its next request, whatever the session driver. Every account
 * starts at 0, and a session from before this migration holds no number, which counts as 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->unsignedInteger('session_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('session_version');
        });
    }
};
