<?php

namespace App\Contracts\Pages;

use App\Models\Page;
use App\Models\User;

interface DeletesPages
{
    /**
     * Page spaces (#1179): the owner, the admins and the moderators manage the pages.
     */
    public function delete(User $user, Page $page): void;
}
