<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface UpdatesCommunities
{
    /**
     * Change the name, the description or the visibility of a community (PATCH: the fields not sent keep their value).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Community $community, array $input): Community;
}
