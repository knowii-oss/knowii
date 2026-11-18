<?php

namespace App\Support;

/**
 * What the mail provider says about the use of its team (#1477, ADR 0054): the month, and the day when the plan has a
 * daily limit. The count of the provider also holds the mail of the other senders of the same team.
 */
final readonly class MailUsage
{
    public function __construct(public MailUsagePeriod $monthly, public ?MailUsagePeriod $daily) {}
}
