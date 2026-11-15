<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface SetsAccessGroupSpaceGroups
{
    /**
     * Replace the space groups that an access group opens whole (#1422, ADR 0041). The input has `space_groups`: cuids.
     *
     * @param  array<mixed>  $input
     */
    public function set(User $user, AccessGroup $group, array $input): AccessGroup;
}
