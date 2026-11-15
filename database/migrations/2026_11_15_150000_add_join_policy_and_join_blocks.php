<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who can join a community (#1486, ADR 0044): by invitation (the default, as before) or open (a signed-in person with a
 * verified address joins by themselves).
 *
 * A member whom a manager removed cannot join again by themselves: the removal writes one row, the smallest thing that
 * keeps the rule (community, person, date). An accepted invitation or a manager deletes it. The rows go with the
 * community and with the account of the person (ADR 0024).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', static function (Blueprint $table) {
            $table->string('join_policy', 16)->default('invitation');
        });

        Schema::create('community_join_blocks', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['community_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_join_blocks');

        Schema::table('communities', static function (Blueprint $table) {
            $table->dropColumn('join_policy');
        });
    }
};
