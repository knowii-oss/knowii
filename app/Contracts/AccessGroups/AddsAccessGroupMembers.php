<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface AddsAccessGroupMembers
{
    /**
     * @param  array<mixed>  $input
     */
    public function add(User $user, AccessGroup $group, array $input): User;
}
