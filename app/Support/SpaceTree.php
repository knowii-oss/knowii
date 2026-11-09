<?php

namespace App\Support;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The navigation of a community: its space groups and spaces, in order, limited to what the user may see.
 */
final class SpaceTree
{
    /**
     * @return Collection<int, SpaceGroup>
     */
    public static function for(Community $community, ?User $user): Collection
    {
        $canManage = $user !== null && Gate::forUser($user)->check('manageSpaces', $community);

        $groups = $community->spaceGroups()->with(['spaces.accessGroups'])->get();

        foreach ($groups as $group) {
            $visible = $group->spaces
                ->filter(function (Space $space) use ($community, $group, $user): bool {
                    // Avoid a query per space in the policy
                    $space->setRelation('community', $community);
                    $space->setRelation('spaceGroup', $group);

                    return Gate::forUser($user)->check('view', $space);
                })
                ->values();

            $group->setRelation('spaces', $visible);
        }

        if ($user !== null && $user->belongsToCommunity($community)) {
            self::countUnreadMessages($groups, $user);
        }

        // Members do not need empty groups. Managers see them, to fill them.
        return $canManage ? $groups : $groups->filter(fn (SpaceGroup $group) => $group->spaces->isNotEmpty())->values();
    }

    /**
     * The first-level messages of each chat space after the last one the member read, without their own (#1185).
     * One query for the whole tree.
     *
     * @param  Collection<int, SpaceGroup>  $groups
     */
    private static function countUnreadMessages(Collection $groups, User $user): void
    {
        $chatSpaces = $groups->flatMap(fn (SpaceGroup $group) => $group->spaces)->filter(fn (Space $space) => $space->type === KnowiiSpaceType::Chat);
        if ($chatSpaces->isEmpty()) {
            return;
        }

        $counts = DB::table('chat_messages as m')
            ->leftJoin('chat_reads as r', fn (JoinClause $join) => $join->on('r.space_id', '=', 'm.space_id')->where('r.user_id', '=', $user->id))
            ->whereIn('m.space_id', $chatSpaces->map(fn (Space $space) => $space->id)->all())
            ->whereNull('m.parent_id')
            ->whereNull('m.deleted_at')
            ->where(fn (Builder $query) => $query->whereNull('m.author_id')->orWhere('m.author_id', '<>', $user->id))
            ->whereRaw('m.id > COALESCE(r.last_read_message_id, 0)')
            ->groupBy('m.space_id')
            ->selectRaw('m.space_id, count(*) as unread')
            ->pluck('unread', 'space_id');

        foreach ($chatSpaces as $space) {
            $count = $counts[$space->id] ?? 0;
            $space->setAttribute('unread_count', is_numeric($count) ? (int) $count : 0);
        }
    }
}
