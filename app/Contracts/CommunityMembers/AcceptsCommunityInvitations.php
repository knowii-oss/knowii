<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;

interface AcceptsCommunityInvitations
{
    /**
     * The signed-in user joins the community of the invitation with its role. Returns the community.
     */
    public function accept(User $user, CommunityInvitation $invitation): Community;
}
