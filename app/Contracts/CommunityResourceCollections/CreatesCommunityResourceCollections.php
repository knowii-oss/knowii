<?php

namespace App\Contracts\CommunityResourceCollections;

use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\User;

interface CreatesCommunityResourceCollections
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): CommunityResourceCollection;
}
