<?php

namespace App\Contracts\Resources;

use App\Models\CommunityResource;
use App\Models\User;

interface DeletesCommunityResources
{
    /**
     * Delete a resource of a resources space with its comments, reactions and captured content (#324).
     */
    public function delete(User $user, CommunityResource $resource): void;
}
