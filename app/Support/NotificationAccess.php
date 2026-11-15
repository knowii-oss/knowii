<?php

namespace App\Support;

use App\Models\Community;
use App\Models\Notification;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The access rule of the notifications (#1474, ADR 0042, design 6.8): a member is told about an item only while they
 * can view it. The rule is checked when a row is written, when the list and the count are read, and when the access
 * groups, the members or the visibility of a space change. A row that fails the check is deleted.
 *
 * "Can view" is the community membership plus SpacePolicy::view, the one place that knows the rule (ADR 0025, 0041).
 */
final class NotificationAccess
{
    /**
     * Whether the member can view what happens in the space of the community. A null space (an item without a space)
     * needs the membership only.
     */
    public static function canView(User $user, Community $community, ?Space $space): bool
    {
        // A public space is readable by everyone, but only a member of the community is told about it
        if (! $user->belongsToCommunity($community)) {
            return false;
        }

        if ($space === null) {
            return true;
        }

        $space->setRelation('community', $community);

        return Gate::forUser($user)->allows('view', $space);
    }

    /**
     * Delete the rows of the query whose recipient cannot view their item any more, and tell the recipients. The
     * members, the communities and the spaces load once for all the rows (no query per row).
     *
     * @param  Builder<Notification>  $rows
     * @return int The number of rows deleted
     */
    public static function prune(Builder $rows): int
    {
        /** @var Collection<int, Notification> $candidates */
        $candidates = (clone $rows)->get(['notifications.id', 'notifications.cuid', 'notifications.user_id', 'notifications.community_id', 'notifications.space_id']);
        if ($candidates->isEmpty()) {
            return 0;
        }

        $spaces = Space::query()
            ->with(['accessGroups', 'spaceGroup.accessGroups'])
            ->whereIn('id', $candidates->pluck('space_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $communities = Community::query()
            ->whereIn('id', $candidates->pluck('community_id')->merge($spaces->pluck('community_id'))->unique()->values()->all())
            ->get()
            ->keyBy('id');
        $users = User::query()->with(['communities', 'accessGroups'])->whereIn('id', $candidates->pluck('user_id')->unique()->values()->all())->get()->keyBy('id');

        $doomed = $candidates->filter(function (Notification $row) use ($spaces, $communities, $users): bool {
            $user = $users->get($row->user_id);
            $community = $communities->get($row->community_id);
            if ($user === null || $community === null) {
                return false;
            }
            $space = $row->space_id === null ? null : $spaces->get($row->space_id);

            return ! self::canView($user, $community, $space);
        });
        if ($doomed->isEmpty()) {
            return 0;
        }

        Notification::query()->whereIn('id', $doomed->modelKeys())->delete();
        NotificationEvents::changed($doomed);

        return $doomed->count();
    }
}
