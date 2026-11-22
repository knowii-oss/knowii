<?php

namespace App\Contracts\InviteLinks;

use App\Models\Community;
use App\Models\User;

interface JoinsCommunitiesThroughInviteLinks
{
    public function join(User $user, string $token): Community;

    /**
     * The same join, for the sha256 of the token: the page of a link keeps the hash in the session, never the token
     */
    public function joinWithTokenHash(User $user, string $tokenHash): Community;
}
