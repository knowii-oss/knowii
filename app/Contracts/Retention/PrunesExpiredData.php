<?php

namespace App\Contracts\Retention;

interface PrunesExpiredData
{
    /**
     * Deletes the data whose retention period is over (ADR 0030).
     *
     * @return array<string, int> the number of deleted rows per kind of data (counted, not deleted, with $dryRun)
     */
    public function prune(bool $dryRun = false): array;

    /**
     * Deletes the expired entries of the database cache, and the expired locks: the pending sign-ups (ADR 0065) and the
     * sign-in links go within an hour after their end. Nothing with another cache store, which ends its entries itself.
     *
     * @return int the number of deleted rows (counted, not deleted, with $dryRun)
     */
    public function pruneExpiredCache(bool $dryRun = false): int;
}
