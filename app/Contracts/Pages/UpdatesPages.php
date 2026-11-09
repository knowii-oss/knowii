<?php

namespace App\Contracts\Pages;

use App\Models\Page;
use App\Models\User;

interface UpdatesPages
{
    /**
     * Page spaces (#1179): the owner, the admins and the moderators manage the pages.
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, Page $page, array $input): Page;
}
