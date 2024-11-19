<?php

namespace App\Contracts\Resources;

use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\CommunityResourceCollection;
use App\Models\User;

interface CreatesTextResources
{
    /**
     * Create a new resource.
     *
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, CommunityResourceCollection $communityResourceCollection, array $input): CommunityResource;
}
