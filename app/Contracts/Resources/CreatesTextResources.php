<?php

namespace App\Contracts\Resources;

use App\Models\CommunityResource;
use App\Models\User;
use App\Models\Community;
use App\Models\CommunityResourceCollection;

interface CreatesTextResources
{
  /**
   * Create a new resource.
   *
   * @param  User  $user
   * @param  Community  $community
   * @param  CommunityResourceCollection  $communityResourceCollection
   * @param  array  $input
   * @return CommunityResource
   */
  public function create(User $user, Community $community, CommunityResourceCollection $communityResourceCollection, array $input): CommunityResource;
}
