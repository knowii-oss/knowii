<?php

namespace App\Actions\Spaces;

use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The access groups of a space (#1241, ADR 0025): a "groups" space needs at least one group that opens it: a group of
 * its own community given to the space, or a group given to its space group (#1422, ADR 0041). Another visibility has
 * none of its own.
 */
final class SpaceAccessGroups
{
    /**
     * @param  list<string>|null  $groupCuids  null: keep the current groups
     */
    public static function sync(Space $space, ?array $groupCuids): void
    {
        if ($space->visibility !== KnowiiSpaceVisibility::Groups) {
            $space->accessGroups()->detach();

            return;
        }

        if ($groupCuids === null) {
            if (! self::hasAnyOpeningGroup($space)) {
                throw ValidationException::withMessages(['access_groups' => 'Choose at least one access group']);
            }

            return;
        }

        $groupCuids = array_values(array_unique($groupCuids));
        $ids = $space->community->accessGroups()->whereIn('cuid', $groupCuids)->pluck('id')->all();

        if (count($ids) !== count($groupCuids)) {
            // Never trust a cuid from the client: the groups must belong to the community of the space
            throw ValidationException::withMessages(['access_groups' => 'An access group does not exist in this community']);
        }
        if ($ids === [] && ! self::spaceGroupHasAccessGroups($space)) {
            throw ValidationException::withMessages(['access_groups' => 'Choose at least one access group']);
        }

        $space->accessGroups()->sync($ids);
    }

    private static function hasAnyOpeningGroup(Space $space): bool
    {
        return $space->accessGroups()->exists() || self::spaceGroupHasAccessGroups($space);
    }

    /**
     * Reads the space group again: the space may just have moved to another one.
     */
    private static function spaceGroupHasAccessGroups(Space $space): bool
    {
        return DB::table('access_group_space_group')->where('space_group_id', $space->space_group_id)->exists();
    }
}
