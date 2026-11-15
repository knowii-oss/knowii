<?php

namespace App\Actions\Notifications;

use App\Constants;
use App\Contracts\Notifications\ListsNotifications;
use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationAccess;
use App\Support\NotificationBundles;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reads the notifications of a member (#1474, ADR 0042, design 5.1, 5.2). Every read checks the access first: a row
 * whose recipient cannot view the item any more is deleted, so it is neither listed nor counted (design 6.8.2, 6.8.5).
 *
 * The age of a row is its `updated_at`: a bundled row moves up when a new comment arrives. A row of "Needs you" folds
 * into "Older" after 14 days without news, and the count forgets it. A row of "Following" (level 2, #1475) is hidden
 * after 14 days: it has no "Older", and it never enters the count.
 */
final class ListNotifications implements ListsNotifications
{
    final public function list(User $user, Pagination $pagination, ?string $communityCuid = null, bool $older = false, ?bool $read = false, int $level = 1): array
    {
        $rows = $level === 2 ? Notification::query()->where('user_id', $user->id)->following() : $this->ofMember($user);
        if ($communityCuid !== null && $communityCuid !== '') {
            $rows->whereIn('community_id', Community::query()->where('cuid', $communityCuid)->select('id'));
        }

        // "Needs you" holds the open rows: a row that the member opened or marked done leaves it (design 5.1.4)
        if ($read === false) {
            $rows->whereNull('read_at');
        } elseif ($read === true) {
            $rows->whereNotNull('read_at');
        }

        $cutoff = now()->subDays(Constants::NOTIFICATIONS_FOLD_DAYS);
        if ($level === 2 && $older) {
            // "Following" is hidden after 14 days: it has no "Older" (the rows stay until the retention removes them)
            $rows->whereRaw('1 = 0');
        } elseif ($older) {
            $rows->where('updated_at', '<', $cutoff);
        } else {
            $rows->where('updated_at', '>=', $cutoff);
        }

        NotificationAccess::prune($rows);

        $page = $pagination->apply($rows->orderByDesc('updated_at')->orderByDesc('id'));
        NotificationBundles::attach($user, $page['items']);

        return $page;
    }

    final public function unseenCount(User $user): int
    {
        $rows = $this->ofMember($user)->whereNull('seen_at')->where('updated_at', '>=', now()->subDays(Constants::NOTIFICATIONS_FOLD_DAYS));

        NotificationAccess::prune($rows);

        return $rows->count();
    }

    final public function present(User $user, Notification $notification): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(Notification::class, [$notification->cuid]);
        }

        NotificationBundles::attach($user, new Collection([$notification]));

        return $notification;
    }

    /**
     * @return Builder<Notification>
     */
    private function ofMember(User $user): Builder
    {
        return Notification::query()->where('user_id', $user->id)->needingYou();
    }
}
