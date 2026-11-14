<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface TransfersCommunityOwnership
{
    /**
     * The owner gives the community to one of its admins (#1339). Returns the new owner.
     *
     * @param  array<mixed>  $input  member (the user cuid of an admin) and password (of the current owner)
     */
    public function transfer(User $user, Community $community, array $input): User;
}
