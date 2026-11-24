<?php

namespace App\Console\Commands;

use App\Contracts\Retention\PrunesExpiredData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes the expired entries of the database cache every hour (ADR 0065): a pending sign-up holds a name and an address,
 * encrypted, and the database cache removes an expired row only when its key is read again. So a sign-up that nobody
 * finished goes at most an hour after the end of its link, as the privacy policy says. `retention:prune` does the same
 * once a day with the other data.
 */
#[Signature('cache:prune-expired {--dry-run : Count the rows without deleting them}')]
#[Description('Delete the expired entries of the database cache')]
class PruneExpiredCacheCommand extends Command
{
    public function handle(PrunesExpiredData $pruner): int
    {
        $count = $pruner->pruneExpiredCache((bool) $this->option('dry-run'));
        $this->info(sprintf('%s: %d', $this->option('dry-run') ? 'To delete' : 'Deleted', $count));

        return self::SUCCESS;
    }
}
