<?php

namespace App\Contracts\Users;

use App\Models\User;

interface ExportsUsers
{
    /**
     * Writes the data of a member to one zip file on the local disk, under `exports/` (#1343): the copy of the data
     * that the privacy policy promises (GDPR art. 15 and 20). Only what the member wrote or what is about them, never
     * what other members wrote.
     *
     * @return string the absolute path of the zip file
     */
    public function export(User $user): string;
}
