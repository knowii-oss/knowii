<?php

namespace App\Contracts\Conversations;

use App\Models\Conversation;
use App\Models\User;

interface MarksConversationsAsRead
{
    /**
     * Remember the last message (`message_cuid`) that the participant read
     *
     * @param  array<mixed>  $input
     */
    public function markAsRead(User $user, Conversation $conversation, array $input): void;
}
