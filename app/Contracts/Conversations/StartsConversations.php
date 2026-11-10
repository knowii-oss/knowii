<?php

namespace App\Contracts\Conversations;

use App\Models\Community;
use App\Models\Conversation;
use App\Models\User;

interface StartsConversations
{
    /**
     * Write to 1 to 9 other members (`participant_cuids`) with a first message (`body`). The same two members always
     * share one one-to-one conversation.
     *
     * @param  array<mixed>  $input
     */
    public function start(User $user, Community $community, array $input): Conversation;
}
