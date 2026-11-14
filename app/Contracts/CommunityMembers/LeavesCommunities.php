<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\User;

interface LeavesCommunities
{
    /**
     * The user leaves the community (#1338).
     */
    public function leave(User $user, Community $community): void;
}
