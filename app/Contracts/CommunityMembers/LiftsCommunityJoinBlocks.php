<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\User;

interface LiftsCommunityJoinBlocks
{
    /**
     * Let the person whose user cuid is given join the community again by themselves (#1486).
     */
    public function lift(User $user, Community $community, string $memberCuid): void;
}
