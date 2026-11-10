<?php

namespace App\Broadcasting;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\User;
use App\Support\Presence;

/**
 * Presence channel of a chat space (#1187, ADR 0027): who is in the space now, and who is typing (client events,
 * which Reverb sends with the cuid of the sender). Same rule as reading the space (SpacePolicy::view), for the members
 * who show their online status only.
 */
class SpacePresenceChannel
{
    /**
     * @return array{cuid: string, name: string, username: string, avatar_url: ?string}|false
     */
    public function join(User $user, string $communityCuid, string $spaceCuid): array|false
    {
        $community = Community::whereCuid($communityCuid)->first();
        if ($community === null) {
            return false;
        }

        $space = $community->spaces()->where('cuid', $spaceCuid)->first();
        if ($space === null || $space->type !== KnowiiSpaceType::Chat || ! $user->showsOnlineStatus()) {
            return false;
        }

        if (! $user->belongsToCommunity($community) || ! $user->can('view', $space)) {
            return false;
        }

        return Presence::info($user);
    }
}
