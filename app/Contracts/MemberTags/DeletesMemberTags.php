<?php

namespace App\Contracts\MemberTags;

use App\Models\MemberTag;
use App\Models\User;

interface DeletesMemberTags
{
    public function delete(User $user, MemberTag $tag): void;
}
