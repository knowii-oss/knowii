<?php

namespace App\Console\Commands;

use App\Contracts\Retention\PrunesExpiredData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes the expired invitations, API tokens, failed jobs, password reset tokens, sessions and cache entries
 * (ADR 0030, #1306). The periods come from App\Support\Retention, like the numbers of the privacy policy.
 */
#[Signature('retention:prune {--dry-run : Count the rows without deleting them}')]
#[Description('Delete the data whose retention period is over')]
class PruneExpiredDataCommand extends Command
{
    public function handle(PrunesExpiredData $pruner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $pruner->prune($dryRun);

        $this->table(['Data', $dryRun ? 'To delete' : 'Deleted'], array_map(null, array_keys($counts), array_values($counts)));

        return self::SUCCESS;
    }
}
