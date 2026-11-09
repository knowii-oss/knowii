<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface UpdatesModeratorPermissions
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, Community $community, array $input): Community;
}
