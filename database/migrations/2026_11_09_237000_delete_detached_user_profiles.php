<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A profile belongs to an account and goes with it (#1261, ADR 0024). Before, deleting an account kept the profile
 * (email, name, every field) with user_id = null, so that a new sign-up with the same email could take it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The profiles of deleted accounts: the resources they curated show "Deleted member" (curator_id nullOnDelete)
        DB::table('user_profiles')->whereNull('user_id')->delete();

        Schema::table('user_profiles', static function (Blueprint $table) {
            // Was "set null" on delete
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', static function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
