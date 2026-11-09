<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;

interface InvitesCommunityMembers
{
    /**
     * Invite an email address with a role, and send the invitation email.
     *
     * @param  array<mixed>  $input
     */
    public function invite(User $user, Community $community, array $input): CommunityInvitation;
}
