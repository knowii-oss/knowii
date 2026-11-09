<?php

namespace App\Actions\Spaces;

use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use Illuminate\Validation\ValidationException;

/**
 * The access groups of a space (#1241, ADR 0025): a "groups" space needs at least one group of its community; another
 * visibility has none.
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
            if (! $space->accessGroups()->exists()) {
                throw ValidationException::withMessages(['access_groups' => 'Choose at least one access group']);
            }

            return;
        }

        $groupCuids = array_values(array_unique($groupCuids));
        $ids = $space->community->accessGroups()->whereIn('cuid', $groupCuids)->pluck('id')->all();

        if ($ids === []) {
            throw ValidationException::withMessages(['access_groups' => 'Choose at least one access group']);
        }
        if (count($ids) !== count($groupCuids)) {
            // Never trust a cuid from the client: the groups must belong to the community of the space
            throw ValidationException::withMessages(['access_groups' => 'An access group does not exist in this community']);
        }

        $space->accessGroups()->sync($ids);
    }
}
