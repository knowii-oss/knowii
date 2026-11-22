<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last monthly limit and use that `mail:check-budget` read from the mail provider (#1390, ADR 0061), so that the
 * checks of an import of invitations use the same quota as the budget when MAIL_MONTHLY_QUOTA is not set:
 *
 * - `provider_limit`: the monthly limit of the plan, null when the provider gives none;
 * - `provider_used`: the mail that the provider counted this month;
 * - `provider_sent_count`: `sent_count` at the time of that read, so that the mail sent since then adds to it.
 *
 * Null until the first read of the month. No personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_usage_months', static function (Blueprint $table) {
            $table->unsignedBigInteger('provider_limit')->nullable();
            $table->unsignedBigInteger('provider_used')->nullable();
            $table->unsignedBigInteger('provider_sent_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_usage_months', static function (Blueprint $table) {
            $table->dropColumn(['provider_limit', 'provider_used', 'provider_sent_count']);
        });
    }
};
