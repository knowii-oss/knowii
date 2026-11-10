<?php

namespace App\Support;

use App\Models\User;

/**
 * What a presence channel tells the other members about a member (#1187, ADR 0027): the public summary that shows
 * next to what they write, nothing more. The identifier is the cuid (User::getAuthIdentifierForBroadcasting).
 */
final class Presence
{
    /**
     * @return array{cuid: string, name: string, username: string, avatar_url: ?string}
     */
    public static function info(User $user): array
    {
        return [
            'cuid' => $user->cuid,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
