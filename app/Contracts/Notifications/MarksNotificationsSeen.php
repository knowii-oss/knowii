<?php

namespace App\Contracts\Notifications;

use App\Models\Post;
use App\Models\User;

interface MarksNotificationsSeen
{
    /**
     * The lines were on screen: set `seen_at` on the rows of the member. Rows that do not exist, or that belong to
     * another member, are ignored.
     *
     * @param  array<mixed>  $input  notification_cuids: 1 to 100 cuids
     */
    public function seen(User $user, array $input): void;

    /**
     * The member loads a post page: the rows about the post or one of its comments are seen (not read).
     */
    public function seenForPost(User $user, Post $post): void;
}
