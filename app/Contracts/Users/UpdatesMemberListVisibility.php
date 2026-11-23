<?php

namespace App\Contracts\Users;

use App\Models\User;

interface UpdatesMemberListVisibility
{
    /**
     * Show or hide the user in the member list of their communities (#1466): `show_in_member_list` (boolean).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, array $input): User;
}
