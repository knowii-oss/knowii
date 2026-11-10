<?php

namespace App\Contracts\Retention;

interface PrunesMailingListContacts
{
    /**
     * Deletes the EmailOctopus contacts whose retention period is over (ADR 0030): waitlist contacts after
     * Retention::waitlistMonths(), unconfirmed and unsubscribed contacts of every list after
     * Retention::inactiveContactsDays().
     *
     * @return array<string, int> the number of deleted contacts per list (counted, not deleted, with $dryRun)
     */
    public function prune(bool $dryRun = false): array;
}
