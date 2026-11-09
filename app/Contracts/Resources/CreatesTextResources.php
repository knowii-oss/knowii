<?php

namespace App\Contracts\Resources;

use App\Models\CommunityResource;
use App\Models\Space;
use App\Models\User;

interface CreatesTextResources
{
    /**
     * Add a text article to a resources space (ADR 0015).
     *
     * @param  array<mixed>  $input
     */
    public function create(User $user, Space $space, array $input): CommunityResource;
}
