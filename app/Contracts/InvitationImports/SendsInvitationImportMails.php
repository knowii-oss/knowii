<?php

namespace App\Contracts\InvitationImports;

/**
 * Each method acts only when the row is `queued` with this `queued_count`: the job of an earlier time of the row changes
 * nothing
 */
interface SendsInvitationImportMails
{
    /**
     * Send the mail of a queued row (the job SendInvitationImportMail), or skip it when its invitation is gone or the
     * person joined meanwhile
     */
    public function send(int $rowId, int $queuedCount): void;

    /**
     * The mail budget holds the mail: the row is `held`
     */
    public function hold(int $rowId, int $queuedCount): void;

    /**
     * The mail failed after its retries: the row is `failed`
     */
    public function fail(int $rowId, int $queuedCount): void;
}
