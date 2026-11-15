<?php

namespace App\Contracts\Notifications;

use App\Models\Notification;
use App\Models\Space;
use App\Models\User;

interface MarksNotificationsRead
{
    /**
     * The member opened the line or marked it done: set `read_at` (and `seen_at`). A chat mention moves the chat
     * cursor of the member forward to its message, never backward.
     */
    public function read(User $user, Notification $notification): Notification;

    /**
     * Undo "read": the row is open again, and seen. The chat cursor stays where it is.
     */
    public function unread(User $user, Notification $notification): Notification;

    /**
     * The member read a chat space up to a message: the mention rows of the space up to it are seen and read.
     */
    public function readChat(User $user, Space $space, int $lastReadMessageId): void;
}
