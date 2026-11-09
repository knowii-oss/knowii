<?php

namespace App\Contracts\Chat;

use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;

interface CreatesChatMessages
{
    /**
     * Write a message in a chat space, or a reply in a thread (#1185).
     *
     * @param  array<mixed>  $input
     */
    public function create(User $user, Space $space, array $input): ChatMessage;
}
