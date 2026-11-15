<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with an OpenID Connect provider (#1385, ADR 0045): one row per account that a member connected. It holds the
 * issuer, the subject (the stable identifier of the account at the provider), and two dates. No email, no name and no
 * token of the provider, ever. The rows go with the platform account (ADR 0024).
 *
 * An account that the provider created has no password: the column accepts null (nothing matches a null hash).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('issuer', 512);
            // OpenID Connect Core 2.: at most 255 ASCII characters
            $table->string('subject', 255);
            $table->timestamp('linked_at');
            $table->timestamp('last_used_at')->nullable();

            // A provider account belongs to one platform account, and a platform account has one account per provider
            $table->unique(['issuer', 'subject']);
            $table->unique(['user_id', 'issuer']);
        });

        Schema::table('users', static function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');

        // Accounts without a password get an unusable one, so that the column can be required again
        Schema::getConnection()->table('users')->whereNull('password')->update(['password' => '!']);
        Schema::table('users', static function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
