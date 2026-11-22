<?php

namespace App\Support\InvitationImports;

use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportRefusal;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\InvitationGrants;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The checks of the rows of an import of invitations (#1390, ADR 0061), at the upload and again at the confirm. A row has
 * one reason, the first of KnowiiInvitationImportRefusal in its order.
 *
 * The checks run with a fixed number of queries, whatever the number of rows: the groups and the tags of the community,
 * the members with these addresses (the owner too), the join blocks of the accounts with these addresses, and the pending
 * invitations, one query each. Addresses are compared in lower case. Nothing else of an address shows: an address with an
 * account and no block is a valid row, as an address without an account.
 */
final class InvitationImportChecks
{
    /**
     * @param  list<InvitationImportCheckInput>  $inputs
     * @param  iterable<AccessGroup>  $defaultGroups  Given to every row (InvitationGrants checked them)
     * @param  iterable<MemberTag>  $defaultTags  Given to every row (InvitationGrants checked them)
     * @return list<InvitationImportCheckedRow>
     */
    public static function check(User $user, Community $community, array $inputs, KnowiiCommunityMemberRole $defaultRole, iterable $defaultGroups = [], iterable $defaultTags = []): array
    {
        $groups = AccessGroup::query()->where('community_id', $community->id)->orderBy('id')->get();
        $tags = MemberTag::query()->where('community_id', $community->id)->orderBy('id')->get();
        $mayGiveGroups = InvitationGrants::mayGiveAccessGroups($user, $community);
        $mayGiveTags = InvitationGrants::mayGiveMemberTags($user, $community);
        /** @var array<string, bool> $grantable */
        $grantable = [];
        foreach (KnowiiCommunityMemberRole::cases() as $role) {
            $grantable[$role->value] = CommunityRoles::canGrant($user, $community, $role);
        }
        $baseGroups = self::list($defaultGroups);
        $baseTags = self::list($defaultTags);

        // The first pass: what the file says, with no query
        $rows = [];
        /** @var array<string, int> $firstRowOf */
        $firstRowOf = [];
        foreach ($inputs as $input) {
            $full = mb_strtolower(trim($input->email));
            $email = mb_substr($full, 0, 255);
            $row = new InvitationImportCheckedRow($input->rowNumber, $email);

            $roleCell = mb_strtolower(trim($input->role));
            $role = $roleCell === '' ? $defaultRole : KnowiiCommunityMemberRole::tryFrom($roleCell);
            $row->role = $role;

            [$rowGroups, $groupRefusal, $groupDetail] = self::groups($groups, $input->accessGroupNames);
            [$rowTags, $tagRefusal, $tagDetail] = self::tags($tags, $input->memberTagNames);
            $row->groups = self::merge($baseGroups, $rowGroups);
            $row->tags = self::merge($baseTags, $rowTags);
            $row->groupNames = self::names($row->groups, $groupRefusal !== null ? $input->accessGroupNames : []);
            $row->tagNames = self::names($row->tags, $tagRefusal !== null ? $input->memberTagNames : []);

            $validEmail = $input->problem === null && self::isEmail($full);
            $duplicateOf = $validEmail ? ($firstRowOf[$email] ?? null) : null;
            if ($validEmail && $duplicateOf === null) {
                $firstRowOf[$email] = $input->rowNumber;
            }

            $refusal = match (true) {
                $input->problem !== null => [$input->problem, null],
                ! $validEmail => [KnowiiInvitationImportRefusal::InvalidEmail, null],
                $duplicateOf !== null => [KnowiiInvitationImportRefusal::DuplicateInFile, (string) $duplicateOf],
                $role === null => [KnowiiInvitationImportRefusal::UnknownRole, null],
                ! $grantable[$role->value] => [KnowiiInvitationImportRefusal::RoleNotAllowed, null],
                $input->accessGroupNames !== [] && ! $mayGiveGroups => [KnowiiInvitationImportRefusal::AccessGroupsNotAllowed, null],
                $groupRefusal !== null => [$groupRefusal, $groupDetail],
                $input->memberTagNames !== [] && ! $mayGiveTags => [KnowiiInvitationImportRefusal::TagsNotAllowed, null],
                $tagRefusal !== null => [$tagRefusal, $tagDetail],
                count($row->tags) > Constants::MAX_MEMBER_TAGS_PER_MEMBER => [KnowiiInvitationImportRefusal::TooManyTags, null],
                default => null,
            };
            if ($refusal !== null) {
                [$row->reason, $row->detail] = $refusal;
            }
            $rows[] = $row;
        }

        // The second pass: the people, one query each for the whole file
        $emails = [];
        foreach ($rows as $row) {
            if ($row->reason === null) {
                $emails[$row->email] = true;
            }
        }
        $emails = array_keys($emails);
        if ($emails === []) {
            return $rows;
        }

        $members = array_flip(self::memberEmails($community, $emails));
        $blocked = array_flip(self::blockedEmails($community, $emails));
        $pending = self::pendingInvitations($community, $emails);
        foreach ($rows as $row) {
            if ($row->reason !== null) {
                continue;
            }
            if (isset($members[$row->email])) {
                $row->reason = KnowiiInvitationImportRefusal::AlreadyMember;
            } elseif (isset($blocked[$row->email])) {
                $row->reason = KnowiiInvitationImportRefusal::RemovedByManager;
            } elseif (isset($pending[$row->email])) {
                $row->reason = KnowiiInvitationImportRefusal::PendingInvitation;
                $row->detail = $pending[$row->email];
            }
        }

        return $rows;
    }

    /**
     * The names of a cell: split on `|`, each part trimmed with its spaces made one, the empty parts dropped, a duplicate
     * without the case once
     *
     * @return list<string>
     */
    public static function splitNames(string $cell): array
    {
        $names = [];
        $seen = [];
        foreach (explode('|', $cell) as $part) {
            $name = MemberTag::cleanName($part);
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /**
     * The addresses of the members of the community among these (the owner too), in lower case
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    public static function memberEmails(Community $community, array $emails): array
    {
        /** @var list<string> $found */
        $found = DB::table('users')
            ->whereIn(DB::raw('lower(email)'), $emails)
            ->where(static fn (QueryBuilder $query) => $query
                ->where('users.id', $community->owner_id)
                ->orWhereIn('users.id', DB::table('community_members')->where('community_id', $community->id)->select('user_id')))
            ->selectRaw('lower(email) as address')
            ->pluck('address')
            ->filter(static fn ($email): bool => is_string($email))
            ->values()
            ->all();

        return $found;
    }

    /**
     * The addresses among these of an account that a manager removed from the community (a join block), in lower case
     *
     * @param  list<string>  $emails
     * @return list<string>
     */
    private static function blockedEmails(Community $community, array $emails): array
    {
        /** @var list<string> $found */
        $found = DB::table('community_join_blocks')
            ->join('users', 'users.id', '=', 'community_join_blocks.user_id')
            ->where('community_join_blocks.community_id', $community->id)
            ->whereIn(DB::raw('lower(users.email)'), $emails)
            ->selectRaw('lower(users.email) as address')
            ->pluck('address')
            ->filter(static fn ($email): bool => is_string($email))
            ->values()
            ->all();

        return $found;
    }

    /**
     * The end of the valid pending invitation of each of these addresses in the community (ISO 8601), by address
     *
     * @param  list<string>  $emails
     * @return array<string, string>
     */
    private static function pendingInvitations(Community $community, array $emails): array
    {
        $since = CarbonImmutable::now()->subDays(CommunityInvitation::VALIDITY_DAYS);
        $pending = [];
        foreach (DB::table('community_invitations')
            ->where('community_id', $community->id)
            ->whereIn(DB::raw('lower(email)'), $emails)
            ->where('created_at', '>', $since)
            ->get([DB::raw('lower(email) as email'), 'created_at']) as $invitation) {
            $email = is_string($invitation->email ?? null) ? $invitation->email : '';
            $createdAt = is_string($invitation->created_at ?? null) ? $invitation->created_at : 'now';
            $pending[$email] = CarbonImmutable::parse($createdAt)->addDays(CommunityInvitation::VALIDITY_DAYS)->toIso8601ZuluString();
        }

        return $pending;
    }

    /**
     * The groups of the names: the group with exactly that name, else the one group whose name is equal without the case.
     * Two groups that differ only in the case and no exact match: ambiguous. The first problem wins
     *
     * @param  Collection<int, AccessGroup>  $groups
     * @param  list<string>  $names
     * @return array{0: list<AccessGroup>, 1: KnowiiInvitationImportRefusal|null, 2: string|null}
     */
    private static function groups(Collection $groups, array $names): array
    {
        $found = [];
        foreach ($names as $name) {
            $exact = $groups->first(static fn (AccessGroup $group): bool => $group->name === $name);
            if ($exact instanceof AccessGroup) {
                $found[] = $exact;

                continue;
            }
            $lower = mb_strtolower($name);
            $matches = $groups->filter(static fn (AccessGroup $group): bool => mb_strtolower($group->name) === $lower)->values();
            if ($matches->count() > 1) {
                return [[], KnowiiInvitationImportRefusal::AmbiguousAccessGroup, mb_substr($name, 0, 64)];
            }
            $match = $matches->first();
            if (! $match instanceof AccessGroup) {
                return [[], KnowiiInvitationImportRefusal::UnknownAccessGroup, mb_substr($name, 0, 64)];
            }
            $found[] = $match;
        }

        return [$found, null, null];
    }

    /**
     * The tags of the names, without the case (a tag name is unique without the case). Never a new tag
     *
     * @param  Collection<int, MemberTag>  $tags
     * @param  list<string>  $names
     * @return array{0: list<MemberTag>, 1: KnowiiInvitationImportRefusal|null, 2: string|null}
     */
    private static function tags(Collection $tags, array $names): array
    {
        $found = [];
        foreach ($names as $name) {
            $lower = mb_strtolower($name);
            $match = $tags->first(static fn (MemberTag $tag): bool => mb_strtolower($tag->name) === $lower);
            if (! $match instanceof MemberTag) {
                return [[], KnowiiInvitationImportRefusal::UnknownTag, mb_substr($name, 0, 64)];
            }
            $found[] = $match;
        }

        return [$found, null, null];
    }

    /**
     * @template TModel of AccessGroup|MemberTag
     *
     * @param  list<TModel>  $base
     * @param  list<TModel>  $more
     * @return list<TModel>
     */
    private static function merge(array $base, array $more): array
    {
        $merged = [];
        foreach ([...$base, ...$more] as $model) {
            $merged[$model->id] = $model;
        }

        return array_values($merged);
    }

    /**
     * The names to store: those of the models, then the names of the cell that were not found
     *
     * @param  list<AccessGroup>|list<MemberTag>  $models
     * @param  list<string>  $unresolved
     * @return list<string>
     */
    private static function names(array $models, array $unresolved): array
    {
        $names = [];
        foreach ($models as $model) {
            $names[] = $model->name;
        }
        foreach ($unresolved as $name) {
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @template TModel
     *
     * @param  iterable<TModel>  $items
     * @return list<TModel>
     */
    private static function list(iterable $items): array
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = $item;
        }

        return $list;
    }

    private static function isEmail(string $email): bool
    {
        return $email !== '' && Validator::make(['email' => $email], ['email' => ['required', 'string', 'email:strict', 'max:255']])->passes();
    }
}
