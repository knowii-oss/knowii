<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\User;

interface UpdatesCommunityMemberRoles
{
    /**
     * Change the role of the member whose user cuid is given. Returns the member with their new role.
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Community $community, string $memberCuid, array $input): User;
}
