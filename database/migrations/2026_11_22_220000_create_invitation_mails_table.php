<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per invitation mail that went to the queue (#1390, ADR 0061): a single invitation, a row of an import, a send
 * again. The daily caps of the invitation mails count these rows: per community, and per account over all its
 * communities (App\Support\InvitationMailCaps).
 *
 * - No `cuid` and no `updated_at`: no route and no answer names a row, and a row never changes.
 * - Both keys are `nullOnDelete`: a deleted community leaves the rows of the account that sent them, and a deleted account
 *   leaves the rows of the community, so each cap still counts them. No record that a manager deletes holds the count.
 * - No address and no text: the community, the account and the time. The prune step `invitation_mails` (daily) deletes
 *   the rows older than 24 hours, the ones that count no more; a row stays 2 days at most.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_mails', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['community_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_mails');
    }
};
