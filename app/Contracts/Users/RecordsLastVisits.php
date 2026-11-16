<?php

namespace App\Contracts\Users;

use App\Models\User;

interface RecordsLastVisits
{
    /**
     * Write today's date (UTC) as the last visit of the member, at most once a day. Returns the date that was replaced
     * (`YYYY-MM-DD`) when it was 7 days old or more, else null: the caller keeps it for the "Catch up" card (#1476).
     */
    public function record(User $user): ?string;
}
