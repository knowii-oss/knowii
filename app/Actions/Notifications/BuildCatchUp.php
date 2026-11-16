<?php

namespace App\Actions\Notifications;

use App\Constants;
use App\Contracts\Notifications\BuildsCatchUp;
use App\Enums\KnowiiFollowState;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\NotificationResource;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Support\Excerpt;
use App\Support\NotificationAccess;
use App\Support\NotificationBundles;
use App\Support\SpaceNotificationLevels;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The "Catch up" card (#1476, ADR 0048, design 5.5). The lines come from the live content and from the notification rows:
 * nothing is stored. The number of queries is fixed, whatever the number of spaces.
 *
 * Announcements are never left out because of their age: only `since` limits them. A muted space (level "Muted") gives
 * none, like everything else that the member muted (design 1.2.10).
 *
 * @phpstan-import-type Line from BuildsCatchUp
 * @phpstan-import-type Target from BuildsCatchUp
 */
final class BuildCatchUp implements BuildsCatchUp
{
    private const int NEEDS_YOU_LINES = 2;

    private const int ANNOUNCEMENT_LINES = 2;

    final public function build(User $user, Community $community, array $input): array
    {
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community have a "Catch up" card');
        }

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $validated = Validator::make($input, [
            'since' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.$today->toDateString(),
                'after_or_equal:'.$today->subDays(Constants::CATCH_UP_MAX_DAYS_BACK)->toDateString(),
            ],
        ])->validate();
        $date = $validated['since'];
        $since = CarbonImmutable::parse(is_string($date) ? $date : $today->toDateString(), 'UTC')->startOfDay();

        $spaces = NotificationAccess::viewableSpaces($user, $community);
        $levels = SpaceNotificationLevels::levelsForSpaces($user, $community, $spaces);
        /** @var list<int> $blocked */
        $blocked = DB::table('user_blocks')->where('blocker_id', $user->id)->pluck('blocked_id')->map(static fn ($id): int => is_numeric($id) ? (int) $id : 0)->values()->all();

        [$needsYou, $moreNeedsYou] = $this->needsYou($user, $community, $blocked);
        [$announcements, $moreAnnouncements] = $this->announcements($user, $spaces, $levels, $blocked, $since);
        [$threads, $moreThreads] = $this->threads($user, $community, $spaces, $levels, $blocked, $since);

        return [
            'lines' => [...$needsYou, ...$announcements, ...$threads],
            'has_more' => $moreNeedsYou || $moreAnnouncements || $moreThreads,
        ];
    }

    /**
     * The open rows of level 1 of "Needs you": the same rows as the list, after the access check
     *
     * @param  list<int>  $blocked
     * @return array{0: list<Line>, 1: bool}
     */
    private function needsYou(User $user, Community $community, array $blocked): array
    {
        $rows = Notification::query()
            ->where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->needingYou()
            ->whereNull('read_at')
            ->where('updated_at', '>=', now()->subDays(Constants::NOTIFICATIONS_FOLD_DAYS));

        NotificationAccess::prune($rows);

        // Blocking a member deletes their rows (PruneNotifications::forBlock): a row of a blocked author never shows here
        if ($blocked !== []) {
            $rows->where(fn (Builder $inner) => $inner->whereNull('actor_id')->orWhereNotIn('actor_id', $blocked));
        }

        $found = $rows->orderByDesc('updated_at')->orderByDesc('id')->limit(self::NEEDS_YOU_LINES + 1)->get();
        NotificationBundles::attach($user, $found);

        $lines = [];
        foreach ($found->take(self::NEEDS_YOU_LINES) as $row) {
            $space = $row->space;
            $lines[] = [
                'kind' => 'needs_you',
                'title' => NotificationResource::titleOf($row),
                'excerpt' => NotificationResource::excerptOf($row),
                'space' => $space === null ? null : ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name],
                'author' => $this->author($row->actor),
                'date' => $row->updated_at,
                'target' => NotificationResource::targetOf($row),
            ];
        }

        return [$lines, $found->count() > self::NEEDS_YOU_LINES];
    }

    /**
     * The posts of other members in the discussion spaces where only the moderators post, that the member reads and did not
     * mute, since the date, newest first
     *
     * @param  Collection<int, Space>  $spaces  the spaces that the member can view
     * @param  array<int, KnowiiSpaceNotificationLevel>  $levels
     * @param  list<int>  $blocked
     * @return array{0: list<Line>, 1: bool}
     */
    private function announcements(User $user, Collection $spaces, array $levels, array $blocked, CarbonImmutable $since): array
    {
        $ids = $spaces
            ->filter(static fn (Space $space): bool => $space->type === KnowiiSpaceType::Discussion
                && $space->posting_policy === KnowiiSpacePostingPolicy::Moderators
                && $levels[$space->id] !== KnowiiSpaceNotificationLevel::None)
            ->keys()
            ->all();
        if ($ids === []) {
            return [[], false];
        }

        $posts = Post::query()
            ->with('author')
            ->whereIn('space_id', $ids)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->tap(fn (Builder $query) => $this->notBy($query, 'author_id', $user, $blocked))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ANNOUNCEMENT_LINES + 1)
            ->get();

        $lines = [];
        foreach ($posts->take(self::ANNOUNCEMENT_LINES) as $post) {
            $lines[] = [
                'kind' => 'announcement',
                'title' => $post->title,
                'excerpt' => Excerpt::of($post->body, Constants::NOTIFICATIONS_EXCERPT_LENGTH),
                'space' => $this->space($spaces->get($post->space_id)),
                'author' => $this->author($post->author),
                'date' => $post->created_at,
                'target' => $this->target($post, null),
            ];
        }

        return [$lines, $posts->count() > self::ANNOUNCEMENT_LINES];
    }

    /**
     * The thread with the most new comments since the date, among the posts that the member follows and the posts of the
     * spaces at "Everything" (not muted, not stopped). No line when no thread has a new comment.
     *
     * @param  Collection<int, Space>  $spaces  the spaces that the member can view
     * @param  array<int, KnowiiSpaceNotificationLevel>  $levels
     * @param  list<int>  $blocked
     * @return array{0: list<Line>, 1: bool}
     */
    private function threads(User $user, Community $community, Collection $spaces, array $levels, array $blocked, CarbonImmutable $since): array
    {
        $discussions = $spaces->filter(static fn (Space $space): bool => $space->type === KnowiiSpaceType::Discussion);
        if ($discussions->isEmpty()) {
            return [[], false];
        }
        $everything = $discussions->filter(static fn (Space $space): bool => $levels[$space->id] === KnowiiSpaceNotificationLevel::All)->keys()->all();

        $following = NotificationFollow::query()
            ->where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('state', KnowiiFollowState::Following->value)
            ->whereNotNull('post_id')
            ->select('post_id');
        $silenced = NotificationFollow::query()
            ->where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->whereIn('state', [KnowiiFollowState::Muted->value, KnowiiFollowState::Stopped->value])
            ->whereNotNull('post_id')
            ->select('post_id');

        $query = DB::table('comments')
            ->join('posts', 'posts.id', '=', 'comments.post_id')
            ->where('posts.community_id', $community->id)
            ->whereIn('posts.space_id', $discussions->keys()->all())
            ->whereNull('posts.deleted_at')
            ->whereNull('comments.deleted_at')
            ->where('comments.created_at', '>=', $since)
            ->where(fn (QueryBuilder $inner) => $inner->whereNull('comments.author_id')->orWhere('comments.author_id', '<>', $user->id));
        if ($blocked !== []) {
            $query->where(fn (QueryBuilder $inner) => $inner->whereNull('comments.author_id')->orWhereNotIn('comments.author_id', $blocked));
        }

        $top = $query
            ->where(fn (QueryBuilder $inner) => $inner
                ->whereIn('comments.post_id', $following->toBase())
                ->orWhere(fn (QueryBuilder $everyone) => $everyone->whereIn('posts.space_id', $everything)->whereNotIn('comments.post_id', $silenced->toBase())))
            ->groupBy('comments.post_id')
            ->selectRaw('comments.post_id as post_id, count(*) as new_comments, max(comments.id) as last_comment_id')
            ->orderByDesc('new_comments')
            ->orderByDesc('last_comment_id')
            ->limit(2)
            ->get();
        if ($top->isEmpty()) {
            return [[], false];
        }

        $first = $top->first();
        $lastId = $first->last_comment_id;
        $comment = is_numeric($lastId) ? Comment::query()->with(['author', 'post'])->find((int) $lastId) : null;
        $post = $comment?->post;
        if ($comment === null || $post === null) {
            return [[], false];
        }

        return [[[
            'kind' => 'thread',
            'title' => $post->title,
            'excerpt' => Excerpt::of($comment->body, Constants::NOTIFICATIONS_EXCERPT_LENGTH),
            'space' => $this->space($spaces->get($post->space_id)),
            'author' => $this->author($comment->author),
            'date' => $comment->created_at,
            'target' => $this->target($post, $comment),
        ]], $top->count() > 1];
    }

    /**
     * Keep the rows that another member wrote and that the member did not block. A row without an author (a deleted account)
     * stays: its author shows as a deleted member.
     *
     * @param  Builder<Post>  $query
     * @param  list<int>  $blocked
     */
    private function notBy(Builder $query, string $column, User $user, array $blocked): void
    {
        $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, '<>', $user->id));
        if ($blocked !== []) {
            $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhereNotIn($column, $blocked));
        }
    }

    /**
     * @return array{cuid: string, slug: string, name: string}|null
     */
    private function space(?Space $space): ?array
    {
        return $space === null ? null : ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name];
    }

    /**
     * @return array{cuid: string, name: string, username: string, avatar_url: string|null}|null
     */
    private function author(?User $author): ?array
    {
        if ($author === null) {
            return null;
        }

        /** @var array{cuid: string, name: string, username: string, avatar_url: string|null} $summary */
        $summary = $author->toResource(AuthorResource::class)->resolve();

        return $summary;
    }

    /**
     * @return Target
     */
    private function target(Post $post, ?Comment $comment): array
    {
        return [
            'post_slug' => $post->slug,
            'post_cuid' => $post->cuid,
            'comment_cuid' => $comment?->cuid,
            'chat_message_cuid' => null,
            'thread_cuid' => null,
            'resource_cuid' => null,
            'resource_slug' => null,
            'page_slug' => null,
        ];
    }
}
