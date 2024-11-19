<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface CreatesCommunities
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, array $input): Community;
}
