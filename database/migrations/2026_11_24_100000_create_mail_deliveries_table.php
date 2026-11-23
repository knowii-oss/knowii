<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery log (#1478, ADR 0054): one row per mail that the platform hands to the mailer, so that a report of the mail
 * provider finds the mail that it is about, and so that a notification mail goes out at most once.
 *
 * - `kind`: App\Enums\KnowiiMailKind (a group of notification email, `invitation`, `account`, `asked_for`, `operator`).
 * - `user_id` is `cascadeOnDelete`: the deletion of an account removes its delivery log (ADR 0024).
 * - `community_invitation_id` is `nullOnDelete`: a late report on a deleted invitation still reads `kind = invitation`, so
 *   it never changes an account with the same address.
 * - At most one of the two is set (ADR 0014). The alert of the operator has neither.
 * - `notification_id` is `cascadeOnDelete`: the row of a notification mail goes with its notification. One row per
 *   notification and channel.
 * - `provider_email_id`: the id that the provider gives the mail (`X-Resend-Email-ID`), null with another transport.
 * - No address, no subject, no text. The prune step `mail_deliveries` deletes a row after the retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_deliveries', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->string('kind', 32);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('community_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('email');
            $table->string('status', 16);
            $table->string('provider_email_id')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('community_invitation_id');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE mail_deliveries ADD CONSTRAINT mail_deliveries_one_owner CHECK (user_id IS NULL OR community_invitation_id IS NULL)');
        DB::statement('CREATE UNIQUE INDEX mail_deliveries_notification_channel_unique ON mail_deliveries (notification_id, channel) WHERE notification_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_deliveries');
    }
};
