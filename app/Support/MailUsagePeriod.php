<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The use of one period (a month or a day) at the mail provider (#1477, ADR 0054): the mail counted, the limit of the
 * plan (null: none), and when the count starts again.
 */
final readonly class MailUsagePeriod
{
    public function __construct(public int $used, public ?int $limit, public ?CarbonImmutable $resetsAt) {}
}
