<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface UpdatesCommunityJoinPolicy
{
    /**
     * Change who can join the community (#1486).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Community $community, array $input): Community;
}
