<?php

namespace App\Contracts\SpaceGroups;

use App\Models\SpaceGroup;
use App\Models\User;

interface DeletesSpaceGroups
{
    public function delete(User $user, SpaceGroup $spaceGroup): void;
}
