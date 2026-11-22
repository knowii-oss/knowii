<?php

namespace App\Contracts\MemberTags;

use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;

interface CreatesMemberTags
{
    /**
     * @param  array<mixed>  $input
     */
    public function create(User $user, Community $community, array $input): MemberTag;
}
