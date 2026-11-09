<?php

namespace App\Contracts\Chat;

use App\Models\ChatMessage;
use App\Models\User;

interface UpdatesChatMessages
{
    /**
     * The author changes the text of their message (#1185).
     *
     * @param  array<mixed>  $input
     */
    public function update(User $user, ChatMessage $message, array $input): ChatMessage;
}
