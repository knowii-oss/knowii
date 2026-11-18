<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The count of sent mail and the budget of the month (#1477, ADR 0054). One row per calendar month (UTC):
 *
 * - `sent_count`: the recipients of every mail that the transport accepted (`App\Listeners\CountSentEmails`), whatever
 *   the mailer, the queue or the kind of mail.
 * - `alert_80_sent_at` and `hold_notified_at`: the alert of `mail:check-budget` goes once a month.
 * - `held_until`: notification mail is held while this time is in the future. A new month has a new row, so the hold of
 *   the month before lifts by itself.
 *
 * No personal data: no address, no account, no text. So no retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_usage_months', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            // The first day of the month, in UTC
            $table->date('month')->unique();
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->timestamp('alert_80_sent_at')->nullable();
            $table->timestamp('hold_notified_at')->nullable();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_usage_months');
    }
};
