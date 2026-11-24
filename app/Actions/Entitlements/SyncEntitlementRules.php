<?php

namespace App\Actions\Entitlements;

use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroupMember;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Support\AccessGroupMemberships;
use App\Support\LiveConnections;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entitlement rules feed the access groups (#1386, ADR 0047). The wanted groups of a member in a community are the groups
 * with a rule whose key the member holds. The result is the `by_rule` flag of the membership: it never touches `manual`,
 * and the reading rule of ADR 0041 (a row in `access_group_members`) does not change.
 *
 * - Only a member of the community, only a community that the operator lists, only the keys of the issuer of this instance.
 * - The keys are the ones that were stored (the read at the provider is `RefreshEntitlements`). A member without keys has
 *   no rule membership.
 * - `AccessGroupsChanged` (the prune of the notifications of ADR 0042 follows from it) and `SpaceTreeChanged` (the
 *   navigation of the open pages) go out once per community that changed.
 * - The log holds cuids and counts, never a key or a subject.
 * - The live connections of each member whose groups changed end (ADR 0075): the pages connect again with the groups of now.
 */
final class SyncEntitlementRules implements SyncsEntitlementRules
{
    private const int CHUNK = 200;

    final public function syncMember(User $user): void
    {
        if (! OidcEntitlements::enabled()) {
            return;
        }

        $keys = self::keysOf([$user->id])[$user->id] ?? [];
        $communities = Community::query()
            ->whereIn('slug', OidcEntitlements::communitySlugs())
            ->whereHas('users', static fn ($members) => $members->where('users.id', $user->id))
            ->with('accessGroups.rules')
            ->get();

        foreach ($communities as $community) {
            if (self::apply($community, $user->id, $keys)) {
                self::announce($community->cuid);
                Log::info('Entitlement rules changed the access groups of a member', ['community' => $community->cuid, 'member' => $user->cuid]);
                LiveConnections::endFor($user);
            }
        }
    }

    final public function syncCommunity(Community $community): void
    {
        if (! OidcEntitlements::allows($community)) {
            return;
        }

        $community->load('accessGroups.rules');
        $changedMembers = 0;

        CommunityMember::query()->where('community_id', $community->id)->chunkById(self::CHUNK, function (Collection $members) use ($community, &$changedMembers): void {
            /** @var Collection<int, CommunityMember> $members */
            $keysByUser = self::keysOf(array_values($members->map(static fn (CommunityMember $member): int => $member->user_id)->all()));
            $changed = [];
            foreach ($members as $member) {
                if (self::apply($community, $member->user_id, $keysByUser[$member->user_id] ?? [])) {
                    $changedMembers++;
                    $changed[] = $member->user_id;
                }
            }
            LiveConnections::endForIds($changed);
        });

        if ($changedMembers > 0) {
            self::announce($community->cuid);
        }
        Log::info('Entitlement rules synced for a community', ['community' => $community->cuid, 'changed_members' => $changedMembers]);
    }

    final public function clearMember(User $user): void
    {
        $communityIds = DB::transaction(function () use ($user): array {
            $ids = [];
            $rows = AccessGroupMember::query()->where('user_id', $user->id)->where('by_rule', true)->with('accessGroup')->lockForUpdate()->get();
            foreach ($rows as $row) {
                AccessGroupMemberships::releaseByRule($row);
                $ids[$row->accessGroup->community_id] = $row->accessGroup->community_id;
            }

            return array_values($ids);
        });

        $this->announceAll($communityIds);
        if ($communityIds !== []) {
            // Written when the change is stored: a takeover calls this inside its own transaction (ADR 0059)
            $context = ['member' => $user->cuid, 'communities' => count($communityIds)];
            DB::afterCommit(static fn () => Log::info('Entitlement rule memberships of a member removed', $context));
            LiveConnections::endFor($user);
        }
    }

    final public function clearUnlisted(): int
    {
        $query = AccessGroupMember::query()->where('by_rule', true)->with('accessGroup');
        if (OidcEntitlements::enabled()) {
            // A community that is still listed keeps its rule memberships. With an empty list every community is outside
            $query->whereHas('accessGroup.community', static fn ($communities) => $communities->whereNotIn('slug', OidcEntitlements::communitySlugs()));
        }

        $cleared = 0;
        $communityIds = [];
        $userIds = [];
        $query->chunkById(self::CHUNK, function (Collection $rows) use (&$cleared, &$communityIds, &$userIds): void {
            /** @var Collection<int, AccessGroupMember> $rows */
            foreach ($rows as $row) {
                $communityId = DB::transaction(static function () use ($row): ?int {
                    $locked = AccessGroupMember::query()->whereKey($row->id)->lockForUpdate()->first();
                    if ($locked === null || ! $locked->by_rule) {
                        return null;
                    }
                    AccessGroupMemberships::releaseByRule($locked);

                    return $row->accessGroup->community_id;
                });
                if ($communityId !== null) {
                    $cleared++;
                    $communityIds[$communityId] = $communityId;
                    $userIds[$row->user_id] = $row->user_id;
                }
            }
        });

        $this->announceAll(array_values($communityIds));
        LiveConnections::endForIds(array_values($userIds));
        if ($cleared > 0) {
            Log::notice('Entitlement rule memberships cleared where no rule can apply', ['memberships' => $cleared, 'communities' => count($communityIds)]);
        }

        return $cleared;
    }

    /**
     * Make the rule memberships of one member in one community the wanted ones. The community has its groups and their
     * rules loaded.
     *
     * @param  list<string>  $keys
     */
    private static function apply(Community $community, int $userId, array $keys): bool
    {
        $groupIds = [];
        $wanted = [];
        foreach ($community->accessGroups as $group) {
            $groupIds[] = $group->id;
            foreach ($group->rules as $rule) {
                if (in_array($rule->entitlement_key, $keys, true)) {
                    $wanted[] = $group->id;
                    break;
                }
            }
        }

        return $groupIds !== [] && AccessGroupMemberships::setByRule($userId, $groupIds, $wanted);
    }

    /**
     * The stored keys of some members at the issuer of this instance
     *
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    private static function keysOf(array $userIds): array
    {
        $keys = [];
        foreach (UserEntitlement::query()->whereIn('user_id', $userIds)->where('issuer', OpenIdConnect::issuer() ?? '')->get(['user_id', 'key']) as $entitlement) {
            $keys[$entitlement->user_id][] = $entitlement->key;
        }

        return $keys;
    }

    /**
     * @param  list<int>  $communityIds
     */
    private function announceAll(array $communityIds): void
    {
        if ($communityIds === []) {
            return;
        }
        foreach (Community::query()->whereIn('id', $communityIds)->get() as $community) {
            self::announce($community->cuid);
        }
    }

    private static function announce(string $communityCuid): void
    {
        AccessGroupsChanged::dispatch($communityCuid);
        SpaceTreeChanged::dispatch($communityCuid);
    }
}
