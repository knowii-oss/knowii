<?php

namespace App\Contracts\CommunityMembers;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;

interface CancelsCommunityInvitations
{
    public function cancel(User $user, Community $community, CommunityInvitation $invitation): void;
}
