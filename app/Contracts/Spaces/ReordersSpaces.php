<?php

namespace App\Contracts\Spaces;

use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ReordersSpaces
{
    /**
     * @param  array<mixed>  $input
     * @return Collection<int, Space>
     */
    public function reorder(User $user, SpaceGroup $spaceGroup, array $input): Collection;
}
