<?php

namespace App\Contracts\Conversations;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;

interface SendsDirectMessages
{
    /**
     * Write a message (`body`) in a conversation
     *
     * @param  array<mixed>  $input
     */
    public function send(User $user, Conversation $conversation, array $input): ChatMessage;
}
