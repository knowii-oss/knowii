<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avatars (#109, ADR 0021): one path on the user, read by every list of authors and members without another query.
 * The unused Jetstream column of the profile goes away (no avatar was ever stored: the feature was off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('avatar_path', 2048)->nullable();
        });

        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->string('profile_photo_path', 2048)->nullable();
        });

        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
