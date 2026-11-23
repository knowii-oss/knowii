<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member chose for a notification email group (#1478, ADR 0054): one row per account and group, no row means the
 * default of the group. Slice 4b writes only `off`, through the stop link of a mail; `state` is a string because slice 4c
 * adds the rhythms. `user_id` is `cascadeOnDelete`: the choices go with the account. No period: they live with the
 * account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_email_choices', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('group', 32);
            $table->string('state', 32);
            $table->timestamps();

            $table->unique(['user_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_choices');
    }
};
