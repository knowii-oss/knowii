<?php

namespace App\Contracts\Mail;

use App\Models\User;

/**
 * "Try this address again" (#1478, ADR 0054, owner decision 9): the member lifts the suppression of the notification
 * email of the account.
 */
interface LiftsEmailSuppressions
{
    /**
     * Deletes the suppression row of the account (a complaint too: the member asks for it), then asks the mail provider,
     * best effort, to take the address off its list. A row that only counts soft bounces goes too. Nothing happens
     * without a row.
     */
    public function lift(User $user): void;
}
