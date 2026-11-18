<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The result of one run of `mail:check-budget` (#1477, ADR 0054). `quota` is null when the instance has none: then
 * `percent` is null too, and the check did nothing. `source` is `provider` (the usage of the mail provider) or `local`
 * (the count of this instance).
 */
final readonly class MailBudgetCheck
{
    public const string SOURCE_PROVIDER = 'provider';

    public const string SOURCE_LOCAL = 'local';

    public function __construct(
        public int $used,
        public ?int $quota,
        public ?int $percent,
        public string $source,
        public ?CarbonImmutable $heldUntil,
    ) {}

    public function held(): bool
    {
        return $this->heldUntil !== null && $this->heldUntil->isFuture();
    }
}
