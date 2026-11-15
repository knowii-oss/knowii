<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\User;

interface JoinsCommunities
{
    /**
     * The user joins an open community by themselves, as a member (#1486).
     */
    public function join(User $user, Community $community): Community;
}
