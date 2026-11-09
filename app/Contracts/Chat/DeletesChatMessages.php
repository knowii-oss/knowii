<?php

namespace App\Contracts\Chat;

use App\Models\ChatMessage;
use App\Models\User;

interface DeletesChatMessages
{
    /**
     * The author or a moderator deletes a message; it stays as a marker (#1185).
     */
    public function delete(User $user, ChatMessage $message): void;
}
