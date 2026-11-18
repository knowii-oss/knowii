<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account without a password proves that it wants to be deleted with a new sign-in at the OpenID Connect provider
 * (#1521, ADR 0052). users.deletion_signed_in_at is the time of that sign-in (the claim auth_time): one value per
 * account, replaced by each new proof, cleared by the deletion that uses it, and deleted with the account. The proof
 * holds for a few minutes after that time (services.oidc.fresh_sign_in_seconds). A web session and an API token read
 * the same value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->timestamp('deletion_signed_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('deletion_signed_in_at');
        });
    }
};
