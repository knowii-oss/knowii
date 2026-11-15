<?php

namespace App\Support;

use App\Events\Notifications\NotificationsChanged;
use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Sends NotificationsChanged (#1474, ADR 0042, design 6.12) for rows that changed or went away: one event per member and
 * community. The event names the notification when the member has one row in that community in the batch, else it has
 * none. The rows need their `cuid`, `user_id` and `community_id`.
 */
final class NotificationEvents
{
    /**
     * @param  iterable<Notification>  $rows
     */
    public static function changed(iterable $rows): void
    {
        /** @var Collection<string, Collection<int, Notification>> $groups */
        $groups = Collection::make(is_array($rows) ? $rows : iterator_to_array($rows, false))->groupBy(static fn (Notification $row): string => $row->user_id.':'.$row->community_id);
        if ($groups->isEmpty()) {
            return;
        }

        $all = $groups->flatten(1);
        /** @var Collection<int, string> $userCuids */
        $userCuids = User::query()->whereIn('id', $all->pluck('user_id')->unique()->values()->all())->pluck('cuid', 'id');
        /** @var Collection<int, string> $communityCuids */
        $communityCuids = Community::query()->whereIn('id', $all->pluck('community_id')->unique()->values()->all())->pluck('cuid', 'id');

        foreach ($groups as $group) {
            $first = $group->first();
            if ($first === null) {
                continue;
            }
            $userCuid = $userCuids->get($first->user_id);
            $communityCuid = $communityCuids->get($first->community_id);
            if ($userCuid === null || $communityCuid === null) {
                continue;
            }

            NotificationsChanged::dispatch($userCuid, $communityCuid, $group->count() === 1 ? $first->cuid : null);
        }
    }
}
