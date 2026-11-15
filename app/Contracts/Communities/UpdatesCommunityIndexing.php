<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface UpdatesCommunityIndexing
{
    /**
     * Let search engines index the public spaces of the community, or not (#1487).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Community $community, array $input): Community;
}
