<?php

namespace App\Contracts\InviteLinks;

use App\Models\Community;
use App\Models\InviteLink;
use App\Models\User;

interface CreatesInviteLinks
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): InviteLink;
}
