<?php

namespace App\Contracts\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Collection;

interface ListsNotifications
{
    /**
     * The "Needs you" rows of the member, newest news first. The rows that the member cannot view any more are
     * deleted first, so they are not listed and not counted.
     *
     * @param  ?string  $communityCuid  keep the rows of this community
     * @param  bool  $older  false: the rows of the last 14 days, true: the older ones ("Older")
     * @param  ?bool  $read  false: the open rows (not read, the "Needs you" list), true: the rows that the member read, null: both
     * @return array{items: Collection<int, Notification>, metadata: array{pagination: array<string, int|null>}}
     */
    public function list(User $user, Pagination $pagination, ?string $communityCuid = null, bool $older = false, ?bool $read = false): array;

    /**
     * The rows that the member did not see, 14 days old at most, after the access check
     */
    public function unseenCount(User $user): int;

    /**
     * One row of the member, ready to show (the content and the bundled authors loaded)
     */
    public function present(User $user, Notification $notification): Notification;
}
