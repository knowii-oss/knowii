<?php

namespace App\Contracts\Spaces;

use App\Models\Space;
use App\Models\User;

interface UpdatesSpaces
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, Space $space, array $input): Space;
}
