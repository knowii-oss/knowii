<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public page of a community (#1485, ADR 0044): what a visitor and a signed-in person who is not a member may
 * read of a community. It exists only for a community with at least one public space that is not a chat space. A chat
 * space is never part of it, nor are the spaces that are not public.
 */
final class PublicCommunity
{
    /**
     * Whether the community has a public space that is not a chat space
     */
    public static function exists(Community $community): bool
    {
        return $community->spaces()
            ->where('visibility', KnowiiSpaceVisibility::Public->value)
            ->where('type', '!=', KnowiiSpaceType::Chat->value)
            ->exists();
    }

    /**
     * Whether the reader belongs to the community: they get the community as before, not the public page
     */
    public static function isMember(?User $user, Community $community): bool
    {
        return $user !== null && $user->belongsToCommunity($community);
    }

    /**
     * Refuse the request for a community without a public page. A visitor gets 404 (the web app sends them to the
     * sign-in page, the API answers 404: nothing tells a community without a public space from one that does not
     * exist), a signed-in person who is not a member gets 403, as before.
     */
    public static function ensureExists(Community $community, Request $request): void
    {
        if (self::exists($community)) {
            return;
        }

        throw $request->user() === null ? new NotFoundHttpException : new AccessDeniedHttpException;
    }

    /**
     * The navigation that the public page shows: the public spaces that the reader may read, without any chat space
     * (a signed-in person may read a public chat space, the public page never names one)
     *
     * @return Collection<int, SpaceGroup>
     */
    public static function tree(Community $community, ?User $user): Collection
    {
        $groups = SpaceTree::for($community, $user);

        foreach ($groups as $group) {
            $group->setRelation('spaces', $group->spaces
                ->filter(fn (Space $space): bool => $space->type !== KnowiiSpaceType::Chat && $space->visibility === KnowiiSpaceVisibility::Public)
                ->values());
        }

        return $groups->filter(fn (SpaceGroup $group): bool => $group->spaces->isNotEmpty())->values();
    }

    /**
     * The newest posts of the public discussion spaces of the community: no marker of a deleted post, no post of another
     * space, no post of a chat space
     *
     * @return Collection<int, Post>
     */
    public static function newestPosts(Community $community): Collection
    {
        return Post::query()
            ->listed()
            ->where('community_id', $community->id)
            ->whereIn('space_id', Space::query()
                ->select('id')
                ->where('community_id', $community->id)
                ->where('type', KnowiiSpaceType::Discussion->value)
                ->where('visibility', KnowiiSpaceVisibility::Public->value))
            ->with(['author', 'space'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(Constants::PUBLIC_NEWEST_POSTS)
            ->get();
    }
}
