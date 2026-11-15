<?php

namespace App\Events\Notifications;

use App\Constants;
use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The notifications of a member changed (#1474, ADR 0042): a row was written, bundled, seen, read or deleted. Identifiers
 * only, like ConversationChanged: the client loads the count and the line through the API, which checks the access.
 * Sent to the private channel of the member (`user.{cuid}`) on the queue `broadcasts`, so the other tabs follow too.
 *
 * `notificationCuid` is null when more than one row changed (a list marked as seen, rows deleted).
 */
class NotificationsChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string NAME = 'notifications.changed';

    public function __construct(
        public readonly string $userCuid,
        public readonly string $communityCuid,
        public readonly ?string $notificationCuid = null,
    ) {}

    public static function forNotification(Notification $notification): self
    {
        $user = $notification->user;
        $community = $notification->community;

        return new self($user->cuid, $community->cuid, $notification->cuid);
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return ['notification_cuid' => $this->notificationCuid, 'community_cuid' => $this->communityCuid];
    }

    public function broadcastAs(): string
    {
        return self::NAME;
    }

    /**
     * The queue of the live updates: the worker reads it first (ADR 0042)
     */
    public function broadcastQueue(): string
    {
        return Constants::QUEUE_BROADCASTS;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(str_replace(Constants::WS_CHANNEL_USER_PARAM_USER_CUID, $this->userCuid, Constants::WS_CHANNEL_USER))];
    }
}
