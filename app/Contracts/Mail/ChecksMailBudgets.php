<?php

namespace App\Contracts\Mail;

use App\Support\MailBudgetCheck;
use Carbon\CarbonImmutable;

interface ChecksMailBudgets
{
    /**
     * Compares the mail of the month with the quota (#1477, ADR 0054): the alert at 80% and the hold of the notification
     * mail at the hold percent, each once a month, and the end of the hold when the numbers are below it again.
     */
    public function check(): MailBudgetCheck;

    /**
     * Notification mail is held: `held_until` of the row of the current month is in the future
     */
    public function isHeld(): bool;

    /**
     * Holds the notification mail until this time (the provider refused a mail for a quota). A later hold stays.
     *
     * @param  string  $reason  The error type of the provider, for the log
     */
    public function holdUntil(CarbonImmutable $until, string $reason): void;
}
