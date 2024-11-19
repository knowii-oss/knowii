<?php

namespace App\Contracts\CommunityResourceCollections;

use App\Models\CommunityResourceCollection;
use App\Models\User;

interface DeletesCommunityResourceCollections
{
    public function delete(User $user, CommunityResourceCollection $resourceCollection): void;
}
