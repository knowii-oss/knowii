<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface DeletesAccessGroups
{
    public function delete(User $user, AccessGroup $group): void;
}
