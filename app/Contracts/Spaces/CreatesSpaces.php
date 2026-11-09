<?php

namespace App\Contracts\Spaces;

use App\Models\Community;
use App\Models\Space;
use App\Models\User;

interface CreatesSpaces
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): Space;
}
