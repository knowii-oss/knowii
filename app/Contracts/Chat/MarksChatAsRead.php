<?php

namespace App\Contracts\Chat;

use App\Models\Space;
use App\Models\User;

interface MarksChatAsRead
{
    /**
     * Remember the last message that the member read in a chat space (#1185).
     *
     * @param  array<mixed>  $input
     */
    public function markAsRead(User $user, Space $space, array $input): void;
}
