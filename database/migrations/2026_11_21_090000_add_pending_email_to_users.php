<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058). users.pending_email is the
 * new address that the member asked for, with the length rule of users.email; users.pending_email_requested_at is the time
 * of the request. Both are null when no change waits. They are cleared when the change is confirmed or cancelled, and
 * after the period retention.pending_email_days (retention:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('pending_email')->nullable();
            $table->timestamp('pending_email_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_requested_at']);
        });
    }
};
