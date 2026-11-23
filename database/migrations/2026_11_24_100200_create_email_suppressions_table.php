<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The suppression of the notification email of an account (#1478, ADR 0054): one row per account at most, about one
 * address. App\Support\EmailSuppression is the only reader: a row applies only while `email` is the address of the
 * account, so a change of the address lifts it.
 *
 * - `user_id` is unique and `cascadeOnDelete`: the row goes with the account (ADR 0024).
 * - `email`: the address of the report of the provider, in lower case.
 * - `reason` and `suppressed_at` are null while the row only counts soft bounces (`soft_bounce_count`).
 * - `last_event_at`: the time of the last report, so that a late `delivered` from before a bounce changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', static function (Blueprint $table) {
            $table->id();
            $table->string('cuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 32)->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->smallInteger('soft_bounce_count')->default(0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
