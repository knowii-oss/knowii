<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The last time a worker ended a job of the mail queue (#1477, ADR 0054), kept in the cache. `app:health` reads it to
 * tell a long backlog (the worker runs, mail waits behind the rate limit) from a worker that does not run.
 */
final class MailWorkerActivity
{
    private const string CACHE_KEY = 'mail-queue:last-job-ended-at';

    private const int KEPT_SECONDS = 86400;

    public static function record(): void
    {
        Cache::put(self::CACHE_KEY, CarbonImmutable::now()->getTimestamp(), self::KEPT_SECONDS);
    }

    public static function lastAt(): ?CarbonImmutable
    {
        $timestamp = Cache::get(self::CACHE_KEY);

        // A cache store can give a number back as text
        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestampUTC((int) $timestamp) : null;
    }
}
