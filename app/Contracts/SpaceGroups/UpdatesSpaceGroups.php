<?php

namespace App\Contracts\SpaceGroups;

use App\Models\SpaceGroup;
use App\Models\User;

interface UpdatesSpaceGroups
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, SpaceGroup $spaceGroup, array $input): SpaceGroup;
}
