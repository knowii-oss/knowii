<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The headline of a profile (#1688): one line of plain text that a member writes about themselves, null when there is
 * none. The member chooses who sees it with `field_visibility` (ADR 0020). The limit is `Constants::MAX_LENGTH_USER_HEADLINE`:
 * the column is wider, so a change of the limit needs no migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->string('headline')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->dropColumn('headline');
        });
    }
};
