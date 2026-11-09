<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface RemovesAccessGroupMembers
{
    public function remove(User $user, AccessGroup $group, string $memberCuid): void;
}
