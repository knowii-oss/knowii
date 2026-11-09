<?php

namespace App\Contracts\SpaceGroups;

use App\Models\Community;
use App\Models\SpaceGroup;
use App\Models\User;

interface CreatesSpaceGroups
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): SpaceGroup;
}
