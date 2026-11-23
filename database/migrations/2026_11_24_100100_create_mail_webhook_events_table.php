<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The events of the webhook of the mail provider that the route accepted (#1478, ADR 0054): the id of the event
 * (`svix-id`, the same on every retry of the provider) and its type. The route inserts the id after a valid signature
 * check, and only then: an event that is already here is not handled again. No address, no header and no body. The
 * prune step `mail_webhook_events` deletes a row after the retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_webhook_events', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->string('provider_event_id')->unique();
            $table->string('type', 64);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_webhook_events');
    }
};
