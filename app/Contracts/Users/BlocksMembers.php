<?php

namespace App\Contracts\Users;

use App\Models\User;

interface BlocksMembers
{
    public function block(User $user, User $blocked): void;

    public function unblock(User $user, User $blocked): void;
}
