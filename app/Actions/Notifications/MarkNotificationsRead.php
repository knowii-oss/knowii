<?php

namespace App\Actions\Notifications;

use App\Contracts\Notifications\MarksNotificationsRead;
use App\Enums\KnowiiNotificationKind;
use App\Exceptions\BusinessException;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Notification;
use App\Models\Space;
use App\Models\User;
use App\Support\NotificationAccess;
use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * "Read" is the state of a line that was opened or marked done (#1474, ADR 0042, design 6.1). A read row also is seen.
 * It never moves a row up the list (`updated_at` stays), and a read row is never opened again by a new comment.
 *
 * A chat mention is also behind the chat cursor (design 5.2.5): reading the chat reads the mention rows up to the
 * message, and reading a mention row moves the cursor forward to its message, never backward.
 */
final class MarkNotificationsRead implements MarksNotificationsRead
{
    final public function read(User $user, Notification $notification): Notification
    {
        $this->ownedBy($user, $notification);

        Notification::withoutTimestamps(function () use ($notification): void {
            $notification->forceFill(['seen_at' => $notification->seen_at ?? now(), 'read_at' => $notification->read_at ?? now()])->save();
        });

        $this->moveChatCursor($user, $notification);
        NotificationEvents::changed([$notification]);

        return $notification;
    }

    final public function unread(User $user, Notification $notification): Notification
    {
        $this->ownedBy($user, $notification);

        if ($notification->read_at === null) {
            return $notification;
        }

        // One open row per member and thing: when a newer comment already opened a row, this one stays read
        $open = Notification::query()->where('user_id', $user->id)->where('dedupe_key', $notification->dedupe_key)->whereNull('read_at')->exists();
        if ($open) {
            throw new BusinessException('A newer notification about this already exists');
        }

        Notification::withoutTimestamps(function () use ($notification): void {
            $notification->forceFill(['read_at' => null])->save();
        });
        NotificationEvents::changed([$notification]);

        return $notification;
    }

    final public function readChat(User $user, Space $space, int $lastReadMessageId): void
    {
        $rows = Notification::query()
            ->where('user_id', $user->id)
            ->where('kind', KnowiiNotificationKind::Mention->value)
            ->whereNull('read_at')
            ->whereIn('chat_message_id', ChatMessage::query()->where('space_id', $space->id)->where('id', '<=', $lastReadMessageId)->select('id'));

        $changed = (clone $rows)->get(['notifications.id', 'notifications.cuid', 'notifications.user_id', 'notifications.community_id']);
        if ($changed->isEmpty()) {
            return;
        }

        // toBase: the update must not touch updated_at, which orders the list
        Notification::query()->whereIn('id', $changed->modelKeys())->toBase()->update([
            'seen_at' => DB::raw('COALESCE(seen_at, CURRENT_TIMESTAMP)'),
            'read_at' => now(),
        ]);
        NotificationEvents::changed($changed);
    }

    /**
     * A row of another member does not exist for this member (404), and a row that the member cannot view any more is
     * deleted (design 6.8.3)
     */
    private function ownedBy(User $user, Notification $notification): void
    {
        $missing = fn (): ModelNotFoundException => (new ModelNotFoundException)->setModel(Notification::class, [$notification->cuid]);

        if ($notification->user_id !== $user->id) {
            throw $missing();
        }
        if (NotificationAccess::prune(Notification::query()->whereKey($notification->id)) > 0) {
            throw $missing();
        }
    }

    private function moveChatCursor(User $user, Notification $notification): void
    {
        $message = $notification->chat_message_id === null ? null : $notification->chatMessage;
        if ($message === null || $message->space_id === null) {
            return;
        }

        // The cursor counts first-level messages: a reply in a thread moves it to the message of its thread
        $target = $message->parent_id ?? $message->id;
        $read = ChatRead::query()->firstOrNew(['user_id' => $user->id, 'space_id' => $message->space_id]);
        if ($read->exists && $read->last_read_message_id >= $target) {
            return;
        }

        $read->last_read_message_id = $target;
        $read->save();
    }
}
