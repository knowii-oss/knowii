<?php

namespace App\Contracts\Users;

use App\Models\User;

interface UpdatesNotificationSettings
{
    /**
     * The account choices about notifications (#1476): `popups` (boolean), the switch "Show pop-ups"
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, array $input): User;
}
