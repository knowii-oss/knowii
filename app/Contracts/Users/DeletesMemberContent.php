<?php

namespace App\Contracts\Users;

use App\Models\User;

/**
 * Deletes what a member wrote, in every community, when they delete their account and choose to (#1344, ADR 0043).
 */
interface DeletesMemberContent
{
    /**
     * The posts, the comments, the chat messages and the direct messages of the member. A post that still has comments
     * of other members, and a comment or a chat message that still has replies, stay as empty markers. Resources,
     * pages and lessons stay.
     */
    public function delete(User $user): void;
}
