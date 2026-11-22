<?php

namespace App\Support;

use App\Constants;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * The access groups and the member tags that a person gets when they join through an invitation (#1390, ADR 0061), and
 * later through an invite link (#1391). The one reader of the two input lists `access_groups` and `member_tags`: lists
 * of cuids of the same community, a duplicate counts once.
 *
 * Who may give them: access groups need `manageAccessGroupMembers`, member tags need `assignMemberTags` (the contract of
 * ADR 0060, point 3). Each gate is checked by itself, so a later change of one rule holds. A personal API token also
 * needs `groups:write` for groups and `member-tags:write` for tags (ADR 0060 decision 29: a token that syncs tags must
 * not give paid groups). An empty or missing list needs nothing.
 *
 * The order of the checks: the shape and the limits of the lists (400), the rights (403), then the cuids (400). A raw
 * list holds at most twice its limit, duplicates included.
 */
final class InvitationGrants
{
    public const string FIELD_ACCESS_GROUPS = 'access_groups';

    public const string FIELD_MEMBER_TAGS = 'member_tags';

    public const string GROUPS_ABILITY = 'groups:write';

    public const string TAGS_ABILITY = 'member-tags:write';

    public const string CANNOT_GIVE_ACCESS_GROUPS = 'You cannot give access groups';

    public const string CANNOT_GIVE_MEMBER_TAGS = 'You cannot give member tags';

    public const string NO_SUCH_ACCESS_GROUP = 'No access group of the community matches';

    public const string NO_SUCH_MEMBER_TAG = 'No member tag of the community matches';

    /**
     * The most items of a list as it comes in, duplicates included: twice the limit of the list
     */
    public const int MAX_RAW_ACCESS_GROUPS = 2 * Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY;

    public const int MAX_RAW_MEMBER_TAGS = 2 * Constants::MAX_MEMBER_TAGS_PER_MEMBER;

    /**
     * Read and check both lists for the person who gives them. The models come sorted by name without the case.
     *
     * @param  array<mixed>  $input
     * @return array{access_groups: Collection<int, AccessGroup>, member_tags: Collection<int, MemberTag>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function read(User $user, Community $community, array $input): array
    {
        // The raw lists are capped before their items are read: twice the limit leaves room for duplicates
        /** @var array{access_groups?: list<string>|null, member_tags?: list<string>|null} $validated */
        $validated = Validator::make($input, [
            self::FIELD_ACCESS_GROUPS => ['sometimes', 'nullable', 'array', 'list', 'max:'.self::MAX_RAW_ACCESS_GROUPS],
            self::FIELD_ACCESS_GROUPS.'.*' => ['string', 'max:255'],
            self::FIELD_MEMBER_TAGS => ['sometimes', 'nullable', 'array', 'list', 'max:'.self::MAX_RAW_MEMBER_TAGS],
            self::FIELD_MEMBER_TAGS.'.*' => ['string', 'max:255'],
        ], [
            self::FIELD_ACCESS_GROUPS.'.max' => self::tooManyAccessGroups(),
            self::FIELD_MEMBER_TAGS.'.max' => self::tooManyMemberTags(),
        ])->validate();

        $groupCuids = self::cuids($validated[self::FIELD_ACCESS_GROUPS] ?? null);
        $tagCuids = self::cuids($validated[self::FIELD_MEMBER_TAGS] ?? null);

        if (count($groupCuids) > Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY) {
            throw ValidationException::withMessages([self::FIELD_ACCESS_GROUPS => self::tooManyAccessGroups()]);
        }
        if (count($tagCuids) > Constants::MAX_MEMBER_TAGS_PER_MEMBER) {
            throw ValidationException::withMessages([self::FIELD_MEMBER_TAGS => self::tooManyMemberTags()]);
        }

        if ($groupCuids !== [] && ! self::mayGiveAccessGroups($user, $community)) {
            throw new AuthorizationException(self::CANNOT_GIVE_ACCESS_GROUPS);
        }
        if ($tagCuids !== [] && ! self::mayGiveMemberTags($user, $community)) {
            throw new AuthorizationException(self::CANNOT_GIVE_MEMBER_TAGS);
        }

        /** @var Collection<int, AccessGroup> $groups */
        $groups = $groupCuids === []
            ? new Collection
            : AccessGroup::query()->where('community_id', $community->id)->whereIn('cuid', $groupCuids)->orderByRaw('lower(name)')->orderBy('id')->get();
        if ($groups->count() !== count($groupCuids)) {
            throw ValidationException::withMessages([self::FIELD_ACCESS_GROUPS => self::NO_SUCH_ACCESS_GROUP]);
        }

        /** @var Collection<int, MemberTag> $tags */
        $tags = $tagCuids === []
            ? new Collection
            : MemberTag::query()->where('community_id', $community->id)->whereIn('cuid', $tagCuids)->orderByRaw('lower(name)')->orderBy('id')->get();
        if ($tags->count() !== count($tagCuids)) {
            throw ValidationException::withMessages([self::FIELD_MEMBER_TAGS => self::NO_SUCH_MEMBER_TAG]);
        }

        return [self::FIELD_ACCESS_GROUPS => $groups, self::FIELD_MEMBER_TAGS => $tags];
    }

    /**
     * The person may put access groups on an invitation: the gate, and `groups:write` for a personal API token
     */
    public static function mayGiveAccessGroups(User $user, Community $community): bool
    {
        return Gate::forUser($user)->allows('manageAccessGroupMembers', $community) && self::tokenAllows($user, self::GROUPS_ABILITY);
    }

    /**
     * The person may put member tags on an invitation: the gate, and `member-tags:write` for a personal API token
     */
    public static function mayGiveMemberTags(User $user, Community $community): bool
    {
        return Gate::forUser($user)->allows('assignMemberTags', $community) && self::tokenAllows($user, self::TAGS_ABILITY);
    }

    /**
     * Write the links of a new invitation. The caller holds the transaction of the create.
     *
     * @param  iterable<AccessGroup>  $groups
     * @param  iterable<MemberTag>  $tags
     */
    public static function attach(CommunityInvitation $invitation, iterable $groups, iterable $tags): void
    {
        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[] = $group->id;
        }
        $tagIds = [];
        foreach ($tags as $tag) {
            $tagIds[] = $tag->id;
        }
        if ($groupIds !== []) {
            $invitation->accessGroups()->attach($groupIds);
        }
        if ($tagIds !== []) {
            $invitation->memberTags()->attach($tagIds);
        }
    }

    /**
     * The groups and the tags of the lists that still exist, read again inside the transaction of the caller with a
     * shared lock: one that was deleted after the read of the lists is skipped, and one that is still there stays until
     * the commit. So the write of the links never refers to a group or a tag that is gone.
     *
     * @param  iterable<AccessGroup>  $groups
     * @param  iterable<MemberTag>  $tags
     * @return array{access_groups: Collection<int, AccessGroup>, member_tags: Collection<int, MemberTag>}
     */
    public static function lockPresent(iterable $groups, iterable $tags): array
    {
        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[] = $group->id;
        }
        $tagIds = [];
        foreach ($tags as $tag) {
            $tagIds[] = $tag->id;
        }

        /** @var Collection<int, AccessGroup> $presentGroups */
        $presentGroups = $groupIds === []
            ? new Collection
            : AccessGroup::query()->whereIn('id', $groupIds)->orderByRaw('lower(name)')->orderBy('id')->sharedLock()->get();
        /** @var Collection<int, MemberTag> $presentTags */
        $presentTags = $tagIds === []
            ? new Collection
            : MemberTag::query()->whereIn('id', $tagIds)->orderByRaw('lower(name)')->orderBy('id')->sharedLock()->get();

        return [self::FIELD_ACCESS_GROUPS => $presentGroups, self::FIELD_MEMBER_TAGS => $presentTags];
    }

    /**
     * Load the groups and the tags of a page of invitations for a reader, with one query per list for the whole page,
     * sorted by name without the case. A reader who does not pass `viewAccessGroups` gets no group, and one who does not
     * pass `viewMemberTags` no tag: the resource then gives an empty list.
     *
     * @param  iterable<CommunityInvitation>  $invitations
     */
    public static function loadFor(iterable $invitations, Community $community, ?User $reader): void
    {
        $items = [];
        foreach ($invitations as $invitation) {
            $items[] = $invitation;
        }
        $page = new Collection($items);
        if ($page->isEmpty() || $reader === null) {
            return;
        }

        $relations = [];
        if (Gate::forUser($reader)->allows('viewAccessGroups', $community)) {
            $relations['accessGroups'] = static fn (BelongsToMany $query) => $query->orderByRaw('lower(access_groups.name)')->orderBy('access_groups.id');
        }
        if (Gate::forUser($reader)->allows('viewMemberTags', $community)) {
            $relations['memberTags'] = static fn (BelongsToMany $query) => $query->orderByRaw('lower(member_tags.name)')->orderBy('member_tags.id');
        }
        if ($relations !== []) {
            $page->load($relations);
        }
    }

    /**
     * A session has every ability (a TransientToken, or no token in a page request, a job or a command)
     */
    private static function tokenAllows(User $user, string $ability): bool
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can($ability);
    }

    private static function tooManyAccessGroups(): string
    {
        return 'An invitation holds at most '.Constants::MAX_ACCESS_GROUPS_PER_COMMUNITY.' access groups';
    }

    private static function tooManyMemberTags(): string
    {
        return 'An invitation holds at most '.Constants::MAX_MEMBER_TAGS_PER_MEMBER.' tags';
    }

    /**
     * The cuids of a validated list, a duplicate once, in the order of the list. The items are compared as strings
     *
     * @param  list<string>|null  $list
     * @return list<string>
     */
    private static function cuids(?array $list): array
    {
        return array_values(array_unique($list ?? [], SORT_STRING));
    }
}
