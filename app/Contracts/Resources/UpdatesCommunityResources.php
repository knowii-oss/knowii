<?php

namespace App\Contracts\Resources;

use App\Models\CommunityResource;
use App\Models\User;

interface UpdatesCommunityResources
{
    /**
     * Change what the community decides about a resource: its type and its level (#1246).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, CommunityResource $resource, array $input): CommunityResource;
}
