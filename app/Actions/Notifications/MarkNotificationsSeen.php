<?php

namespace App\Actions\Notifications;

use App\Constants;
use App\Contracts\Notifications\MarksNotificationsSeen;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

/**
 * "Seen" is the state of a line that was on screen (#1474, ADR 0042, design 6.1): the bell number counts only the rows
 * that are not seen. It never sets `read_at`, and it never moves a row up the list (`updated_at` stays).
 */
final class MarkNotificationsSeen implements MarksNotificationsSeen
{
    final public function seen(User $user, array $input): void
    {
        /** @var array{notification_cuids: list<string>} $validated */
        $validated = Validator::make($input, [
            'notification_cuids' => ['required', 'array', 'min:1', 'max:'.Constants::NOTIFICATIONS_SEEN_MAX],
            'notification_cuids.*' => ['required', 'string', 'max:64'],
        ])->validate();

        $this->markSeen(Notification::query()->where('user_id', $user->id)->whereIn('cuid', $validated['notification_cuids']));
    }

    final public function seenForPost(User $user, Post $post): void
    {
        $this->markSeen(Notification::query()->where('user_id', $user->id)->where(function (Builder $query) use ($post): void {
            $query->where('post_id', $post->id)
                ->orWhereIn('comment_id', Comment::query()->where('post_id', $post->id)->select('id'));
        }));
    }

    /**
     * @param  Builder<Notification>  $rows
     */
    private function markSeen(Builder $rows): void
    {
        $rows = $rows->whereNull('seen_at');
        $changed = (clone $rows)->get(['notifications.id', 'notifications.cuid', 'notifications.user_id', 'notifications.community_id']);
        if ($changed->isEmpty()) {
            return;
        }

        // toBase: the update must not touch updated_at, which orders the list
        Notification::query()->whereIn('id', $changed->modelKeys())->toBase()->update(['seen_at' => now()]);
        NotificationEvents::changed($changed);
    }
}
