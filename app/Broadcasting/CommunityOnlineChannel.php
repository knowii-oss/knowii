<?php

namespace App\Broadcasting;

use App\Models\Community;
use App\Models\User;
use App\Support\Presence;

/**
 * Presence channel of a community (#1187, ADR 0027): the members who are online now. A member who hides their online
 * status never joins it, so no other member learns that they are connected. They read the list through the API.
 */
class CommunityOnlineChannel
{
    /**
     * @return array{cuid: string, name: string, username: string, avatar_url: ?string}|false
     */
    public function join(User $user, string $communityCuid): array|false
    {
        $community = Community::whereCuid($communityCuid)->first();

        if ($community === null || ! $user->showsOnlineStatus() || ! $user->belongsToCommunity($community)) {
            return false;
        }

        return Presence::info($user);
    }
}
