<?php

namespace App\Contracts\InviteLinks;

use App\Models\InviteLink;
use App\Models\User;

interface UpdatesInviteLinks
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, InviteLink $link, array $input): InviteLink;
}
