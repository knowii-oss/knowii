<?php

namespace App\Contracts\AccessGroups;

use App\Models\AccessGroup;
use App\Models\User;

interface AddsAccessGroupMembers
{
    /**
     * The member who is now in the group, for the selectors `member` and `username`. Null for the selector `email`,
     * whether or not a member of the community has the address: the caller answers without a member. The selector
     * `email` is for the owner and the admins only.
     *
     * @param  array<mixed>  $input
     */
    public function add(User $user, AccessGroup $group, array $input): ?User;
}
