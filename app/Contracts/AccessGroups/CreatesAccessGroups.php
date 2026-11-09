<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\User;

interface CreatesAccessGroups
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): AccessGroup;
}
