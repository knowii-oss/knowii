<?php

namespace App\Actions\Notifications;

use App\Enums\KnowiiNotificationKind;
use App\Events\Notifications\NotificationsChanged;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use Visus\Cuid2\Cuid2;

/**
 * Writes the rows of level 2, "Following", for one chunk of recipients with a fixed number of queries (#1475, ADR 0042,
 * design 6.14): the open rows are bundled with one update, the new rows go in with one insert, and a retry is harmless
 * (a member who has the row of the item already is skipped, and the unique index of the open rows keeps one open row per
 * member and thing). One event per member and community, queued on the queue `broadcasts`.
 */
final class LevelTwoWriter
{
    /**
     * The column of the item of a kind, and the thing that its rows bundle on
     *
     * @return array{column: 'post_id'|'comment_id'|'chat_message_id'|'page_id'|'event_id', dedupe_key: string}
     */
    final public static function subject(KnowiiNotificationKind $kind, Post|Comment|ChatMessage|Page|Event $item, Space $space): array
    {
        return match ($kind) {
            KnowiiNotificationKind::ThreadComment => ['column' => 'comment_id', 'dedupe_key' => 'thread_comment:post:'.($item instanceof Comment ? $item->post_id : $item->id)],
            KnowiiNotificationKind::ThreadReply => ['column' => 'chat_message_id', 'dedupe_key' => 'thread_reply:chat:'.($item instanceof ChatMessage ? $item->parent_id : $item->id)],
            KnowiiNotificationKind::SpacePost => ['column' => 'post_id', 'dedupe_key' => 'space_post:space:'.$space->id],
            KnowiiNotificationKind::SpaceChat => ['column' => 'chat_message_id', 'dedupe_key' => 'space_chat:space:'.$space->id],
            KnowiiNotificationKind::LessonNew => ['column' => 'page_id', 'dedupe_key' => 'lesson_new:space:'.$space->id],
            // One row per member and event: the line shows one time and one title, so it never bundles (#1513)
            KnowiiNotificationKind::EventNew => ['column' => 'event_id', 'dedupe_key' => 'event_new:event:'.$item->id],
            // The level 1 kinds write their own rows (Notify)
            default => throw new LogicException('The kind '.$kind->value.' is not a level 2 kind'),
        };
    }

    /**
     * @param  Collection<int, User>  $recipients  the members to tell, already checked (access, blocks, levels)
     */
    final public function write(KnowiiNotificationKind $kind, Post|Comment|ChatMessage|Page|Event $item, Community $community, Space $space, int $actorId, Collection $recipients): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        ['column' => $column, 'dedupe_key' => $dedupeKey] = self::subject($kind, $item, $space);

        // A retry of the job never writes a second row for the same item
        $done = Notification::query()->whereIn('user_id', $recipients->modelKeys())->where('kind', $kind->value)->where($column, $item->id)->pluck('user_id')->all();
        $recipients = $recipients->reject(static fn (User $user): bool => in_array($user->id, $done, true))->keyBy('id');
        if ($recipients->isEmpty()) {
            return;
        }

        // The members who have an open row for the same thing: the row says "New" again, with the new author
        $open = $this->openRows($dedupeKey, $recipients->modelKeys());
        $this->bundle($open, $actorId, $community, $recipients);

        $fresh = $recipients->reject(static fn (User $user): bool => $open->has($user->id));
        if ($fresh->isEmpty()) {
            return;
        }

        $now = now();
        Notification::query()->insertOrIgnore($fresh->map(static fn (User $user): array => [
            'cuid' => (new Cuid2(24))->toString(),
            'user_id' => $user->id,
            'community_id' => $community->id,
            'space_id' => $space->id,
            'kind' => $kind->value,
            'actor_id' => $actorId,
            'post_id' => $column === 'post_id' ? $item->id : null,
            'comment_id' => $column === 'comment_id' ? $item->id : null,
            'chat_message_id' => $column === 'chat_message_id' ? $item->id : null,
            'page_id' => $column === 'page_id' ? $item->id : null,
            'event_id' => $column === 'event_id' ? $item->id : null,
            'dedupe_key' => $dedupeKey,
            'seen_at' => null,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());

        $written = Notification::query()->whereIn('user_id', $fresh->modelKeys())->where('kind', $kind->value)->where($column, $item->id)->get(['cuid', 'user_id'])->keyBy('user_id');
        foreach ($written as $row) {
            $user = $fresh->get($row->user_id);
            if ($user !== null) {
                NotificationsChanged::dispatch($user->cuid, $community->cuid, $row->cuid);
            }
        }

        // Another job opened a row for the same thing between our read and our insert: join it
        $lost = $fresh->reject(static fn (User $user): bool => $written->has($user->id));
        if ($lost->isNotEmpty()) {
            $this->bundle($this->openRows($dedupeKey, $lost->modelKeys()), $actorId, $community, $lost);
        }
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return Collection<int, Notification>
     */
    private function openRows(string $dedupeKey, array $userIds): Collection
    {
        return Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('dedupe_key', $dedupeKey)
            ->whereNull('read_at')
            ->get(['id', 'cuid', 'user_id'])
            ->keyBy('user_id');
    }

    /**
     * A new item on a thing with an open row: the last author changes, the line is "New" again and moves up the list.
     * The subject stays the first item that the member did not read (design 6.6).
     *
     * @param  Collection<int, Notification>  $open  by user id
     * @param  Collection<int, User>  $recipients  by user id
     */
    private function bundle(Collection $open, int $actorId, Community $community, Collection $recipients): void
    {
        if ($open->isEmpty()) {
            return;
        }

        Notification::query()->whereIn('id', $open->pluck('id')->all())->toBase()->update(['actor_id' => $actorId, 'seen_at' => null, 'updated_at' => now()]);

        foreach ($open as $row) {
            $user = $recipients->get($row->user_id);
            if ($user !== null) {
                NotificationsChanged::dispatch($user->cuid, $community->cuid, $row->cuid);
            }
        }
    }
}
