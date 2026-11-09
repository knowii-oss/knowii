<?php

namespace App\Contracts\Pages;

use App\Models\Space;
use App\Models\User;

interface ReordersPages
{
    /**
     * Page spaces (#1179): the owner, the admins and the moderators manage the pages.
     *
     * @param  array<mixed>  $input
     */
    public function reorder(User $user, Space $space, array $input): void;
}
