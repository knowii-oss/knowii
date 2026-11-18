<?php

namespace App\Contracts\Mail;

use App\Support\MailUsage;

interface ReadsMailUsage
{
    /**
     * Reads the use of the month and of the day at the mail provider (#1477, ADR 0054). Null when it cannot: the mailer
     * is not the provider, the key is missing, or the call failed (logged once).
     */
    public function read(): ?MailUsage;
}
