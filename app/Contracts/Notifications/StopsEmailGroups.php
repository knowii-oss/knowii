<?php

namespace App\Contracts\Notifications;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Models\User;

/**
 * "Stop emails like this" (#1478, ADR 0054, design 5.6 point 8): the stop link of a notification email and its one-click
 * header stop one notification email group of the account, in every community.
 */
interface StopsEmailGroups
{
    /**
     * The group sends no email to the account any more. Stopping a group that is off changes nothing
     */
    public function stop(User $user, KnowiiNotificationEmailGroup $group): void;

    /**
     * "Undo": the group gets back the state that it had before the stop. In slice 4b the only state before a stop is the
     * default of the group (no row): `restore` is `default`, and the row goes
     */
    public function restore(User $user, KnowiiNotificationEmailGroup $group, string $restore): void;
}
