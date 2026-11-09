<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\User;

interface RemovesCommunityMembers
{
    /**
     * Remove the member whose user cuid is given from the community.
     */
    public function remove(User $user, Community $community, string $memberCuid): void;
}
