<?php

namespace App\Actions\AccessGroups;

use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityRoles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The links between access groups and space groups (#1422, ADR 0041): both sides of a link belong to the same
 * community, and the pair is unique. The two "set" actions share this code.
 */
final class AccessGroupSpaceGroups
{
    /**
     * The cuids of the input, without doubles. An empty list is valid: it removes every link.
     *
     * @param  array<mixed>  $input
     * @return list<string>
     */
    public static function cuids(array $input, string $key, int $max): array
    {
        /** @var array<string, list<string>> $validated */
        $validated = Validator::make($input, [
            $key => ['present', 'array', 'max:'.$max],
            $key.'.*' => ['string'],
        ])->validate();

        return array_values(array_unique($validated[$key]));
    }

    /**
     * The ids of the space groups of the community with these cuids. A cuid that is not one of the community gives a
     * validation issue: never trust a cuid from the client.
     *
     * @param  list<string>  $cuids
     * @return list<int>
     */
    public static function spaceGroupIds(Community $community, array $cuids): array
    {
        /** @var list<int> $ids */
        $ids = $community->spaceGroups()->whereIn('cuid', $cuids)->pluck('id')->all();
        if (count($ids) !== count($cuids)) {
            throw ValidationException::withMessages(['space_groups' => 'A space group does not exist in this community']);
        }

        return $ids;
    }

    /**
     * @param  list<string>  $cuids
     * @return list<int>
     */
    public static function accessGroupIds(Community $community, array $cuids): array
    {
        /** @var list<int> $ids */
        $ids = $community->accessGroups()->whereIn('cuid', $cuids)->pluck('id')->all();
        if (count($ids) !== count($cuids)) {
            throw ValidationException::withMessages(['access_groups' => 'An access group does not exist in this community']);
        }

        return $ids;
    }

    /**
     * A manager changes the links of a space group only when they can read every "groups" space of it. Without this
     * check, a moderator with the "manage spaces" right gives an access group of their own a closed space group and
     * reads it (#1325, ADR 0025: a group space stays out of reach of the managers who cannot read it). The owner and
     * the admins read every space.
     *
     * @param  list<int>  $spaceGroupIds  the space groups whose links change
     */
    public static function authorizeChange(User $user, Community $community, array $spaceGroupIds): void
    {
        if ($spaceGroupIds === [] || CommunityRoles::isOwnerOrAdmin($user, $community)) {
            return;
        }

        $spaces = Space::query()
            ->whereIn('space_group_id', $spaceGroupIds)
            ->where('visibility', KnowiiSpaceVisibility::Groups)
            ->with(['accessGroups', 'spaceGroup.accessGroups'])
            ->get();

        foreach ($spaces as $space) {
            $space->setRelation('community', $community);
            Gate::forUser($user)->authorize('view', $space);
        }
    }

    /**
     * Who reads what changed: the settings pages and the navigation reload.
     */
    public static function broadcast(AccessGroup|SpaceGroup $group): void
    {
        $communityCuid = $group->community->cuid;
        AccessGroupsChanged::dispatch($communityCuid);
        SpaceTreeChanged::dispatch($communityCuid);
    }
}
