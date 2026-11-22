<?php

namespace App\Contracts\MemberTags;

use App\Models\MemberTag;
use App\Models\User;

interface AddsMemberTagMembers
{
    /**
     * @param  array<mixed>  $input
     */
    public function add(User $user, MemberTag $tag, array $input): User;
}
