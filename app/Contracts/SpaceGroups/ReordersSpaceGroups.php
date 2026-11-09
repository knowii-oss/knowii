<?php

namespace App\Contracts\SpaceGroups;

use App\Models\Community;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ReordersSpaceGroups
{
    /**
     * @param  array<mixed>  $input
     * @return Collection<int, SpaceGroup>
     */
    public function reorder(User $user, Community $community, array $input): Collection;
}
