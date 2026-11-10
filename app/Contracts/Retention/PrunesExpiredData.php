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
}
