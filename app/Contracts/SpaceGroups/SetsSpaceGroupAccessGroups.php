<?php

namespace App\Contracts\SpaceGroups;

use App\Models\SpaceGroup;
use App\Models\User;

interface SetsSpaceGroupAccessGroups
{
    /**
     * Replace the access groups that open a whole space group (#1422, ADR 0041). The input has `access_groups`: cuids.
     *
     * @param  array<mixed>  $input
     */
    public function set(User $user, SpaceGroup $spaceGroup, array $input): SpaceGroup;
}
