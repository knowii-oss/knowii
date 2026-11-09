<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface UpdatesAccessGroups
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, AccessGroup $group, array $input): AccessGroup;
}
