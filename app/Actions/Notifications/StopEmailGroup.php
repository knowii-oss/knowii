<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\StopsEmailGroups;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Visus\Cuid2\Cuid2;

/**
 * "Stop emails like this" (#1478, ADR 0054). The stop writes the row `off` of the group of the account with `insert ... on
 * conflict do update`: a new row, or the row of the group set to `off` whatever state it held (slice 4c adds other
 * states), so two clicks (the header and the page) give one row. The undo deletes the row: in slice 4b the
 * state before a stop is always the default of the group. The signed address of the link is the only proof: the route
 * checks it, the action never reads a session. The log holds the cuid of the account and the group.
 */
final class StopEmailGroup implements StopsEmailGroups
{
    final public function stop(User $user, KnowiiNotificationEmailGroup $group): void
    {
        $before = NotificationEmailChoice::query()->where('user_id', $user->id)->where('group', $group->value)->value('state');
        $now = now();
        // One statement: a new row, or the row of the group set to `off` whatever state it held
        NotificationEmailChoice::query()->upsert([[
            'cuid' => (new Cuid2(24))->toString(),
            'user_id' => $user->id,
            'group' => $group->value,
            'state' => NotificationEmailChoice::STATE_OFF,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id', 'group'], ['state', 'updated_at']);
        if ($before !== NotificationEmailChoice::STATE_OFF) {
            Log::info('A notification email group was stopped with the link of an email', ['user' => $user->cuid, 'group' => $group->value]);
        }
    }

    final public function restore(User $user, KnowiiNotificationEmailGroup $group, string $restore): void
    {
        if ($restore !== NotificationEmailChoice::STATE_DEFAULT) {
            throw new InvalidArgumentException('A notification email group can be restored to its default only');
        }

        $deleted = NotificationEmailChoice::query()->where('user_id', $user->id)->where('group', $group->value)->delete();
        if ($deleted > 0) {
            Log::info('A notification email group was turned on again with the undo of the stop page', ['user' => $user->cuid, 'group' => $group->value]);
        }
    }
}
