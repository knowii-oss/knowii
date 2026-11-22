<?php

namespace App\Contracts\MemberTags;

use App\Models\MemberTag;
use App\Models\User;

interface UpdatesMemberTags
{
    /**
     * @param  array<mixed>  $input
     */
    public function update(User $user, MemberTag $tag, array $input): MemberTag;
}
