<?php

namespace App\Support;

use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\SpaceVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
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

        $groups = $community->spaceGroups()->with(['accessGroups', 'spaces.accessGroups'])->get();

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
            self::markNewContent($groups, $community, $user);
        }

        // Members do not need empty groups. Managers see them, to fill them.
        return $canManage ? $groups : $groups->filter(fn (SpaceGroup $group) => $group->spaces->isNotEmpty())->values();
    }

    /**
     * The dot of a space (#1476, ADR 0048, design 5.2.3): `has_new` is true when content was added after the later of the
     * last visit of the member and the day they joined, the level that applies is not "Muted", and the space is not a chat
     * space (its cursor is `chat_reads`) nor a link space. `notification_level` is the level that applies (a link space
     * has none). Three queries for the whole tree, whatever the number of spaces. Known limits (ADR 0048): content that was
     * deleted, or that a blocked author wrote, can leave a dot until the next visit.
     *
     * @param  Collection<int, SpaceGroup>  $groups
     */
    private static function markNewContent(Collection $groups, Community $community, User $user): void
    {
        $spaces = $groups->flatMap(fn (SpaceGroup $group) => $group->spaces);
        if ($spaces->isEmpty()) {
            return;
        }

        $ids = $spaces->map(fn (Space $space) => $space->id)->all();
        $visits = SpaceVisit::query()->where('user_id', $user->id)->whereIn('space_id', $ids)->get()->keyBy(fn (SpaceVisit $visit) => $visit->space_id);
        // The day the member joined: content from before it gives no dot (the owner is not in the pivot: the community date)
        $joinedAt = DB::table('community_members')->where('community_id', $community->id)->where('user_id', $user->id)->value('created_at');
        $joined = is_string($joinedAt) ? Carbon::parse($joinedAt) : $community->created_at;
        $levels = SpaceNotificationLevels::levelsForSpaces($user, $community, $spaces);

        foreach ($spaces as $space) {
            if ($space->type === KnowiiSpaceType::Link) {
                $space->setAttribute('has_new', false);

                continue;
            }

            $level = $levels[$space->id];
            $since = $visits->get($space->id)?->visited_at;
            if ($since === null || $since->lt($joined)) {
                $since = $joined;
            }
            $last = $space->last_content_at;

            $space->setAttribute('notification_level', $level->value);
            $space->setAttribute('has_new', $space->type !== KnowiiSpaceType::Chat && $level !== KnowiiSpaceNotificationLevel::None && $last !== null && $last->gt($since));
        }
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
