<?php

namespace App\Support;

use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiInviteLinkJoinState;
use App\Enums\KnowiiInviteLinkStatus;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The reads of the invite links (#1391, ADR 0062): what the managers see of a link, and what the person who opens a link
 * sees. The person sees the community and their state only: never the name of the link, its groups, its tags, its
 * counts or its end.
 */
final class InviteLinks
{
    /**
     * Load the groups (with whether a rule on an entitlement feeds each one) and the tags of links, by name without the
     * case, for the managers. One query per list for the whole page.
     *
     * @param  iterable<InviteLink>  $links
     */
    public static function loadForManagers(iterable $links): void
    {
        $items = [];
        foreach ($links as $link) {
            $items[] = $link;
        }
        if ($items === []) {
            return;
        }

        (new Collection($items))->load([
            'accessGroups' => static fn (BelongsToMany $query) => $query->withExists('rules')->orderByRaw('lower(access_groups.name)')->orderBy('access_groups.id'),
            'memberTags' => static fn (BelongsToMany $query) => $query->orderByRaw('lower(member_tags.name)')->orderBy('member_tags.id'),
        ]);
    }

    /**
     * The link of a token when it works, with its community, or null: an unknown token, a link that is off, ended or full,
     * and a link of a personal community. The caller gives one answer for all of them.
     */
    public static function usable(string $token): ?InviteLink
    {
        return self::usableLink(InviteLink::findByToken($token));
    }

    /**
     * The same as usable(), for the sha256 of a token: the session of the page of a link keeps the hash only
     */
    public static function usableByHash(string $tokenHash): ?InviteLink
    {
        return self::usableLink(InviteLink::findByTokenHash($tokenHash));
    }

    private static function usableLink(?InviteLink $link): ?InviteLink
    {
        if ($link === null || $link->status() !== KnowiiInviteLinkStatus::Active) {
            return null;
        }

        $community = Community::query()->find($link->community_id);
        if ($community === null || $community->visibility === KnowiiCommunityVisibility::Personal) {
            return null;
        }
        $link->setRelation('community', $community);

        return $link;
    }

    /**
     * What the account can do with a link of the community: join, or nothing because it is a member (the owner too), or
     * nothing because a manager removed it
     */
    public static function stateFor(User $user, Community $community): KnowiiInviteLinkJoinState
    {
        if ($user->belongsToCommunity($community)) {
            return KnowiiInviteLinkJoinState::Member;
        }
        if ($community->joinBlocks()->where('user_id', $user->id)->exists()) {
            return KnowiiInviteLinkJoinState::Blocked;
        }

        return KnowiiInviteLinkJoinState::Join;
    }
}
