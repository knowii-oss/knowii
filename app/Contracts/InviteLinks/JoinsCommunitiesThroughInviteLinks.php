<?php

namespace App\Contracts\InviteLinks;

use App\Models\Community;
use App\Models\User;

interface JoinsCommunitiesThroughInviteLinks
{
    public function join(User $user, string $token): Community;
}
