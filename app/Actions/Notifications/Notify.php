<?php

namespace App\Actions\Notifications;

use App\Constants;
use App\Contracts\Notifications\Notifies;
use App\Enums\KnowiiNotificationKind;
use App\Events\Notifications\NotificationsChanged;
use App\Jobs\FanOutNotifications;
use App\Jobs\WriteNotifications;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\NotificationAccess;
use App\Support\NotificationItem;
use App\Support\SpaceNotificationLevels;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The one place that decides who is told, and writes the rows (#1474, ADR 0042, design 3, 6.6, 6.8, 6.9, 6.10).
 *
 * - `post_comment`: a first-level comment on a post tells the author of the post. Subject: the comment.
 * - `comment_reply`: a reply to a comment tells the author of that comment. Subject: the reply.
 * - `mention`: a mention in a post, a comment or a chat message tells the mentioned member. Subject: the item.
 *
 * One row per recipient and per item. When one comment gives a member several reasons, the order is mention, reply,
 * comment. Nobody is told about their own action, nor about an edit (the update actions never call this), nor when
 * they cannot view the item, nor when they blocked the author. While the member has an unread row for the same thing,
 * a new comment bundles into it (the row says "New" again) instead of adding a row. Mentions do not bundle, and three
 * unread mention rows from one author in an hour are the most a member gets from them.
 *
 * Level 2, "Following" (#1475), has five kinds, all written by fanOut() in chunks of 500 members, one queued job per chunk
 * (LevelTwoFanOut holds the audience, LevelTwoWriter the bulk writes):
 *
 * - `thread_comment`: a new comment in a post thread tells the members who follow the thread.
 * - `thread_reply`: a new reply in a chat thread tells the members who follow the thread.
 * - `space_post`: a new post in a discussion space tells the members whose level for the space is "Everything", after 5 minutes.
 * - `space_chat`: a new first-level message in a chat space tells the members who chose "Everything" for it.
 * - `lesson_new`: a new lesson in a course tells the members who started the course.
 *
 * A member who gets a level 1 row for the item gets no level 2 row for it. A muted space or thread gives the member nothing
 * at any level: the level 1 kinds respect it too. Reactions give nothing.
 *
 * Callers use notify(): it queues the jobs on the queue `notifications`, after the commit of the caller. The jobs call
 * deliver() and fanOut(). Imports and seeders wrap their work in Notify::silenced().
 */
final class Notify implements Notifies
{
    /**
     * How many silenced() callbacks are running: bigger than 0 means silence
     */
    private static int $silencedDepth = 0;

    /**
     * Run the callback without any notification: imports, seeders, bulk actions (design 6.10).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    final public static function silenced(Closure $callback)
    {
        self::$silencedDepth++;

        try {
            return $callback();
        } finally {
            self::$silencedDepth--;
        }
    }

    final public static function isSilenced(): bool
    {
        return self::$silencedDepth > 0;
    }

    final public function notify(Post|Comment|ChatMessage|Page $item, bool $now = false): void
    {
        if (self::isSilenced()) {
            return;
        }
        // No notifications for direct messages (a later slice)
        if ($item instanceof ChatMessage && $item->isDirectMessage()) {
            return;
        }
        // The second guard: an item that is old at the moment of the event is history, not news
        if ($item->created_at->lt(now()->subMinutes(Constants::NOTIFICATIONS_ITEM_MAX_AGE_MINUTES))) {
            return;
        }

        // Level 1: a reply, a mention (a page has neither)
        if (! $item instanceof Page) {
            WriteNotifications::dispatch($item);
        }

        // Level 2: the followers of the thread, the members at "Everything", the members of the course. The job decides if
        // the item gives a kind (the space type is its to read). A new post waits 5 minutes: an edit inside the wait
        // changes the content and tells nobody, a deletion inside the wait tells nobody (design 6.11)
        if (($item instanceof Comment && $item->post_id === null) || ($item instanceof Page && $item->isSection())) {
            return;
        }
        $fanOut = FanOutNotifications::dispatch($item);
        if ($item instanceof Post && ! $now) {
            $fanOut->delay(now()->addMinutes(Constants::NOTIFICATIONS_POST_FANOUT_DELAY_MINUTES));
        }
    }

    final public function fanOut(Post|Comment|ChatMessage|Page $item, ?array $excluded = null, int $after = 0, ?int $size = null): void
    {
        $fanOut = app(LevelTwoFanOut::class);
        $kind = $fanOut->kindOf($item);
        if ($kind === null) {
            return;
        }

        $size ??= Constants::NOTIFICATIONS_FANOUT_CHUNK;
        $excluded ??= $item instanceof Page ? [] : array_keys($this->reasons($item));

        $next = $fanOut->chunk($item, $kind, $excluded, $after, $size);
        if ($next !== null) {
            FanOutNotifications::dispatch($item, $excluded, $next, $size);
        }
    }

    final public function deliver(Post|Comment|ChatMessage $item): void
    {
        if ($item instanceof ChatMessage && $item->isDirectMessage()) {
            return;
        }
        // A chat message is only soft-deleted: one that was deleted before the job ran tells nobody
        if ($item instanceof ChatMessage && $item->isDeleted()) {
            return;
        }

        $actorId = $item->author_id;
        if ($actorId === null) {
            return;
        }

        $reasons = $this->reasons($item);
        unset($reasons[$actorId]);
        if ($reasons === []) {
            return;
        }

        $community = $item->community;
        $space = NotificationItem::spaceOf($item);

        // A muted space or thread gives nothing, a reply and a mention included (design 1.2.10, 2.2.6)
        foreach (SpaceNotificationLevels::mutedAmong($space, NotificationItem::threadOf($item), array_keys($reasons)) as $mutedId) {
            unset($reasons[$mutedId]);
        }
        if ($reasons === []) {
            return;
        }

        // A member who blocked the author gets nothing (ADR 0028, design 6.9)
        $blockers = DB::table('user_blocks')->where('blocked_id', $actorId)->whereIn('blocker_id', array_keys($reasons))->pluck('blocker_id')->all();

        foreach (User::query()->with(['communities', 'accessGroups'])->whereIn('id', array_keys($reasons))->get() as $recipient) {
            if (in_array($recipient->id, $blockers, true) || ! NotificationAccess::canView($recipient, $community, $space)) {
                continue;
            }

            $this->write($recipient, $reasons[$recipient->id], $item, $community, $space, $actorId);
        }
    }

    /**
     * The members to tell and their reason, by user id. One reason per member: mention first, then reply, then comment.
     *
     * @return array<int, KnowiiNotificationKind>
     */
    private function reasons(Post|Comment|ChatMessage $item): array
    {
        $reasons = [];
        $add = static function (?int $userId, KnowiiNotificationKind $kind) use (&$reasons): void {
            if ($userId !== null && ! isset($reasons[$userId])) {
                $reasons[$userId] = $kind;
            }
        };

        foreach ($item->mentions()->pluck('user_id') as $userId) {
            $add(is_numeric($userId) ? (int) $userId : null, KnowiiNotificationKind::Mention);
        }

        if ($item instanceof Comment) {
            if ($item->parent_id !== null) {
                $add($item->parent?->author_id, KnowiiNotificationKind::CommentReply);
            } elseif ($item->post_id !== null) {
                $add($item->post?->author_id, KnowiiNotificationKind::PostComment);
            }
        }

        return $reasons;
    }

    private function write(User $recipient, KnowiiNotificationKind $kind, Post|Comment|ChatMessage $item, Community $community, ?Space $space, int $actorId): void
    {
        $column = match (true) {
            $item instanceof Post => 'post_id',
            $item instanceof Comment => 'comment_id',
            default => 'chat_message_id',
        };

        // A retry of the job never writes a second row for the same item
        if (Notification::query()->where('user_id', $recipient->id)->where('kind', $kind->value)->where($column, $item->id)->exists()) {
            return;
        }

        if ($kind === KnowiiNotificationKind::Mention && $this->mentionCapReached($recipient, $actorId)) {
            return;
        }

        $dedupeKey = $this->dedupeKey($kind, $item, $column);
        if ($kind !== KnowiiNotificationKind::Mention) {
            $open = $this->openRow($recipient, $dedupeKey);
            if ($open !== null) {
                $this->bundle($open, $actorId, $recipient, $community);

                return;
            }
        }

        try {
            $row = DB::transaction(fn (): Notification => Notification::create([
                'user_id' => $recipient->id,
                'community_id' => $community->id,
                'space_id' => $space?->id,
                'kind' => $kind,
                'actor_id' => $actorId,
                $column => $item->id,
                'dedupe_key' => $dedupeKey,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Another job wrote the open row first (one open row per member and thing): join it
            $open = $this->openRow($recipient, $dedupeKey);
            if ($open !== null && $kind !== KnowiiNotificationKind::Mention) {
                $this->bundle($open, $actorId, $recipient, $community);
            }

            return;
        }

        NotificationsChanged::dispatch($recipient->cuid, $community->cuid, $row->cuid);
    }

    /**
     * The thing that rows bundle on: the post for a comment, the parent comment for a reply, the item for a mention
     */
    private function dedupeKey(KnowiiNotificationKind $kind, Post|Comment|ChatMessage $item, string $column): string
    {
        return match ($kind) {
            KnowiiNotificationKind::PostComment => 'post_comment:post:'.($item instanceof Comment ? $item->post_id : $item->id),
            KnowiiNotificationKind::CommentReply => 'comment_reply:comment:'.($item instanceof Comment ? $item->parent_id : $item->id),
            KnowiiNotificationKind::Mention => 'mention:'.$column.':'.$item->id,
            // The level 2 kinds are written by fanOut(), not here
            default => throw new LogicException('The kind '.$kind->value.' is not a level 1 kind'),
        };
    }

    private function openRow(User $recipient, string $dedupeKey): ?Notification
    {
        return Notification::query()->where('user_id', $recipient->id)->where('dedupe_key', $dedupeKey)->whereNull('read_at')->first();
    }

    /**
     * A new comment on a thing with an unread row: the last author changes, the line is "New" again, and it moves up
     * the list. The subject stays the first comment that the member did not read (design 6.6).
     */
    private function bundle(Notification $open, int $actorId, User $recipient, Community $community): void
    {
        $open->forceFill(['actor_id' => $actorId, 'seen_at' => null, 'updated_at' => now()])->save();

        NotificationsChanged::dispatch($recipient->cuid, $community->cuid, $open->cuid);
    }

    /**
     * Storm cap (design 6.10.2): a member has 3 unread mention rows from the same author, less than one hour old
     */
    private function mentionCapReached(User $recipient, int $actorId): bool
    {
        return Notification::query()
            ->where('user_id', $recipient->id)
            ->where('kind', KnowiiNotificationKind::Mention->value)
            ->where('actor_id', $actorId)
            ->whereNull('read_at')
            ->where('created_at', '>', now()->subMinutes(Constants::NOTIFICATIONS_MENTION_CAP_MINUTES))
            ->count() >= Constants::NOTIFICATIONS_MENTION_CAP;
    }
}
