<?php

namespace App\Contracts\Resources;

use App\Models\CommunityResource;
use App\Models\Space;
use App\Models\User;

interface CreatesCommunityResources
{
    /**
     * Add a link to a resources space (ADR 0015). The capture runs later, in a queued job (ADR 0018).
     *
     * @param  array<mixed>  $input
     */
    public function create(User $user, Space $space, array $input): CommunityResource;
}
