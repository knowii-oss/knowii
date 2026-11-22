<?php

namespace App\Support;

use App\ApiRoutes;
use App\Enums\KnowiiMemberTagVisibility;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;

/**
 * The one class that decides which member tags a reader gets (#1389, ADR 0060). A reader who passes `viewMemberTags`
 * (the owner, the admins, the moderators with the "manage members" right) gets every tag; another member of the
 * community gets the tags with the visibility `members`; a person who is not a member, and no reader, get none. A tag
 * of the managers does not exist for another reader: in no list, no count, no filter, and its routes answer 404.
 *
 * Every answer with tags reads them here, with one query for all the users of a page. No resource reads the relation by
 * itself: MemberWithRoleResource reads what `attach()` put on each user, and an empty list when nothing did.
 */
final class MemberTagsOfMembers
{
    /**
     * The relation that `attach()` sets on each user: the tags that the reader may see, by name
     */
    public const string RELATION = 'visibleMemberTags';

    /**
     * The tags of each user in the community that the reader may see, sorted by name without the case, by user id. A user
     * without such a tag has no key.
     *
     * @param  iterable<User>  $users
     * @return array<int, Collection<int, MemberTag>>
     */
    public static function for(Community $community, iterable $users, ?User $reader): array
    {
        $visibility = self::visibilityFor($community, $reader);
        $ids = [];
        foreach ($users as $user) {
            $ids[] = $user->id;
        }
        if ($visibility === false || $ids === []) {
            return [];
        }

        $tags = MemberTag::query()
            ->join('member_tag_members as links', 'links.member_tag_id', '=', 'member_tags.id')
            ->where('member_tags.community_id', $community->id)
            ->whereIn('links.user_id', array_values(array_unique($ids)))
            ->when($visibility !== null, static fn ($query) => $query->where('member_tags.visibility', $visibility?->value))
            ->select('member_tags.*', 'links.user_id as tagged_user_id')
            ->orderByRaw('lower(member_tags.name)')
            ->orderBy('member_tags.id')
            ->get();

        $byUser = [];
        foreach ($tags as $tag) {
            $userId = $tag->getAttribute('tagged_user_id');
            if (! is_numeric($userId)) {
                continue;
            }
            $userId = (int) $userId;
            // The link column is not a field of the tag
            unset($tag['tagged_user_id']);
            $byUser[$userId] ??= new Collection;
            $byUser[$userId]->push($tag);
        }

        return $byUser;
    }

    /**
     * The tags of the community that the reader may see, by name without the case: every tag with its member count for a
     * reader who sees every tag, the tags of the members without a count for another member, and none for another reader
     *
     * @return Collection<int, MemberTag>
     */
    public static function ofCommunity(Community $community, ?User $reader): Collection
    {
        $visibility = self::visibilityFor($community, $reader);
        if ($visibility === false) {
            return new Collection;
        }

        $query = $community->memberTags()->orderByRaw('lower(name)')->orderBy('id');
        if ($visibility === null) {
            $query->withCount('members');
        } else {
            $query->where('visibility', $visibility->value);
        }

        return $query->get();
    }

    /**
     * Put on each user the tags that the reader may see (the relation RELATION), for MemberWithRoleResource
     *
     * @param  iterable<User>  $users
     */
    public static function attach(Community $community, iterable $users, ?User $reader): void
    {
        $tags = self::for($community, $users, $reader);
        foreach ($users as $user) {
            $user->setRelation(self::RELATION, $tags[$user->id] ?? new Collection);
        }
    }

    /**
     * The tags that `attach()` put on the user, or none
     *
     * @return Collection<int, MemberTag>
     */
    public static function of(User $user): Collection
    {
        $tags = $user->relationLoaded(self::RELATION) ? $user->getRelation(self::RELATION) : null;

        /** @var Collection<int, MemberTag> $tags */
        $tags = $tags instanceof Collection ? $tags : new Collection;

        return $tags;
    }

    /**
     * Whether the tag exists for the reader: a tag of the managers exists only for the people who see every tag, and a
     * tag of the members only for the members of its community. A route answers 404 for a tag that does not exist for
     * its reader, with the body of a cuid that does not exist.
     */
    public static function exists(MemberTag $tag, ?User $reader): bool
    {
        $visibility = self::visibilityFor($tag->community, $reader);

        return $visibility === null || ($visibility !== false && $tag->visibility === $visibility);
    }

    /**
     * The route binding of `{memberTag}` (AppServiceProvider): the tag of the community of the route, when it exists for
     * the reader. A binding runs after the sign-in and before the token abilities (`ability:`) and the controller, so a
     * reader who may not see the tag gets the 404 of a cuid that does not exist, with a session and with any token.
     *
     * @throws ModelNotFoundException<MemberTag>
     */
    public static function bindRoute(string $cuid, Route $route): MemberTag
    {
        // The explicit bindings run before the implicit ones: the community of the route is still its cuid here
        $community = $route->parameter(ApiRoutes::PARAM_COMMUNITY);
        $communityCuid = $community instanceof Community ? $community->cuid : $community;
        $reader = request()->user();

        $tag = is_string($communityCuid)
            ? MemberTag::query()->where('cuid', $cuid)->whereRelation('community', 'cuid', $communityCuid)->with('community')->first()
            : null;
        if ($tag === null || ! self::exists($tag, $reader instanceof User ? $reader : null)) {
            throw (new ModelNotFoundException)->setModel(MemberTag::class, [$cuid]);
        }

        return $tag;
    }

    /**
     * Whether the reader sees every tag of the community (null), only one visibility, or none (false)
     */
    private static function visibilityFor(Community $community, ?User $reader): KnowiiMemberTagVisibility|false|null
    {
        if ($reader === null || ! $reader->belongsToCommunity($community)) {
            return false;
        }

        return Gate::forUser($reader)->check('viewMemberTags', $community) ? null : KnowiiMemberTagVisibility::Members;
    }
}
