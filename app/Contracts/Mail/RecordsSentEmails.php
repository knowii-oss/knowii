<?php

namespace App\Contracts\Mail;

interface RecordsSentEmails
{
    /**
     * Adds the recipients of a sent mail to the count of the current calendar month (UTC) in `mail_usage_months`, in one
     * statement (#1477, ADR 0054).
     */
    public function record(int $recipients): void;
}
