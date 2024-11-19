<?php

namespace App\Contracts\Communities;

use App\Models\Community;
use App\Models\User;

interface DeletesCommunities
{
    public function delete(User $user, Community $community): void;
}
