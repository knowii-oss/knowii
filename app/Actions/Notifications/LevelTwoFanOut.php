<?php

namespace App\Actions\Notifications;

use App\Enums\KnowiiFollowState;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpaceType;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\LessonProgress;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\NotificationAccess;
use App\Support\NotificationItem;
use App\Support\SpaceNotificationLevels;
use Illuminate\Support\Facades\DB;

/**
 * Who is told at level 2, "Following", and how many at a time (#1475, ADR 0042, design 2.2, 3, 6.14). Notify holds the
 * rules and starts the chain of jobs; this class answers for one chunk of recipients:
 *
 * - `thread_comment`, `thread_reply`: the members who follow the thread (state "following").
 * - `space_post`: the members whose level for the space is "Everything" (a discussion space where only moderators post is
 *   there by default, any other discussion space by choice).
 * - `space_chat`: the members who chose "Everything" for the chat space (it is never a default).
 * - `lesson_new`: the members who have a progress in the course.
 *
 * Never the author, never a member who gets a level 1 row for the same item, who muted the space, who cannot view the
 * space, who is not a member of the community, or who blocked the author. A chunk reads at most `$size` recipients and
 * runs a fixed number of queries, whatever the number of members: the members, their groups and their roles load once.
 */
final class LevelTwoFanOut
{
    public function __construct(private readonly LevelTwoWriter $writer) {}

    /**
     * The level 2 kind that a new item gives, or null when it gives none (a comment on a resource, a direct message, a
     * post in another space than a discussion space, a section of a course, a marker)
     */
    final public function kindOf(Post|Comment|ChatMessage|Page $item): ?KnowiiNotificationKind
    {
        return match (true) {
            $item instanceof Post => $item->isDeleted() || $item->space->type !== KnowiiSpaceType::Discussion ? null : KnowiiNotificationKind::SpacePost,
            $item instanceof Comment => $item->post_id === null || $item->isDeleted() ? null : KnowiiNotificationKind::ThreadComment,
            $item instanceof ChatMessage => match (true) {
                $item->isDirectMessage(), $item->isDeleted() => null,
                $item->parent_id === null => KnowiiNotificationKind::SpaceChat,
                default => KnowiiNotificationKind::ThreadReply,
            },
            default => $item->isSection() || $item->space->type !== KnowiiSpaceType::Course ? null : KnowiiNotificationKind::LessonNew,
        };
    }

    /**
     * Tell one chunk of recipients.
     *
     * @param  list<int>  $excluded  the members who get a level 1 row for the item
     * @param  int  $after  the id of the last member of the chunk before: the chunk reads the members after it
     * @return ?int The cursor of the next chunk, or null when this was the last one
     */
    final public function chunk(Post|Comment|ChatMessage|Page $item, KnowiiNotificationKind $kind, array $excluded, int $after, int $size): ?int
    {
        $actorId = $item->author_id;
        $space = NotificationItem::spaceOf($item);
        // An item that went away or became a marker while the job waited tells nobody
        if ($actorId === null || $space === null || $this->kindOf($item) !== $kind) {
            return null;
        }
        $community = $item->community;

        $candidates = $this->candidates($item, $kind, $community, $space, $after, $size);
        if ($candidates === []) {
            return null;
        }
        $next = count($candidates) >= $size ? $candidates[array_key_last($candidates)] : null;

        $ids = array_values(array_diff($candidates, [$actorId], $excluded));
        // A member who blocked the author gets nothing (ADR 0028, design 6.9)
        $blockers = DB::table('user_blocks')->where('blocked_id', $actorId)->whereIn('blocker_id', $ids)->pluck('blocker_id')->all();
        $ids = array_values(array_diff($ids, array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, $blockers)));

        if ($ids !== []) {
            $users = User::query()->with(['communities', 'accessGroups'])->whereIn('id', $ids)->get();
            $recipients = CommunityRoles::withKnownAdmins($community, $ids, function () use ($kind, $community, $space, $users) {
                $levels = SpaceNotificationLevels::levelsOf($community, $space, $users);
                $wanted = $users->filter(static fn (User $user): bool => match ($kind) {
                    // "Everything" is what these two kinds are about
                    KnowiiNotificationKind::SpacePost, KnowiiNotificationKind::SpaceChat => $levels[$user->id] === KnowiiSpaceNotificationLevel::All,
                    // A muted space gives nothing, a followed thread or a started course included
                    default => $levels[$user->id] !== KnowiiSpaceNotificationLevel::None,
                });

                return NotificationAccess::viewers($wanted, $community, $space);
            });

            $this->writer->write($kind, $item, $community, $space, $actorId, $recipients);
        }

        return $next;
    }

    /**
     * The next member ids to look at, in the order of their id, `$size` at most
     *
     * @return list<int>
     */
    private function candidates(Post|Comment|ChatMessage|Page $item, KnowiiNotificationKind $kind, Community $community, Space $space, int $after, int $size): array
    {
        $ids = match ($kind) {
            KnowiiNotificationKind::ThreadComment => $this->followers('post_id', $item instanceof Comment ? $item->post_id : null, $after, $size),
            KnowiiNotificationKind::ThreadReply => $this->followers('chat_message_id', $item instanceof ChatMessage ? $item->parent_id : null, $after, $size),
            KnowiiNotificationKind::LessonNew => LessonProgress::query()
                ->where('space_id', $space->id)
                ->where('user_id', '>', $after)
                ->groupBy('user_id')
                ->orderBy('user_id')
                ->limit($size)
                ->pluck('user_id')
                ->all(),
            // "Everything" by default: every member is a candidate, and the ones who chose another level drop out. Else
            // only the members who chose "Everything" are
            default => SpaceNotificationLevels::defaultOf($space) === KnowiiSpaceNotificationLevel::All
                ? User::query()
                    ->where('id', '>', $after)
                    ->where(static fn ($query) => $query->whereIn('id', DB::table('community_members')->where('community_id', $community->id)->select('user_id'))->orWhere('id', $community->owner_id))
                    ->orderBy('id')
                    ->limit($size)
                    ->pluck('id')
                    ->all()
                : NotificationSpaceLevel::query()
                    ->where('space_id', $space->id)
                    ->where('level', KnowiiSpaceNotificationLevel::All->value)
                    ->where('user_id', '>', $after)
                    ->orderBy('user_id')
                    ->limit($size)
                    ->pluck('user_id')
                    ->all(),
        };

        return array_values(array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, $ids));
    }

    /**
     * @param  'post_id'|'chat_message_id'  $column
     * @return list<int>
     */
    private function followers(string $column, ?int $threadId, int $after, int $size): array
    {
        if ($threadId === null) {
            return [];
        }

        return array_values(array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, NotificationFollow::query()
            ->where($column, $threadId)
            ->where('state', KnowiiFollowState::Following->value)
            ->where('user_id', '>', $after)
            ->orderBy('user_id')
            ->limit($size)
            ->pluck('user_id')
            ->all()));
    }
}
