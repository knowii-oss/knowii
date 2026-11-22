<?php

namespace App\Contracts\MemberTags;

use App\Models\MemberTag;
use App\Models\User;

interface RemovesMemberTagMembers
{
    public function remove(User $user, MemberTag $tag, string $memberCuid): void;
}
