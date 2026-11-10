<?php

namespace App\Contracts\Users;

use App\Models\User;

interface UpdatesOnlineStatus
{
    /**
     * Show or hide when the user is online (#1187): `show_online_status` (boolean).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, array $input): User;
}
