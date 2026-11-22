<?php

namespace App\Contracts\InviteLinks;

use App\Models\InviteLink;
use App\Models\User;

interface DeletesInviteLinks
{
    public function delete(User $user, InviteLink $link): void;
}
