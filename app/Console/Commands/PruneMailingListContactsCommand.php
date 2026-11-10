<?php

namespace App\Console\Commands;

use App\Contracts\Retention\PrunesMailingListContacts;
use App\Exceptions\TechnicalException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes the old waitlist contacts and the unconfirmed or unsubscribed contacts of the EmailOctopus lists
 * (ADR 0030, #77). Does nothing while EmailOctopus is not configured.
 */
#[Signature('mailing-lists:prune {--dry-run : Count the contacts without deleting them}')]
#[Description('Delete the mailing list contacts whose retention period is over')]
class PruneMailingListContactsCommand extends Command
{
    public function handle(PrunesMailingListContacts $pruner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $counts = $pruner->prune($dryRun);
        } catch (TechnicalException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['List', $dryRun ? 'To delete' : 'Deleted'], array_map(null, array_keys($counts), array_values($counts)));

        return self::SUCCESS;
    }
}
