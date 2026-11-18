<?php

use App\Actions\Notifications\ChooseSpaceLevels;
use App\Actions\Notifications\FollowThreads;
use App\Actions\Notifications\Notify;
use App\ApiRoutes;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Notifications\ChoosesSpaceLevels;
use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\ListsNotifications;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\PrunesNotifications;
use App\Contracts\Pages\CreatesPages;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Users\BlocksMembers;
use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiFollowState;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiNotificationReason;
use App\Enums\KnowiiSpaceNotificationLevel;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Notifications\NotificationsChanged;
use App\Exceptions\BusinessException;
use App\Jobs\FanOutNotifications;
use App\Jobs\WriteNotifications;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\LessonProgress;
use App\Models\Notification;
use App\Models\NotificationFollow;
use App\Models\NotificationSpaceLevel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityRoles;
use App\Support\NotificationAccess;
use App\Support\SpaceNotificationLevels;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/*
 * Follows and space levels (#1475, ADR 0042): thread follows, the level of a space, the level 2 kinds ("Following"), the
 * fan-out, the API.
 *
 * The queue is "sync" in the tests: the jobs run when the caller commits. A test that needs the 5 minutes of a new post
 * fakes the job `FanOutNotifications` and runs it by hand.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->space = Space::factory()->inGroup($this->group)->create();
    // The place where the team speaks to everyone: a discussion space where only the moderators post
    $this->announce = Space::factory()->inGroup($this->group)->create(['posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->chat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    $this->course = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Course]);
    $this->owner = $this->c['owner'];
    $this->author = $this->c['member'];
    $this->replier = $this->c['moderator'];
    $this->third = $this->c['admin'];
    // Plain members: the owner and the admins view every space, so they cannot show a closed space
    $this->plain = User::factory()->withUserProfile()->create();
    $this->other = User::factory()->withUserProfile()->create();
    $this->community->users()->attach([$this->plain->id => ['role' => 'member'], $this->other->id => ['role' => 'member']]);
});

function flwPost(User $author, Space $space, string $title = 'A post for the tests', string $body = 'A post without mentions', array $extra = []): Post
{
    return app(CreatesPosts::class)->create($author, $space, ['title' => $title, 'body' => $body, ...$extra]);
}

function flwComment(User $author, Post $post, string $body = 'A comment without mentions', ?Comment $parent = null): Comment
{
    return app(CreatesComments::class)->create($author, $post, ['body' => $body, 'parent_cuid' => $parent?->cuid]);
}

function flwMessage(User $author, Space $space, string $body = 'A message without mentions', ?ChatMessage $parent = null): ChatMessage
{
    return app(CreatesChatMessages::class)->create($author, $space, ['body' => $body, 'parent_cuid' => $parent?->cuid]);
}

function flwLesson(User $author, Space $space, string $title = 'A lesson'): Page
{
    return app(CreatesPages::class)->create($author, $space, ['title' => $title, 'body' => 'The body of the lesson']);
}

function flwStart(User $member, Space $course, Page $lesson): void
{
    LessonProgress::query()->create(['user_id' => $member->id, 'community_id' => $course->community_id, 'space_id' => $course->id, 'page_id' => $lesson->id, 'completed_at' => now()]);
}

function flwLevel(User $user, Space $space, string $level): void
{
    app(ChoosesSpaceLevels::class)->set($user, $space, ['level' => $level]);
}

/**
 * The notification rows of a member, oldest first
 *
 * @return Collection<int, Notification>
 */
function flwRows(User $user, ?KnowiiNotificationKind $kind = null): Collection
{
    return Notification::query()->where('user_id', $user->id)->when($kind !== null, fn ($query) => $query->where('kind', $kind?->value))->orderBy('id')->get();
}

/**
 * @return list<string>
 */
function flwKinds(User $user): array
{
    return flwRows($user)->map(fn (Notification $row) => $row->kind->value)->all();
}

function flwFollow(User $user, Post|ChatMessage $thread): ?NotificationFollow
{
    return NotificationFollow::query()->where('user_id', $user->id)->where($thread instanceof Post ? 'post_id' : 'chat_message_id', $thread->id)->first();
}

function flwPath(string $route, array $params = []): string
{
    return ApiRoutes::path($route, $params);
}

function flwThreadPath(string $route, Community $community, Space $space, Post|ChatMessage $thread): string
{
    return ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        $thread instanceof Post ? ApiRoutes::PARAM_POST : ApiRoutes::PARAM_MESSAGE => $thread->cuid,
    ]);
}

function flwSpacePath(string $route, Community $community, Space $space): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
}

/**
 * The "Following" list of the member, as the API gives it
 */
function flwList(User $user, string $query = 'level=2'): array
{
    return test()->actingAs($user->fresh())->getJson(flwPath(ApiRoutes::NOTIFICATIONS).'?'.$query)->assertOk()->json('data');
}

// The kinds

test('the level 2 kinds are the six of "Following", with the reason that the client turns into a sentence', function () {
    expect(KnowiiNotificationKind::valuesOfLevel(2))->toBe(['thread_comment', 'thread_reply', 'space_post', 'space_chat', 'lesson_new', 'event_new'])
        ->and(KnowiiNotificationKind::valuesOfLevel(1))->toBe(['post_comment', 'comment_reply', 'mention'])
        ->and(KnowiiNotificationKind::PostComment->reason())->toBe(KnowiiNotificationReason::WrotePost)
        ->and(KnowiiNotificationKind::CommentReply->reason())->toBe(KnowiiNotificationReason::WroteComment)
        ->and(KnowiiNotificationKind::Mention->reason())->toBe(KnowiiNotificationReason::Mentioned)
        ->and(KnowiiNotificationKind::ThreadComment->reason())->toBe(KnowiiNotificationReason::FollowsThread)
        ->and(KnowiiNotificationKind::ThreadReply->reason())->toBe(KnowiiNotificationReason::FollowsThread)
        ->and(KnowiiNotificationKind::SpacePost->reason())->toBe(KnowiiNotificationReason::SpaceEverything)
        ->and(KnowiiNotificationKind::SpaceChat->reason())->toBe(KnowiiNotificationReason::SpaceEverything)
        ->and(KnowiiNotificationKind::LessonNew->reason())->toBe(KnowiiNotificationReason::StartedCourse)
        ->and(KnowiiNotificationKind::EventNew->reason())->toBe(KnowiiNotificationReason::SpaceEverything);
});

test('the contracts of the follows and the levels are bound to their actions', function () {
    expect(app(FollowsThreads::class))->toBeInstanceOf(FollowThreads::class)
        ->and(app(ChoosesSpaceLevels::class))->toBeInstanceOf(ChooseSpaceLevels::class);
});

test('reactions give nothing: no kind and no row', function () {
    $post = flwPost($this->author, $this->space);
    $before = Notification::query()->count();

    $this->actingAs($this->replier)->postJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, $this->community, $this->space, $post), ['emoji' => '👍'])->assertSuccessful();

    expect(Notification::query()->count())->toBe($before)
        ->and(array_filter(KnowiiNotificationKind::toStringArray(), fn (string $kind) => str_contains($kind, 'reaction')))->toBe([]);
});

// Thread follows: writing follows, a mention does not

test('a member who writes a post, comments it or replies in a chat thread follows the thread', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->replier, $post);
    // The thread of a first message starts with its first reply: nobody follows a message that nobody answered
    $message = flwMessage($this->author, $this->chat);
    expect(flwFollow($this->author, $message))->toBeNull();
    flwMessage($this->replier, $this->chat, 'A reply', $message);

    expect(flwFollow($this->author, $post)->state)->toBe(KnowiiFollowState::Following)
        ->and(flwFollow($this->author, $post)->reason)->toBe(KnowiiFollowReason::Wrote)
        ->and(flwFollow($this->replier, $post)->reason)->toBe(KnowiiFollowReason::Replied)
        ->and(flwFollow($this->author, $message)->reason)->toBe(KnowiiFollowReason::Wrote)
        ->and(flwFollow($this->replier, $message)->reason)->toBe(KnowiiFollowReason::Replied)
        ->and(flwFollow($this->replier, $message)->community_id)->toBe($this->community->id);
});

test('a mention does not make the member follow the thread, and an edit makes nobody follow', function () {
    $post = flwPost($this->author, $this->space, 'Mention post', 'Hello @'.$this->plain->username);

    expect(flwRows($this->plain, KnowiiNotificationKind::Mention))->toHaveCount(1)
        ->and(flwFollow($this->plain, $post))->toBeNull();

    flwComment($this->replier, $post, 'Another mention, @'.$this->other->username);
    expect(flwFollow($this->other, $post))->toBeNull();
});

test('stopping to follow is remembered: writing again does not follow the thread again, a request does', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->replier, $post);

    $stopped = app(FollowsThreads::class)->stopFollowing($this->replier, $post);
    expect($stopped->state)->toBe(KnowiiFollowState::Stopped)->and($stopped->reason)->toBe(KnowiiFollowReason::Manual);

    flwComment($this->replier, $post, 'I write again');
    expect(flwFollow($this->replier, $post)->state)->toBe(KnowiiFollowState::Stopped);

    // The next comment of another member does not tell the member who stopped
    flwComment($this->plain, $post, 'Someone else');
    expect(flwRows($this->replier, KnowiiNotificationKind::ThreadComment))->toHaveCount(0);

    $followed = app(FollowsThreads::class)->follow($this->replier, $post);
    expect($followed->state)->toBe(KnowiiFollowState::Following)->and($followed->reason)->toBe(KnowiiFollowReason::Manual);
});

test('stopping a thread that the member never followed leaves a row, so that writing does not follow it', function () {
    $post = flwPost($this->author, $this->space);

    app(FollowsThreads::class)->stopFollowing($this->plain, $post);
    flwComment($this->plain, $post);

    expect(flwFollow($this->plain, $post)->state)->toBe(KnowiiFollowState::Stopped);
});

test('following a thread already followed keeps its reason', function () {
    $post = flwPost($this->author, $this->space);

    $follow = app(FollowsThreads::class)->follow($this->author, $post);

    expect($follow->reason)->toBe(KnowiiFollowReason::Wrote)
        ->and(NotificationFollow::query()->where('user_id', $this->author->id)->count())->toBe(1);
});

test('nobody follows inside Notify::silenced, nor a marker, and a member who left follows nothing', function () {
    $post = Notify::silenced(fn () => flwPost($this->author, $this->space));
    expect(flwFollow($this->author, $post))->toBeNull();

    $marker = Post::factory()->inSpace($this->space)->create(['author_id' => $this->author->id, 'deleted_at' => now()]);
    app(FollowsThreads::class)->followByWriting($this->author, $marker, KnowiiFollowReason::Wrote);
    expect(flwFollow($this->author, $marker))->toBeNull();

    $this->community->removeUser($this->plain);
    app(FollowsThreads::class)->followByWriting($this->plain, $post, KnowiiFollowReason::Replied);
    expect(flwFollow($this->plain, $post))->toBeNull();
});

// thread_comment (row 4)

test('a new comment tells the followers of the thread, not its author, and not the author of the post who gets a level 1 row', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);

    // The author of the post gets the level 1 row only. The first commenter follows the thread: level 2. The author of the comment gets nothing
    expect(flwKinds($this->author))->toBe(['post_comment'])
        ->and(flwKinds($this->plain))->toBe(['thread_comment'])
        ->and(flwKinds($this->replier))->toBe([]);
    $row = flwRows($this->plain)->first();
    expect($row->kind->level())->toBe(2)
        ->and($row->actor_id)->toBe($this->replier->id)
        ->and($row->space_id)->toBe($this->space->id)
        ->and($row->comment_id)->toBe(Comment::query()->where('author_id', $this->replier->id)->value('id'));
});

test('level 1 wins over level 2 for the same comment: a reply and a mention give one row', function () {
    $post = flwPost($this->author, $this->space);
    $first = flwComment($this->plain, $post);
    flwComment($this->other, $post);
    // The row of the thread of the member who wrote the first comment is open, with the last author "other"
    expect(flwRows($this->plain, KnowiiNotificationKind::ThreadComment))->toHaveCount(1);

    // A reply to the comment of plain: plain gets the reply (level 1) and no new thread row, the bundle does not move
    $threadRow = flwRows($this->plain, KnowiiNotificationKind::ThreadComment)->first();
    flwComment($this->replier, $post, 'A reply', $first);
    expect(flwKinds($this->plain))->toBe(['thread_comment', 'comment_reply'])
        ->and($threadRow->fresh()->actor_id)->toBe($this->other->id);

    // A mention in a comment: the mentioned follower gets the mention, and the thread row does not move for it
    $otherRow = flwRows($this->other, KnowiiNotificationKind::ThreadComment)->first();
    flwComment($this->third, $post, 'Hello @'.$this->other->username);
    expect(flwKinds($this->other))->toBe(['thread_comment', 'mention'])
        ->and($otherRow->fresh()->actor_id)->toBe($this->replier->id);
});

test('the rows of a thread bundle: one open row per member and thread, "New" again, moved up, the subject stays', function () {
    $post = flwPost($this->author, $this->space);
    $second = flwComment($this->plain, $post);
    $firstNews = flwComment($this->replier, $post);
    $row = flwRows($this->plain, KnowiiNotificationKind::ThreadComment)->first();
    $row->forceFill(['seen_at' => now(), 'updated_at' => now()->subHour()])->save();

    flwComment($this->third, $post, 'Third comment', null);

    $rows = flwRows($this->plain, KnowiiNotificationKind::ThreadComment);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->comment_id)->toBe($firstNews->id)
        ->and($rows[0]->actor_id)->toBe($this->third->id)
        ->and($rows[0]->seen_at)->toBeNull()
        ->and($rows[0]->updated_at->isAfter(now()->subMinute()))->toBeTrue();

    // The list counts the live comments of the bundle, from the content
    $item = flwList($this->plain)[0];
    expect($item['kind'])->toBe('thread_comment')
        ->and($item['items_count'])->toBe(2)
        ->and(array_column($item['actors'], 'username'))->toBe([$this->third->username, $this->replier->username])
        ->and($item['title'])->toBe($post->title)
        ->and($second->id)->toBeLessThan($firstNews->id);
});

test('a read row is not opened again: the next comment of the thread makes a new row', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);
    $row = flwRows($this->plain, KnowiiNotificationKind::ThreadComment)->first();
    $this->actingAs($this->plain)->putJson(flwPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]))->assertOk();

    flwComment($this->third, $post);

    expect(flwRows($this->plain, KnowiiNotificationKind::ThreadComment))->toHaveCount(2);
});

test('a comment on a resource tells no follower of a thread, and a reply in a thread counts as a comment of the thread', function () {
    $post = flwPost($this->author, $this->space);
    $first = flwComment($this->plain, $post);
    flwComment($this->replier, $post, 'A reply to the first comment', $first);

    // A reply is a new comment in the thread: the author of the post follows the thread and is told at level 2
    expect(flwKinds($this->author))->toBe(['post_comment', 'thread_comment'])
        ->and(flwKinds($this->plain))->toBe(['comment_reply']);
});

// thread_reply (row 5)

test('a reply in a chat thread tells the followers of the thread, bundled, not the author of the reply', function () {
    $message = flwMessage($this->author, $this->chat, 'The first message');
    flwMessage($this->replier, $this->chat, 'First reply', $message);
    // The author of the first message follows the thread from the first reply
    expect(flwKinds($this->author))->toBe(['thread_reply'])
        ->and(flwKinds($this->replier))->toBe([]);

    flwMessage($this->plain, $this->chat, 'Second reply', $message);
    expect(flwKinds($this->replier))->toBe(['thread_reply'])
        ->and(flwKinds($this->plain))->toBe([]);

    // The author of the thread has one open row, the second reply bundled into it
    $rows = flwRows($this->author);
    expect($rows)->toHaveCount(1)->and($rows[0]->actor_id)->toBe($this->plain->id);
    $item = flwList($this->author)[0];
    expect($item['items_count'])->toBe(2)
        ->and($item['target']['thread_cuid'])->toBe($message->cuid)
        ->and($item['reason'])->toBe('follows_thread')
        ->and($item['follow_reason'])->toBe('wrote');
});

test('a reply that mentions a follower gives the mention only', function () {
    $message = flwMessage($this->author, $this->chat, 'The first message');
    flwMessage($this->replier, $this->chat, 'First reply', $message);

    flwMessage($this->plain, $this->chat, 'Reply for @'.$this->author->username, $message);

    expect(flwKinds($this->author))->toBe(['thread_reply', 'mention'])
        ->and(flwRows($this->author, KnowiiNotificationKind::ThreadReply)->first()->actor_id)->toBe($this->replier->id);
});

test('a first-level message tells the followers of no thread', function () {
    $message = flwMessage($this->author, $this->chat, 'The first message');
    flwMessage($this->replier, $this->chat, 'First reply', $message);

    flwMessage($this->plain, $this->chat, 'Another first message');

    expect(flwRows($this->author, KnowiiNotificationKind::ThreadReply)->first()->actor_id)->toBe($this->replier->id);
});

// space_post (rows 6 and 7)

test('a new post in a space where only moderators post tells every member by default, not its author', function () {
    flwPost($this->replier, $this->announce, 'News from the team');

    foreach ([$this->owner, $this->third, $this->author, $this->plain, $this->other] as $member) {
        expect(flwKinds($member))->toBe(['space_post']);
    }
    expect(flwRows($this->replier))->toHaveCount(0)
        ->and(flwRows($this->c['outsider']))->toHaveCount(0);

    $row = flwRows($this->plain)->first();
    expect($row->post_id)->not->toBeNull()
        ->and($row->actor_id)->toBe($this->replier->id)
        ->and($row->space_id)->toBe($this->announce->id)
        ->and($row->community_id)->toBe($this->community->id);
});

test('a new post in another discussion space tells nobody by default, and tells the members who chose Everything', function () {
    flwLevel($this->plain, $this->space, 'all');

    flwPost($this->author, $this->space, 'A new post');

    expect(flwKinds($this->plain))->toBe(['space_post'])
        ->and(flwRows($this->other))->toHaveCount(0)
        ->and(flwRows($this->third))->toHaveCount(0)
        ->and(flwRows($this->author))->toHaveCount(0);
});

test('the posts of a space bundle into one open row per member and space, with the live posts counted', function () {
    $first = flwPost($this->replier, $this->announce, 'First news');
    flwPost($this->replier, $this->announce, 'Second news');
    $third = flwPost($this->third, $this->announce, 'Third news');

    $rows = flwRows($this->plain);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->post_id)->toBe($first->id)
        ->and($rows[0]->actor_id)->toBe($this->third->id);

    $item = flwList($this->plain)[0];
    expect($item['items_count'])->toBe(3)
        ->and($item['title'])->toBe('First news')
        ->and($item['reason'])->toBe('space_everything')
        ->and($item['follow_reason'])->toBeNull()
        ->and(count($item['actors']))->toBe(2);

    // A deleted post is not counted, the author of the row stays
    app(DeletesPosts::class)->delete($this->third, $third);
    expect(flwList($this->plain)[0]['items_count'])->toBe(2);
});

test('the fan-out of a new post waits 5 minutes, a post by a moderator may skip the wait, and who may use it', function () {
    Queue::fake([FanOutNotifications::class]);

    flwPost($this->author, $this->space, 'Waits');
    flwPost($this->replier, $this->announce, 'Also waits');
    flwPost($this->replier, $this->announce, 'Does not wait', 'Now', ['notify_now' => true]);
    flwPost($this->owner, $this->announce, 'The owner does not wait', 'Now', ['notify_now' => true]);

    $jobs = Queue::pushed(FanOutNotifications::class);
    expect($jobs)->toHaveCount(4)
        ->and($jobs[0]->delay)->toBeInstanceOf(DateTimeInterface::class)
        ->and($jobs[0]->delay->getTimestamp())->toBeGreaterThanOrEqual(now()->addMinutes(4)->getTimestamp())->toBeLessThanOrEqual(now()->addMinutes(6)->getTimestamp())
        ->and($jobs[1]->delay)->not->toBeNull()
        ->and($jobs[2]->delay)->toBeNull()
        ->and($jobs[3]->delay)->toBeNull()
        ->and($jobs[0]->queue)->toBe('notifications');

    // The people who cannot moderate the space cannot skip the wait: the post is refused
    expect(fn () => flwPost($this->plain, $this->space, 'Too eager', 'Now', ['notify_now' => true]))->toThrow(AuthorizationException::class)
        ->and(Post::query()->where('title', 'Too eager')->exists())->toBeFalse();
    // A flag that says false is the default
    flwPost($this->plain, $this->space, 'Not eager', 'Now', ['notify_now' => false]);
    expect(Queue::pushed(FanOutNotifications::class))->toHaveCount(5);
});

test('a post is refused with a notify_now that is not a boolean', function () {
    expect(fn () => flwPost($this->replier, $this->announce, 'Bad flag', 'Now', ['notify_now' => 'soon']))->toThrow(ValidationException::class);
});

test('an edit inside the wait changes what the rows show and tells nobody, a deletion inside the wait tells nobody', function () {
    Queue::fake([FanOutNotifications::class]);
    $post = flwPost($this->replier, $this->announce, 'A first title');
    $gone = flwPost($this->replier, $this->announce, 'Deleted soon');
    expect(Notification::query()->count())->toBe(0);

    app(UpdatesPosts::class)->update($this->replier, $post, ['title' => 'The final title']);
    app(DeletesPosts::class)->delete($this->replier, $gone);
    expect(Notification::query()->count())->toBe(0);

    [$jobOfPost, $jobOfGone] = Queue::pushed(FanOutNotifications::class)->all();
    // The worker restores the post from the database: a deleted post is gone, and the job is dropped (deleteWhenMissingModels)
    expect($jobOfPost->deleteWhenMissingModels)->toBeTrue()
        ->and(fn () => unserialize(serialize($jobOfGone)))->toThrow(ModelNotFoundException::class);

    app()->call([unserialize(serialize($jobOfPost)), 'handle']);

    $item = flwList($this->plain)[0];
    expect($item['kind'])->toBe('space_post')->and($item['title'])->toBe('The final title')
        ->and(flwRows($this->plain))->toHaveCount(1);
});

test('a post that became a marker while the job waited tells nobody', function () {
    Queue::fake([FanOutNotifications::class]);
    $post = flwPost($this->replier, $this->announce, 'A marker soon');
    $post->forceFill(['deleted_at' => now(), 'author_id' => null])->save();

    app()->call([Queue::pushed(FanOutNotifications::class)->first(), 'handle']);

    expect(Notification::query()->where('kind', 'space_post')->count())->toBe(0);
});

test('a post in a space that is not a discussion space tells nobody at Everything', function () {
    flwLevel($this->plain, $this->chat, 'all');
    $post = Post::factory()->inSpace($this->chat)->create(['author_id' => $this->author->id]);

    app(Notifies::class)->fanOut($post);

    expect(flwRows($this->plain))->toHaveCount(0);
});

// space_chat (row 8)

test('a chat space tells only the members who chose Everything, never by default, with one open row updated', function () {
    flwLevel($this->plain, $this->chat, 'all');
    // A manager cannot make a chat space Everything by default
    expect(fn () => app(UpdatesSpaces::class)->update($this->owner, $this->chat, ['default_notification_level' => 'all']))->toThrow(ValidationException::class);

    $first = flwMessage($this->author, $this->chat, 'First');
    flwMessage($this->replier, $this->chat, 'Second');
    flwMessage($this->third, $this->chat, 'Third');

    $rows = flwRows($this->plain);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->kind)->toBe(KnowiiNotificationKind::SpaceChat)
        ->and($rows[0]->chat_message_id)->toBe($first->id)
        ->and($rows[0]->actor_id)->toBe($this->third->id)
        ->and(flwRows($this->other))->toHaveCount(0)
        ->and(flwRows($this->owner))->toHaveCount(0);
    expect(flwList($this->plain)[0]['items_count'])->toBe(3);

    // The member did not read it: the next message updates it. Read it: a new row
    $this->actingAs($this->plain)->putJson(flwPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $rows[0]->cuid]))->assertOk();
    flwMessage($this->author, $this->chat, 'Fourth');
    expect(flwRows($this->plain))->toHaveCount(2);
    flwMessage($this->author, $this->chat, 'Fifth');
    expect(flwRows($this->plain))->toHaveCount(2);
});

test('a reply in a thread of a chat space is not a new message of the space', function () {
    flwLevel($this->plain, $this->chat, 'all');
    $root = flwMessage($this->author, $this->chat, 'Root');
    $row = flwRows($this->plain)->first();
    $row->forceFill(['read_at' => now()])->save();

    flwMessage($this->replier, $this->chat, 'A reply', $root);

    expect(flwRows($this->plain))->toHaveCount(1);
});

// lesson_new (row 9)

test('a new lesson tells the members who started the course, not the others, not a muted member, not its author', function () {
    $first = flwLesson($this->replier, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);
    flwStart($this->other, $this->course, $first);
    flwStart($this->replier, $this->course, $first);
    flwLevel($this->other, $this->course, 'none');

    $second = flwLesson($this->replier, $this->course, 'Lesson 2');

    expect(flwKinds($this->plain))->toBe(['lesson_new'])
        ->and(flwRows($this->other))->toHaveCount(0)
        ->and(flwRows($this->author))->toHaveCount(0)
        ->and(flwRows($this->replier))->toHaveCount(0);
    $row = flwRows($this->plain)->first();
    expect($row->page_id)->toBe($second->id)->and($row->actor_id)->toBe($this->replier->id)->and($row->space_id)->toBe($this->course->id);
});

test('the lessons of a course bundle, a section and a page space tell nobody', function () {
    $first = flwLesson($this->replier, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);

    flwLesson($this->replier, $this->course, 'Lesson 2');
    flwLesson($this->third, $this->course, 'Lesson 3');
    app(CreatesPages::class)->create($this->replier, $this->course, ['title' => 'A section', 'kind' => 'section']);
    $pages = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Pages]);
    flwStart($this->plain, $pages, Page::factory()->inSpace($pages)->create());
    flwLesson($this->replier, $pages, 'A page, not a lesson');

    $rows = flwRows($this->plain);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->actor_id)->toBe($this->third->id);
    $item = flwList($this->plain)[0];
    expect($item['items_count'])->toBe(2)
        ->and($item['title'])->toBe('Lesson 2')
        ->and($item['reason'])->toBe('started_course')
        ->and($item['target']['page_slug'])->toBe(Page::query()->where('title', 'Lesson 2')->value('slug'));
});

test('a member who left the community gets no lesson, and a member who is not in the community is not told', function () {
    $first = flwLesson($this->replier, $this->course);
    flwStart($this->plain, $this->course, $first);
    $this->community->removeUser($this->plain);
    // The progress goes with the member (ADR 0034), and a progress row that stays would not make them a recipient
    LessonProgress::query()->create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'space_id' => $this->course->id, 'page_id' => $first->id, 'completed_at' => now()]);

    flwLesson($this->replier, $this->course, 'Lesson 2');

    expect(flwRows($this->plain))->toHaveCount(0);
});

// The levels of a space

test('the built-in default is Everything for a discussion space where only moderators post, Only what is for me elsewhere', function () {
    $levels = fn (Space $space) => SpaceNotificationLevels::levelOf($this->plain, $this->community, $space);

    expect($levels($this->announce))->toBe(KnowiiSpaceNotificationLevel::All)
        ->and($levels($this->space))->toBe(KnowiiSpaceNotificationLevel::Mine)
        ->and($levels($this->chat))->toBe(KnowiiSpaceNotificationLevel::Mine);
    foreach ([KnowiiSpaceType::Course, KnowiiSpaceType::Pages, KnowiiSpaceType::Resources, KnowiiSpaceType::Link] as $type) {
        expect($levels(Space::factory()->inGroup($this->group)->create(['type' => $type, 'url' => $type === KnowiiSpaceType::Link ? 'https://example.com' : null])))->toBe(KnowiiSpaceNotificationLevel::Mine);
    }
    // A chat space where only moderators write is not at Everything either
    expect($levels(Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators])))->toBe(KnowiiSpaceNotificationLevel::Mine);
});

test('a manager sets the default of a space, the choice of a member always wins', function () {
    app(UpdatesSpaces::class)->update($this->owner, $this->space, ['default_notification_level' => 'all']);
    expect($this->space->fresh()->default_notification_level)->toBe(KnowiiSpaceNotificationLevel::All)
        ->and(SpaceNotificationLevels::levelOf($this->plain, $this->community, $this->space->fresh()))->toBe(KnowiiSpaceNotificationLevel::All);

    flwLevel($this->other, $this->space, 'mine');
    flwPost($this->author, $this->space, 'For everyone');
    expect(flwKinds($this->plain))->toBe(['space_post'])
        ->and(flwRows($this->other))->toHaveCount(0);

    // The announcement space goes back to Only what is for me: the member who chose Everything still gets it
    app(UpdatesSpaces::class)->update($this->owner, $this->announce, ['default_notification_level' => 'mine']);
    flwLevel($this->other, $this->announce, 'all');
    flwPost($this->replier, $this->announce, 'Quiet news');
    expect(flwRows($this->plain, KnowiiNotificationKind::SpacePost)->where('space_id', $this->announce->id))->toHaveCount(0)
        ->and(flwRows($this->other, KnowiiNotificationKind::SpacePost)->where('space_id', $this->announce->id))->toHaveCount(1);

    // null gives the built-in default back
    app(UpdatesSpaces::class)->update($this->owner, $this->space, ['default_notification_level' => null]);
    expect($this->space->fresh()->default_notification_level)->toBeNull();
});

test('the default of a space: who may set it, and which values', function () {
    // A moderator without the right "manage spaces" cannot, a moderator with it can
    expect(fn () => app(UpdatesSpaces::class)->update($this->replier, $this->space, ['default_notification_level' => 'all']))->toThrow(AuthorizationException::class);
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_spaces' => true]]);
    app(UpdatesSpaces::class)->update($this->replier->fresh(), $this->space->fresh(), ['default_notification_level' => 'all']);
    expect($this->space->fresh()->default_notification_level)->toBe(KnowiiSpaceNotificationLevel::All);

    // A manager does not mute a space for the members, and Everything exists for a discussion space only
    foreach (['none', 'everything', 7] as $bad) {
        expect(fn () => app(UpdatesSpaces::class)->update($this->owner, $this->space, ['default_notification_level' => $bad]))->toThrow(ValidationException::class);
    }
    foreach ([$this->course, $this->chat] as $space) {
        expect(fn () => app(UpdatesSpaces::class)->update($this->owner, $space, ['default_notification_level' => 'all']))->toThrow(ValidationException::class);
    }
    app(UpdatesSpaces::class)->update($this->owner, $this->course, ['default_notification_level' => 'mine']);
    expect($this->course->fresh()->default_notification_level)->toBe(KnowiiSpaceNotificationLevel::Mine);
});

test('a chat space is never at Everything by default, even when the database says so', function () {
    DB::table('spaces')->where('id', $this->chat->id)->update(['default_notification_level' => 'all']);

    expect(SpaceNotificationLevels::defaultOf($this->chat->fresh()))->toBe(KnowiiSpaceNotificationLevel::Mine);
    flwMessage($this->author, $this->chat);
    expect(Notification::query()->count())->toBe(0);
});

test('an owner and an admin who see a groups space only because of their role get Only what is for me there', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->plain->id, $this->replier->id]);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $groups->accessGroups()->attach($insiders);

    flwPost($this->replier, $groups, 'Inside news');

    // The member of the group gets the default, Everything. The owner and the admin see the space by their role: Only what is for me.
    // The member outside of the groups does not see the space
    expect(flwKinds($this->plain))->toBe(['space_post'])
        ->and(flwRows($this->owner))->toHaveCount(0)
        ->and(flwRows($this->third))->toHaveCount(0)
        ->and(flwRows($this->author))->toHaveCount(0)
        ->and(SpaceNotificationLevels::levelOf($this->third, $this->community, $groups->fresh()))->toBe(KnowiiSpaceNotificationLevel::Mine);

    // The same admin in an access group of the space gets the default
    $insiders->members()->attach($this->third);
    expect(SpaceNotificationLevels::levelOf($this->third->fresh(), $this->community, $groups->fresh()))->toBe(KnowiiSpaceNotificationLevel::All);
    // And their own choice wins
    flwLevel($this->owner, $groups, 'all');
    flwPost($this->replier, $groups, 'More inside news');
    expect(flwRows($this->owner))->toHaveCount(1);
});

test('a member chooses a level, goes back to the default, and the choice forgets with it', function () {
    $levels = app(ChoosesSpaceLevels::class);

    expect($levels->show($this->plain, $this->announce))->toBe(['space_cuid' => $this->announce->cuid, 'level' => 'all', 'chosen' => false, 'default' => 'all']);
    expect($levels->set($this->plain, $this->announce, ['level' => 'none']))->toBe(['space_cuid' => $this->announce->cuid, 'level' => 'none', 'chosen' => true, 'default' => 'all']);
    expect($levels->set($this->plain, $this->announce, ['level' => 'mine'])['level'])->toBe('mine')
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->plain->id)->count())->toBe(1);
    expect($levels->reset($this->plain, $this->announce)['chosen'])->toBeFalse()
        ->and(NotificationSpaceLevel::query()->count())->toBe(0);
});

test('a level is refused for a link space, Everything is refused outside discussion and chat spaces, and a stranger has none', function () {
    $link = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Link, 'url' => 'https://example.com']);

    expect(fn () => flwLevel($this->plain, $link, 'mine'))->toThrow(BusinessException::class)
        ->and(fn () => flwLevel($this->plain, $this->course, 'all'))->toThrow(BusinessException::class)
        ->and(fn () => flwLevel($this->plain, $this->space, 'sometimes'))->toThrow(ValidationException::class)
        ->and(fn () => flwLevel($this->c['outsider'], $this->space, 'mine'))->toThrow(AuthorizationException::class);
    flwLevel($this->plain, $this->course, 'none');
    expect(NotificationSpaceLevel::query()->count())->toBe(1);
});

// Mute is full

test('a muted thread gives nothing, not a reply, not a mention, not a comment line, and deletes the open rows of the thread', function () {
    $post = flwPost($this->author, $this->space);
    $comment = flwComment($this->replier, $post, 'Hello @'.$this->plain->username);
    flwComment($this->plain, $post);
    expect(flwKinds($this->author))->toBe(['post_comment'])
        ->and(flwKinds($this->plain))->toBe(['mention']);
    $elsewhere = flwPost($this->author, $this->space, 'Another post', 'Another body');
    flwComment($this->replier, $elsewhere);
    expect(flwRows($this->author))->toHaveCount(2);

    // The author mutes the thread: the open row of the thread goes, the row of the other thread stays
    app(FollowsThreads::class)->mute($this->author, $post);
    expect(flwRows($this->author))->toHaveCount(1)
        ->and(flwRows($this->author)->first()->comment_id)->not->toBe($comment->id)
        ->and(flwFollow($this->author, $post)->state)->toBe(KnowiiFollowState::Muted);

    // A reply, a comment and a mention of the muted thread give nothing to the member who muted it
    flwComment($this->third, $post, 'A comment for @'.$this->author->username);
    flwComment($this->replier, $post, 'A reply', $comment);
    expect(flwRows($this->author))->toHaveCount(1);

    // The member who did not mute still gets their rows
    expect(flwRows($this->plain)->count())->toBeGreaterThan(0);
});

test('muting a thread deletes the mention rows and the follower rows of the thread, never the rows of a space', function () {
    // The member is mentioned in the post (a member who gets a mention gets no row of the space for that post)
    $post = flwPost($this->replier, $this->announce, 'Team news', 'Hello @'.$this->plain->username);
    flwComment($this->plain, $post);
    flwComment($this->third, $post);
    flwPost($this->replier, $this->announce, 'More team news');
    expect(flwKinds($this->plain))->toBe(['mention', 'thread_comment', 'space_post']);

    app(FollowsThreads::class)->mute($this->plain, $post);

    // The row of the space says "new posts in the space": it is not about one thread
    expect(flwKinds($this->plain))->toBe(['space_post']);
});

test('a muted chat thread gives nothing: not a reply of the thread, not a mention in it', function () {
    $root = flwMessage($this->author, $this->chat, 'Root');
    flwMessage($this->replier, $this->chat, 'First reply', $root);
    expect(flwKinds($this->author))->toBe(['thread_reply']);

    app(FollowsThreads::class)->mute($this->author, $root);
    expect(flwRows($this->author))->toHaveCount(0);

    flwMessage($this->plain, $this->chat, 'Reply for @'.$this->author->username, $root);
    expect(flwRows($this->author))->toHaveCount(0);
});

test('a muted space gives nothing at any level, and deletes the open rows of that space, not of another', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->replier, $post);
    $elsewhere = Space::factory()->inGroup($this->group)->create();
    $other = flwPost($this->author, $elsewhere, 'Other space', 'Elsewhere');
    flwComment($this->replier, $other);
    flwLevel($this->plain, $this->space, 'none');
    expect(flwRows($this->author))->toHaveCount(2);

    flwLevel($this->author, $this->space, 'none');

    expect(flwRows($this->author))->toHaveCount(1)
        ->and(flwRows($this->author)->first()->space_id)->toBe($elsewhere->id);

    // Nothing from the muted space reaches the member: a comment, a reply, a mention, a post at Everything
    $post2 = flwPost($this->replier, $this->space, 'Mention', 'Hello @'.$this->author->username.' and @'.$this->plain->username);
    flwComment($this->third, $post, 'Comment on the post of the muted member');
    flwComment($this->third, $post2, 'Hello @'.$this->plain->username);
    flwMessage($this->third, $this->chat, 'A message for @'.$this->author->username);
    expect(flwRows($this->author))->toHaveCount(2)
        ->and(flwRows($this->plain)->where('space_id', $this->space->id))->toHaveCount(0)
        // The chat space is another space: the mention reaches the member
        ->and(flwRows($this->author)->where('space_id', $this->chat->id))->toHaveCount(1);
});

test('muting the announcement space gives no space_post row, also to the member who has the default', function () {
    flwLevel($this->plain, $this->announce, 'none');

    flwPost($this->replier, $this->announce, 'News');

    expect(flwRows($this->plain))->toHaveCount(0)
        ->and(flwRows($this->other))->toHaveCount(1);
});

test('a muted space gives no lesson row, and a member who muted a thread is not a follower', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    app(FollowsThreads::class)->mute($this->plain, $post);

    flwComment($this->replier, $post);

    expect(flwRows($this->plain, KnowiiNotificationKind::ThreadComment))->toHaveCount(0);
});

// Access and blocks, for every new kind

test('a member who blocked the author gets no row of any new kind, and the others do', function () {
    $this->plain->blockedUsers()->attach($this->replier);
    flwLevel($this->plain, $this->chat, 'all');
    flwLevel($this->plain, $this->space, 'all');

    $post = flwPost($this->author, $this->space, 'A post of the author');
    flwComment($this->other, $post);
    $message = flwMessage($this->author, $this->chat, 'First');
    flwMessage($this->third, $this->chat, 'Reply', $message);
    flwMessage($this->plain, $this->chat, 'Reply of plain', $message);
    $first = flwLesson($this->third, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);
    flwStart($this->other, $this->course, $first);
    $before = flwRows($this->plain)->count();

    // Everything that the blocked member does: a comment in a followed thread, a post at Everything, a message, a reply in a followed thread, a lesson
    flwComment($this->replier, $post, 'Blocked comment');
    flwPost($this->replier, $this->space, 'Blocked post');
    flwMessage($this->replier, $this->chat, 'Blocked message');
    flwMessage($this->replier, $this->chat, 'Blocked reply', $message);
    flwLesson($this->replier, $this->course, 'Blocked lesson');
    expect(flwRows($this->plain)->count())->toBe($before);

    // The member who did not block gets them
    expect(flwKinds($this->other))->toContain('thread_comment')->toContain('lesson_new');
});

test('a member who cannot view the space gets no row of any new kind', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->replier->id, $this->plain->id]);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $groups->accessGroups()->attach($insiders);
    // The member chose Everything and follows a thread of the space, then lost the access
    $post = flwPost($this->replier, $groups, 'A group post');
    flwComment($this->plain, $post);
    flwLevel($this->plain, $groups, 'all');
    $insiders->members()->detach($this->plain);

    flwComment($this->replier, $post, 'A comment');
    flwPost($this->replier, $groups, 'Another group post');

    expect(flwRows($this->plain))->toHaveCount(0);
    // A member of the groups gets them
    $insiders->members()->attach($this->plain);
    flwComment($this->replier, $post, 'Another comment');
    expect(flwKinds($this->plain))->toBe(['thread_comment']);
});

test('a visitor and a person outside the community are never told, a public space included (ADR 0044)', function () {
    $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    // An outsider cannot follow nor choose: they are not members
    $post = flwPost($this->replier, $public, 'A public announcement');
    expect(fn () => app(FollowsThreads::class)->follow($this->c['outsider'], $post))->toThrow(AuthorizationException::class)
        ->and(fn () => flwLevel($this->c['outsider'], $public, 'all'))->toThrow(AuthorizationException::class)
        ->and(flwRows($this->c['outsider']))->toHaveCount(0);

    // A choice of a member who left the community and a row that stayed do not tell the person
    NotificationSpaceLevel::query()->create(['user_id' => $this->c['outsider']->id, 'space_id' => $public->id, 'level' => 'all']);
    NotificationFollow::query()->create(['user_id' => $this->c['outsider']->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => 'following', 'reason' => 'manual']);
    flwPost($this->replier, $public, 'Another public announcement');
    flwComment($this->replier, $post);
    expect(flwRows($this->c['outsider']))->toHaveCount(0);
});

// The fan-out

test('the queries of the fan-out are the same for 10 recipients and for 200', function () {
    Event::fake([NotificationsChanged::class]);
    Queue::fake([FanOutNotifications::class]);
    $users = User::factory()->count(200)->create();
    $this->community->users()->attach($users->mapWithKeys(fn (User $user) => [$user->id => ['role' => 'member']])->all());
    $post = Notify::silenced(fn () => flwPost($this->replier, $this->announce, 'For everyone'));
    $countQueries = function (callable $run): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // One chunk of 10 recipients (the owner, the admin, the member, 2 plain members, 5 others: the chunk is full and queues the next one)
    $ten = $countQueries(fn () => app(Notifies::class)->fanOut($post, null, 0, 10));
    expect(Notification::query()->where('kind', 'space_post')->count())->toBeLessThanOrEqual(10)->toBeGreaterThan(5);
    expect(Queue::pushed(FanOutNotifications::class))->toHaveCount(1);
    Notification::query()->delete();

    // One chunk of 500: every member of the community (205 recipients) in the same number of queries
    $all = $countQueries(fn () => app(Notifies::class)->fanOut($post, null, 0, 500));
    expect(Notification::query()->where('kind', 'space_post')->count())->toBe(205)
        ->and($all)->toBe($ten);
});

test('the queries of the fan-out grow by chunk, not by member', function () {
    Event::fake([NotificationsChanged::class]);
    Queue::fake([FanOutNotifications::class]);
    $users = User::factory()->count(195)->create();
    $this->community->users()->attach($users->mapWithKeys(fn (User $user) => [$user->id => ['role' => 'member']])->all());
    $post = Notify::silenced(fn () => flwPost($this->replier, $this->announce, 'For everyone'));
    $countQueries = function (callable $run): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // The chunks of 50, run one after the other as a worker does: 4 full chunks and an empty one at the end
    $total = 0;
    $after = 0;
    $chunks = 0;
    do {
        Queue::fake([FanOutNotifications::class]);
        $total += $countQueries(fn () => app(Notifies::class)->fanOut($post, null, $after, 50));
        $job = Queue::pushed(FanOutNotifications::class)->first();
        $after = $job?->after ?? 0;
        $chunks++;
    } while ($job !== null);
    expect(Notification::query()->where('kind', 'space_post')->count())->toBe(200)
        ->and($chunks)->toBe(5);
    Notification::query()->delete();

    // One chunk of 500 for the same 200 members
    $inOne = $countQueries(fn () => app(Notifies::class)->fanOut($post, null, 0, 500));

    // About one chunk's worth of queries per chunk: far less than one query per member, and not constant either
    expect($total)->toBeGreaterThan($inOne * 3)
        ->and($total)->toBeLessThan($inOne * 6)
        ->and($total)->toBeLessThan(200);
});

test('the fan-out of a groups space reads the roles and the groups once per chunk: the same queries for 10 and for 200 members', function () {
    Event::fake([NotificationsChanged::class]);
    Queue::fake([FanOutNotifications::class]);
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $groups->accessGroups()->attach($insiders);
    $insiders->members()->attach($this->replier->id);
    $post = Notify::silenced(fn () => flwPost($this->replier, $groups, 'Inside news'));
    $countQueries = function (callable $run): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $recipients = fn () => Notification::query()->where('kind', 'space_post')->count();

    // The owner and the admin view the space by their role (Only what is for me): nobody at Everything by default but the group
    $few = User::factory()->count(10)->create();
    $this->community->users()->attach($few->mapWithKeys(fn (User $user) => [$user->id => ['role' => 'member']])->all());
    $insiders->members()->attach($few->modelKeys());
    $withTen = $countQueries(fn () => app(Notifies::class)->fanOut($post));
    expect($recipients())->toBe(10);
    Notification::query()->delete();

    $many = User::factory()->count(190)->create();
    $this->community->users()->attach($many->mapWithKeys(fn (User $user) => [$user->id => ['role' => 'member']])->all());
    $insiders->members()->attach($many->modelKeys());
    $withTwoHundred = $countQueries(fn () => app(Notifies::class)->fanOut($post));

    expect($recipients())->toBe(200)->and($withTwoHundred)->toBe($withTen);
});

test('the roles of the members are known for the length of one check only', function () {
    $roles = CommunityRoles::class;
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $groups->accessGroups()->attach($insiders);

    $viewers = NotificationAccess::viewers(User::query()->with(['communities', 'accessGroups'])->whereIn('id', [$this->owner->id, $this->third->id, $this->plain->id])->get(), $this->community, $groups->load(['accessGroups', 'spaceGroup.accessGroups']));

    // The owner and the admin by their role, not the plain member
    expect($viewers->modelKeys())->toEqualCanonicalizing([$this->owner->id, $this->third->id]);
    // The knowledge is gone with the check: the rule reads the database again
    $this->community->users()->updateExistingPivot($this->third->id, ['role' => 'member']);
    expect($roles::isOwnerOrAdmin($this->third->fresh(), $this->community))->toBeFalse();
});

test('the chunks of a fan-out are jobs on the queue notifications, each one with the cursor of the last member', function () {
    $users = User::factory()->count(30)->create();
    $this->community->users()->attach($users->mapWithKeys(fn (User $user) => [$user->id => ['role' => 'member']])->all());
    $post = Notify::silenced(fn () => flwPost($this->replier, $this->announce, 'For everyone'));
    Queue::fake([FanOutNotifications::class]);

    app(Notifies::class)->fanOut($post, null, 0, 10);

    $job = Queue::pushed(FanOutNotifications::class)->first();
    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job->queue)->toBe('notifications')
        ->and($job->size)->toBe(10)
        ->and($job->after)->toBeGreaterThan(0)
        ->and($job->excluded)->toBe([]);
    // The last chunk (the members after the cursor, fewer than the size) queues nothing
    Queue::fake([FanOutNotifications::class]);
    app(Notifies::class)->fanOut($post, [], PHP_INT_MAX - 1, 10);
    Queue::assertNothingPushed();
});

test('a retry of a chunk is harmless: no second row, no second event', function () {
    flwLevel($this->plain, $this->space, 'all');
    $post = flwPost($this->author, $this->space, 'A post');
    expect(flwRows($this->plain))->toHaveCount(1);
    $row = flwRows($this->plain)->first();
    // The member read it, then the job runs again: the item has its row already
    $row->forceFill(['read_at' => now()])->save();
    Event::fake([NotificationsChanged::class]);

    app(Notifies::class)->fanOut($post);
    app(Notifies::class)->fanOut($post);

    expect(flwRows($this->plain))->toHaveCount(1);
    Event::assertNotDispatched(NotificationsChanged::class);
});

test('one event per member and community, queued on broadcasts, sent for the new rows and the bundled ones', function () {
    Event::fake([NotificationsChanged::class]);

    flwPost($this->replier, $this->announce, 'First news');
    flwPost($this->replier, $this->announce, 'Second news');

    Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event) => $event->userCuid === $this->plain->cuid && $event->communityCuid === $this->community->cuid && $event->notificationCuid !== null);
    Event::assertDispatchedTimes(NotificationsChanged::class, 10);
    expect((new NotificationsChanged('u', 'c'))->broadcastQueue())->toBe('broadcasts');
});

test('the two jobs of a new item run on the queue notifications, a comment on a post gives a fan-out, a comment on a resource does not', function () {
    Queue::fake();
    $post = flwPost($this->author, $this->space);
    Queue::assertPushedOn('notifications', WriteNotifications::class);
    Queue::assertPushedOn('notifications', FanOutNotifications::class);

    Queue::fake();
    flwComment($this->replier, $post);
    Queue::assertPushed(FanOutNotifications::class, 1);
    Queue::assertPushed(WriteNotifications::class, 1);
});

// The list

test('the list has a level filter: 1 is Needs you (the default), 2 is Following', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);
    flwPost($this->replier, $this->announce, 'News');

    $needsYou = flwList($this->author, 'level=1');
    $following = flwList($this->author, 'level=2');
    expect(array_column($needsYou, 'kind'))->toBe(['post_comment'])
        ->and(array_column($following, 'kind'))->toBe(['space_post'])
        ->and(array_column(flwList($this->author, 'limit=20'), 'kind'))->toBe(['post_comment'])
        ->and(array_column($following, 'level'))->toBe([2]);

    $this->actingAs($this->author)->getJson(flwPath(ApiRoutes::NOTIFICATIONS).'?level=3')->assertStatus(400);
    $this->actingAs($this->author)->getJson(flwPath(ApiRoutes::NOTIFICATIONS).'?level=0')->assertStatus(400);
});

test('a row of Following older than 14 days is never listed, and has no Older, the row of Needs you folds', function () {
    flwPost($this->replier, $this->announce, 'News');
    $row = flwRows($this->plain)->first();
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    $old = flwRows($this->author, KnowiiNotificationKind::PostComment)->first();

    Notification::query()->whereKey($row->id)->toBase()->update(['updated_at' => now()->subDays(15)]);
    Notification::query()->whereKey($old->id)->toBase()->update(['updated_at' => now()->subDays(15)]);

    expect(flwList($this->plain, 'level=2'))->toBe([])
        ->and(flwList($this->plain, 'level=2&older=true'))->toBe([])
        ->and(flwList($this->author, 'level=1'))->toBe([])
        ->and(array_column(flwList($this->author, 'level=1&older=true'), 'cuid'))->toBe([$old->cuid]);
    // The row stays in the table until the retention removes it
    expect(Notification::query()->whereKey($row->id)->exists())->toBeTrue();
});

test('the list holds the reason of each kind, and the reason of the follow for a thread', function () {
    $post = flwPost($this->author, $this->space, 'Reasons', 'Hello @'.$this->plain->username);
    $comment = flwComment($this->replier, $post, 'Hello @'.$this->other->username);
    flwComment($this->third, $post, 'A reply', $comment);
    $first = flwLesson($this->replier, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);
    flwLesson($this->replier, $this->course, 'Lesson 2');
    flwPost($this->replier, $this->announce, 'News');

    $reasons = function (User $user, string $level) {
        $found = collect(flwList($user, "level=$level"))->mapWithKeys(fn (array $item) => [$item['kind'] => [$item['reason'], $item['follow_reason']]])->all();
        ksort($found);

        return $found;
    };

    expect($reasons($this->author, '1'))->toBe(['post_comment' => ['wrote_post', null]])
        ->and($reasons($this->author, '2'))->toBe(['space_post' => ['space_everything', null], 'thread_comment' => ['follows_thread', 'wrote']])
        ->and($reasons($this->replier, '1'))->toBe(['comment_reply' => ['wrote_comment', null]])
        ->and($reasons($this->plain, '1'))->toBe(['mention' => ['mentioned', null]])
        ->and($reasons($this->plain, '2'))->toBe(['lesson_new' => ['started_course', null], 'space_post' => ['space_everything', null]]);

    // The member chose to follow by hand: the follow reason is manual
    app(FollowsThreads::class)->follow($this->plain, $post);
    flwComment($this->third, $post, 'More');
    expect($reasons($this->plain, '2')['thread_comment'])->toBe(['follows_thread', 'manual']);
});

test('the count of the bell never counts level 2', function () {
    flwPost($this->replier, $this->announce, 'News');
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);
    expect(flwRows($this->plain, KnowiiNotificationKind::ThreadComment)->first()->seen_at)->toBeNull();

    $count = fn (User $user) => $this->actingAs($user->fresh())->getJson(flwPath(ApiRoutes::NOTIFICATIONS_COUNT))->assertOk()->json('data.count');

    // plain has 2 unseen rows of level 2, author has 1 of level 1 and 1 of level 2
    expect($count($this->plain))->toBe(0)
        ->and($count($this->author))->toBe(1)
        ->and(app(ListsNotifications::class)->unseenCount($this->plain))->toBe(0);
});

test('a row of Following whose recipient cannot view the space any more is deleted by the list', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->replier->id, $this->plain->id]);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $groups->accessGroups()->attach($insiders);
    flwPost($this->replier, $groups, 'Inside news');
    expect(flwRows($this->plain))->toHaveCount(1);

    $insiders->members()->detach($this->plain);

    expect(flwList($this->plain))->toBe([])
        ->and(flwRows($this->plain))->toHaveCount(0);
});

// "I am caught up"

test('I am caught up marks the open rows of Following read and never touches Needs you', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);
    flwPost($this->replier, $this->announce, 'News');
    flwComment($this->author, flwPost($this->plain, $this->space, 'Plain post', 'Body'));
    expect(flwRows($this->plain))->toHaveCount(3);
    $before = flwRows($this->plain)->where('kind', KnowiiNotificationKind::PostComment);
    expect($before)->toHaveCount(1);

    $this->actingAs($this->plain)->postJson(flwPath(ApiRoutes::NOTIFICATIONS_CAUGHT_UP))->assertOk()->assertJsonPath('data.marked', 2);

    $rows = flwRows($this->plain);
    expect($rows->where('kind', KnowiiNotificationKind::PostComment)->first()->read_at)->toBeNull()
        ->and($rows->where('kind', KnowiiNotificationKind::ThreadComment)->first()->read_at)->not->toBeNull()
        ->and($rows->where('kind', KnowiiNotificationKind::ThreadComment)->first()->seen_at)->not->toBeNull()
        ->and($rows->where('kind', KnowiiNotificationKind::SpacePost)->first()->read_at)->not->toBeNull()
        ->and(flwList($this->plain, 'level=2'))->toBe([])
        ->and(array_column(flwList($this->plain, 'level=1'), 'kind'))->toBe(['post_comment']);
    // Nothing left: nothing marked, and the rows of the other members are not touched
    $this->actingAs($this->plain)->postJson(flwPath(ApiRoutes::NOTIFICATIONS_CAUGHT_UP))->assertOk()->assertJsonPath('data.marked', 0);
    expect(flwRows($this->other)->first()->read_at)->toBeNull();
});

test('I am caught up can keep one community, and sends the events', function () {
    $other = Community::factory()->create(['owner_id' => $this->owner->id]);
    $otherGroup = SpaceGroup::factory()->create(['community_id' => $other->id]);
    $otherAnnounce = Space::factory()->inGroup($otherGroup)->create(['posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $other->users()->attach([$this->plain->id => ['role' => 'member'], $this->replier->id => ['role' => 'moderator']]);
    flwPost($this->replier, $this->announce, 'News here');
    flwPost($this->replier, $otherAnnounce, 'News there');
    expect(flwRows($this->plain))->toHaveCount(2);

    Event::fake([NotificationsChanged::class]);
    $this->actingAs($this->plain)->postJson(flwPath(ApiRoutes::NOTIFICATIONS_CAUGHT_UP), ['community' => $other->cuid])->assertOk()->assertJsonPath('data.marked', 1);

    expect(flwRows($this->plain)->where('community_id', $other->id)->first()->read_at)->not->toBeNull()
        ->and(flwRows($this->plain)->where('community_id', $this->community->id)->first()->read_at)->toBeNull();
    Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event) => $event->userCuid === $this->plain->cuid && $event->communityCuid === $other->cuid);

    $this->actingAs($this->plain)->postJson(flwPath(ApiRoutes::NOTIFICATIONS_CAUGHT_UP), ['community' => ['not', 'a cuid']])->assertStatus(400);
});

// The API: the follow of a thread

test('the follow routes of a post: state, follow, stop following, mute', function () {
    $post = flwPost($this->author, $this->space);
    $follow = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post);
    $this->actingAs($this->plain);

    $this->getJson($follow)->assertOk()->assertJsonPath('data.state', null)->assertJsonPath('data.reason', null);
    $this->putJson($follow)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'manual');
    $this->getJson($follow)->assertOk()->assertJsonPath('data.state', 'following');
    $this->deleteJson($follow)->assertOk()->assertJsonPath('data.state', 'stopped')->assertJsonPath('data.reason', 'manual');
    $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
    $this->putJson($follow)->assertOk()->assertJsonPath('data.state', 'following');

    // The author follows their post since they wrote it
    $this->actingAs($this->author)->getJson($follow)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'wrote');
});

// The undo of a mute (#1509)

test('the undo of a mute gives back the exact earlier state: no row, each reason, stopped', function () {
    $post = flwPost($this->author, $this->space);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post);

    // No row before: no row after
    $this->actingAs($this->plain);
    $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', null)->assertJsonPath('data.reason', null);
    expect(flwFollow($this->plain, $post))->toBeNull();

    // Followed with each reason: followed again with the same reason
    foreach ([KnowiiFollowReason::Wrote, KnowiiFollowReason::Replied, KnowiiFollowReason::Manual] as $reason) {
        NotificationFollow::query()->where('user_id', $this->plain->id)->where('post_id', $post->id)->delete();
        NotificationFollow::create(['user_id' => $this->plain->id, 'community_id' => $this->community->id, 'post_id' => $post->id, 'state' => KnowiiFollowState::Following, 'reason' => $reason]);
        $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
        $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', $reason->value);
        $row = flwFollow($this->plain, $post);
        expect($row->state_before_mute)->toBeNull()->and($row->reason_before_mute)->toBeNull();
    }

    // Stopped: stopped again
    app(FollowsThreads::class)->stopFollowing($this->plain, $post);
    $this->putJson($mute)->assertOk();
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'stopped')->assertJsonPath('data.reason', 'manual');
});

test('a second mute keeps what the first one remembered', function () {
    $post = flwPost($this->author, $this->space);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post);

    // The author wrote the post: followed with the reason "wrote"
    $this->actingAs($this->author);
    $this->putJson($mute)->assertOk();
    $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'wrote');
});

test('a mute, then a follow, then an unmute: the follow stays and the memory is gone', function () {
    $post = flwPost($this->author, $this->space);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post);
    $follow = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post);
    $this->actingAs($this->author);

    $this->putJson($mute)->assertOk();
    $this->putJson($follow)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'manual');
    expect(flwFollow($this->author, $post)->state_before_mute)->toBeNull();
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'manual');

    // The same after "Stop following"
    $this->putJson($mute)->assertOk();
    $this->deleteJson($follow)->assertOk()->assertJsonPath('data.state', 'stopped');
    expect(flwFollow($this->author, $post)->reason_before_mute)->toBeNull();
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'stopped');
});

test('the undo of a thread that is not muted changes nothing', function () {
    $post = flwPost($this->author, $this->space);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post);
    $this->actingAs($this->plain);

    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', null);
    expect(flwFollow($this->plain, $post))->toBeNull();

    $this->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post))->assertOk();
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'manual');
});

test('the undo of a mute does not bring the deleted notifications back, and works for a chat thread', function () {
    $post = flwPost($this->author, $this->space);
    flwComment($this->replier, $post, 'A comment');
    expect(flwRows($this->author))->toHaveCount(1);

    $this->actingAs($this->author);
    $this->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post))->assertOk();
    expect(flwRows($this->author))->toHaveCount(0);
    $this->deleteJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post))->assertOk();
    expect(flwRows($this->author))->toHaveCount(0);

    $root = flwMessage($this->author, $this->chat, 'Root');
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_MUTE, $this->community, $this->chat, $root);
    $this->actingAs($this->plain);
    $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
    $this->deleteJson($mute)->assertOk()->assertJsonPath('data.state', null);
});

test('the follow routes of a chat thread: state, follow, stop following, mute, first-level messages only', function () {
    $root = flwMessage($this->author, $this->chat, 'Root');
    $reply = flwMessage($this->replier, $this->chat, 'Reply', $root);
    $follow = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $root);
    $mute = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_MUTE, $this->community, $this->chat, $root);
    $this->actingAs($this->plain);

    $this->getJson($follow)->assertOk()->assertJsonPath('data.state', null);
    $this->putJson($follow)->assertOk()->assertJsonPath('data.state', 'following');
    $this->deleteJson($follow)->assertOk()->assertJsonPath('data.state', 'stopped');
    $this->putJson($mute)->assertOk()->assertJsonPath('data.state', 'muted');
    $this->actingAs($this->replier)->getJson($follow)->assertOk()->assertJsonPath('data.state', 'following')->assertJsonPath('data.reason', 'replied');

    // A reply has no thread of its own
    $this->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $reply))->assertStatus(422);
    $this->getJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $reply))->assertStatus(422);
});

test('the follow routes refuse a person who is not a member, a member who cannot view the space, a marker, and a post of another space', function () {
    $post = flwPost($this->author, $this->space);
    $follow = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post);

    // Not a member: 403, also in a public space
    $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    $publicPost = flwPost($this->author, $public, 'Public', 'Public body');
    $publicFollow = flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $public, $publicPost);
    foreach ([$follow, $publicFollow] as $path) {
        $this->actingAs($this->c['outsider'])->getJson($path)->assertForbidden();
        $this->actingAs($this->c['outsider'])->putJson($path)->assertForbidden();
        $this->actingAs($this->c['outsider'])->deleteJson($path)->assertForbidden();
    }
    $this->actingAs($this->c['outsider'])->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $public, $publicPost))->assertForbidden();
    $this->actingAs($this->c['outsider'])->deleteJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $public, $publicPost))->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->putJson($follow)->assertUnauthorized();

    // A groups space that the member cannot view
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $groupPost = Post::factory()->inSpace($groups)->create(['author_id' => $this->owner->id]);
    $this->actingAs($this->plain)->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $groups, $groupPost))->assertForbidden();
    $this->actingAs($this->plain)->deleteJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $groups, $groupPost))->assertForbidden();

    // A marker has no follow (ADR 0043), a post of another space is a 404 (scoped bindings)
    $marker = Post::factory()->inSpace($this->space)->create(['author_id' => null, 'deleted_at' => now()]);
    $this->actingAs($this->plain)->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $marker))->assertStatus(422);
    $this->actingAs($this->plain)->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->announce, $post))->assertNotFound();
    // A post of a space that is not a discussion space, a message of a space that is not a chat space
    $chatPost = Post::factory()->inSpace($this->chat)->create(['author_id' => $this->owner->id]);
    $this->actingAs($this->plain)->putJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->chat, $chatPost))->assertStatus(422);
});

test('the routes of the follows and the levels need the abilities of the notifications', function () {
    $post = flwPost($this->author, $this->space);
    $root = flwMessage($this->author, $this->chat, 'Root');
    $routes = [
        ['get', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post), 'read'],
        ['put', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post), 'write'],
        ['delete', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $this->community, $this->space, $post), 'write'],
        ['put', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post), 'write'],
        ['delete', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, $this->community, $this->space, $post), 'write'],
        ['get', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $root), 'read'],
        ['put', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $root), 'write'],
        ['delete', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, $this->community, $this->chat, $root), 'write'],
        ['put', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_MUTE, $this->community, $this->chat, $root), 'write'],
        ['delete', flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_MUTE, $this->community, $this->chat, $root), 'write'],
        ['get', flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->space), 'read'],
        ['put', flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->space), 'write'],
        ['delete', flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->space), 'write'],
        ['get', flwPath(ApiRoutes::COMMUNITY_NOTIFICATION_LEVELS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]), 'read'],
        ['post', flwPath(ApiRoutes::NOTIFICATIONS_CAUGHT_UP), 'write'],
    ];

    foreach ($routes as [$method, $path, $needs]) {
        $other = $needs === 'read' ? 'write' : 'read';
        $body = $path === flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->space) && $method === 'put' ? ['level' => 'mine'] : [];

        // The other ability, and the abilities of the content, are not enough
        Sanctum::actingAs($this->plain, ["notifications:$other", 'posts:read', 'posts:write', 'chat:read', 'chat:write', 'communities:read', 'communities:write']);
        $this->json($method, $path, $body)->assertForbidden();
        // The right ability is
        Sanctum::actingAs($this->plain, ["notifications:$needs"]);
        $this->json($method, $path, $body)->assertSuccessful();
    }
});

// The API: the levels

test('the level routes of a space: read, choose, go back to the default', function () {
    $path = flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->announce);
    $this->actingAs($this->plain);

    $this->getJson($path)->assertOk()->assertJsonPath('data.level', 'all')->assertJsonPath('data.chosen', false)->assertJsonPath('data.default', 'all')->assertJsonPath('data.space_cuid', $this->announce->cuid);
    $this->putJson($path, ['level' => 'none'])->assertOk()->assertJsonPath('data.level', 'none')->assertJsonPath('data.chosen', true);
    $this->putJson($path, ['level' => 'mine'])->assertOk()->assertJsonPath('data.level', 'mine');
    $this->deleteJson($path)->assertOk()->assertJsonPath('data.level', 'all')->assertJsonPath('data.chosen', false);

    $this->putJson($path, ['level' => 'sometimes'])->assertStatus(400);
    $this->putJson($path, [])->assertStatus(400);
    $this->putJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $this->course), ['level' => 'all'])->assertStatus(422);
    $this->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Link, 'url' => 'https://example.com'])))->assertStatus(422);
    $this->actingAs($this->c['outsider'])->getJson($path)->assertForbidden();
    $this->actingAs($this->c['outsider'])->putJson($path, ['level' => 'mine'])->assertForbidden();
});

test('the level of a space that the member cannot view is a 403, and a space of another community is a 404', function () {
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $other = Community::factory()->create(['owner_id' => $this->owner->id]);
    $otherSpace = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $other->id]))->create();
    $this->actingAs($this->plain);

    $this->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $groups))->assertForbidden();
    $this->putJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $groups), ['level' => 'mine'])->assertForbidden();
    $this->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, $this->community, $otherSpace))->assertNotFound();
});

test('the levels of the member for a whole community come in one call, in the order of the navigation, with the defaults', function () {
    $link = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Link, 'url' => 'https://example.com']);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    flwLevel($this->plain, $this->space, 'none');
    flwLevel($this->plain, $this->chat, 'all');
    $path = flwPath(ApiRoutes::COMMUNITY_NOTIFICATION_LEVELS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]);

    $levels = $this->actingAs($this->plain->fresh())->getJson($path)->assertOk()->json('data');

    $bySpace = collect($levels)->keyBy('space_cuid');
    expect($bySpace->keys()->all())->toBe([$this->space->cuid, $this->announce->cuid, $this->chat->cuid, $this->course->cuid])
        ->and($bySpace->has($link->cuid))->toBeFalse()
        ->and($bySpace->has($groups->cuid))->toBeFalse()
        ->and($bySpace[$this->space->cuid])->toMatchArray(['level' => 'none', 'chosen' => true, 'default' => 'mine', 'space_type' => 'discussion', 'space_group_cuid' => $this->group->cuid, 'space_group_name' => $this->group->name])
        ->and($bySpace[$this->announce->cuid])->toMatchArray(['level' => 'all', 'chosen' => false, 'default' => 'all'])
        ->and($bySpace[$this->chat->cuid])->toMatchArray(['level' => 'all', 'chosen' => true, 'default' => 'mine'])
        ->and($bySpace[$this->course->cuid])->toMatchArray(['level' => 'mine', 'chosen' => false, 'default' => 'mine']);

    // A person who is not a member has none, and the call needs a signed-in member
    $this->actingAs($this->c['outsider'])->getJson($path)->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->getJson($path)->assertUnauthorized();
});

test('the levels of the member for a community: no query per space', function () {
    $path = flwPath(ApiRoutes::COMMUNITY_NOTIFICATION_LEVELS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]);
    $count = function () use ($path) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->plain->fresh())->getJson($path)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $count();
    $few = $count();

    foreach (range(1, 8) as $ignored) {
        $space = Space::factory()->inGroup($this->group)->create();
        flwLevel($this->plain, $space, 'mine');
    }

    expect($count())->toBe($few);
});

test('the levels of a community without a space are an empty JSON list, not a JSON object', function () {
    $empty = Community::factory()->create(['owner_id' => $this->owner->id]);
    $path = flwPath(ApiRoutes::COMMUNITY_NOTIFICATION_LEVELS, [ApiRoutes::PARAM_COMMUNITY => $empty->cuid]);

    $content = $this->actingAs($this->owner)->getJson($path)->assertOk()->getContent();

    expect($content)->toContain('"data":[]');
});

test('the space resource gives the default level to the people who manage the spaces only', function () {
    app(UpdatesSpaces::class)->update($this->owner, $this->space, ['default_notification_level' => 'all']);
    $path = flwSpacePath(ApiRoutes::COMMUNITY_SPACE, $this->community, $this->space);

    $this->actingAs($this->owner)->getJson($path)->assertOk()->assertJsonPath('data.default_notification_level', 'all');
    $this->actingAs($this->third)->getJson($path)->assertOk()->assertJsonPath('data.default_notification_level', 'all');
    $this->actingAs($this->owner)->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE, $this->community, $this->announce))->assertOk()->assertJsonPath('data.default_notification_level', null);
    foreach ([$this->plain, $this->replier] as $member) {
        $data = $this->actingAs($member)->getJson($path)->assertOk()->json('data');
        expect($data)->not->toHaveKey('default_notification_level');
    }
    $this->app['auth']->forgetGuards();
    $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    expect($this->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE, $this->community, $public))->assertOk()->json('data'))->not->toHaveKey('default_notification_level');
});

test('the space update takes default_notification_level through the API, with its validation and its ability', function () {
    $path = flwSpacePath(ApiRoutes::COMMUNITY_SPACE, $this->community, $this->space);

    $this->actingAs($this->owner)->patchJson($path, ['default_notification_level' => 'all'])->assertOk()->assertJsonPath('data.default_notification_level', 'all');
    $this->patchJson($path, ['default_notification_level' => 'none'])->assertStatus(400);
    $this->patchJson($path, ['default_notification_level' => 'all', 'name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
    $this->patchJson($path, ['default_notification_level' => null])->assertOk()->assertJsonPath('data.default_notification_level', null);
    $this->patchJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE, $this->community, $this->chat), ['default_notification_level' => 'all'])->assertStatus(400);

    $this->actingAs($this->plain)->patchJson($path, ['default_notification_level' => 'all'])->assertForbidden();
    Sanctum::actingAs($this->owner, ['communities:read']);
    $this->patchJson($path, ['default_notification_level' => 'all'])->assertForbidden();
    expect($this->space->fresh()->default_notification_level)->toBeNull();
});

test('a post is created with notify_now through the API, for the people who moderate the space', function () {
    Queue::fake([FanOutNotifications::class]);
    $path = flwSpacePath(ApiRoutes::COMMUNITY_SPACE_POSTS, $this->community, $this->announce);

    $this->actingAs($this->replier)->postJson($path, ['title' => 'Now please', 'body' => 'Body', 'notify_now' => true])->assertCreated()->assertJsonPath('data.follow_state', 'following');
    $this->actingAs($this->plain)->postJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_POSTS, $this->community, $this->space), ['title' => 'Now please', 'body' => 'Body', 'notify_now' => true])->assertForbidden();
    $this->actingAs($this->replier)->postJson($path, ['title' => 'Bad flag', 'body' => 'Body', 'notify_now' => 'soon'])->assertStatus(400);

    expect(Queue::pushed(FanOutNotifications::class))->toHaveCount(1)
        ->and(Queue::pushed(FanOutNotifications::class)->first()->delay)->toBeNull();
});

// The follow state on pages

test('the post and chat thread resources carry the follow state of the current member, null when there is none', function () {
    $post = flwPost($this->author, $this->space);
    $stopped = flwPost($this->author, $this->space, 'Stopped post', 'Body');
    app(FollowsThreads::class)->stopFollowing($this->plain, $stopped);
    app(FollowsThreads::class)->mute($this->plain, $post);
    $root = flwMessage($this->author, $this->chat, 'Root');
    flwMessage($this->replier, $this->chat, 'Reply', $root);

    $posts = $this->actingAs($this->plain)->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_POSTS, $this->community, $this->space))->assertOk()->json('data');
    $byTitle = collect($posts)->keyBy('title');
    expect($byTitle['A post for the tests']['follow_state'])->toBe('muted')
        ->and($byTitle['Stopped post']['follow_state'])->toBe('stopped');

    $this->actingAs($this->author)->getJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST, $this->community, $this->space, $post))->assertOk()->assertJsonPath('data.follow_state', 'following');
    $this->actingAs($this->third)->getJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_POST, $this->community, $this->space, $post))->assertOk()->assertJsonPath('data.follow_state', null);

    $messages = $this->actingAs($this->author)->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $this->community, $this->chat))->assertOk()->json('data');
    $thread = $this->actingAs($this->author)->getJson(flwThreadPath(ApiRoutes::COMMUNITY_SPACE_MESSAGE_THREAD, $this->community, $this->chat, $root))->assertOk()->json('data');
    expect(collect($messages)->firstWhere('cuid', $root->cuid)['follow_state'])->toBe('following')
        ->and($thread['message']['follow_state'])->toBe('following')
        // A reply has no thread of its own
        ->and($thread['replies'][0])->not->toHaveKey('follow_state');
});

test('the follow state of a page of posts and of messages costs one query, not one per item', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->plain->fresh())->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_POSTS, $this->community, $this->space))->assertOk();
        $this->actingAs($this->plain->fresh())->getJson(flwSpacePath(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $this->community, $this->chat))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $post = flwPost($this->author, $this->space, 'First', 'Body');
    flwMessage($this->author, $this->chat, 'First message');
    $count();
    $few = $count();

    foreach (range(1, 8) as $index) {
        $extra = flwPost($this->author, $this->space, "Post $index", 'Body');
        app(FollowsThreads::class)->follow($this->plain, $extra);
        flwMessage($this->author, $this->chat, "Message $index");
    }

    expect($count())->toBe($few)
        ->and($post->id)->toBeGreaterThan(0);
});

// Rows go away

test('a member who leaves a community loses their follows, their levels and their rows there, not elsewhere', function () {
    $other = Community::factory()->create(['owner_id' => $this->owner->id]);
    $otherSpace = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $other->id]))->create();
    $other->users()->attach([$this->plain->id => ['role' => 'member'], $this->author->id => ['role' => 'member']]);
    $post = flwPost($this->plain, $this->space);
    $elsewhere = flwPost($this->plain, $otherSpace, 'Elsewhere', 'Body');
    flwLevel($this->plain, $this->space, 'all');
    flwLevel($this->plain, $otherSpace, 'none');
    flwPost($this->replier, $this->announce, 'News');
    expect(flwFollow($this->plain, $post))->not->toBeNull();

    $this->community->removeUser($this->plain);

    expect(flwFollow($this->plain, $post))->toBeNull()
        ->and(flwFollow($this->plain, $elsewhere))->not->toBeNull()
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->plain->id)->pluck('space_id')->all())->toBe([$otherSpace->id])
        ->and(flwRows($this->plain)->where('community_id', $this->community->id))->toHaveCount(0);
});

test('a deleted thread, a deleted space and a deleted account take their follows and levels with them', function () {
    $post = flwPost($this->author, $this->space);
    $root = flwMessage($this->author, $this->chat, 'Root');
    flwMessage($this->plain, $this->chat, 'Reply', $root);
    flwLevel($this->plain, $this->space, 'none');
    expect(NotificationFollow::query()->count())->toBeGreaterThanOrEqual(3);

    app(DeletesPosts::class)->delete($this->author, $post);
    expect(flwFollow($this->author, $post))->toBeNull();

    DB::table('chat_messages')->where('id', $root->id)->delete();
    expect(NotificationFollow::query()->whereNotNull('chat_message_id')->count())->toBe(0);

    $this->space->delete();
    expect(NotificationSpaceLevel::query()->count())->toBe(0);

    $kept = flwPost($this->replier, $this->announce, 'Kept', 'Body');
    app(FollowsThreads::class)->follow($this->author, $kept);
    flwLevel($this->author, $this->announce, 'mine');
    $this->author->delete();
    expect(NotificationFollow::query()->where('post_id', $kept->id)->pluck('user_id')->all())->toBe([$this->replier->id])
        ->and(NotificationSpaceLevel::query()->count())->toBe(0);
});

test('a marker loses its follows: a chat message that was deleted, a post that became a marker', function () {
    $root = flwMessage($this->author, $this->chat, 'Root');
    flwMessage($this->plain, $this->chat, 'Reply', $root);
    expect(flwFollow($this->plain, $root))->not->toBeNull();

    app(DeletesChatMessages::class)->delete($this->author, $root);

    expect(ChatMessage::query()->whereKey($root->id)->exists())->toBeTrue()
        ->and(NotificationFollow::query()->where('chat_message_id', $root->id)->count())->toBe(0);

    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    expect(NotificationFollow::query()->where('post_id', $post->id)->count())->toBe(2);
    app(PrunesNotifications::class)->forPosts([$post->id]);
    expect(NotificationFollow::query()->where('post_id', $post->id)->count())->toBe(0);

    $message = flwMessage($this->author, $this->chat, 'Another root');
    flwMessage($this->plain, $this->chat, 'Reply', $message);
    app(PrunesNotifications::class)->forChatMessages([$message->id]);
    expect(NotificationFollow::query()->where('chat_message_id', $message->id)->count())->toBe(0);
});

test('the access job also deletes the follows and the levels of a member who cannot view the space any more', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->replier->id, $this->plain->id, $this->other->id]);
    $groups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $groups->accessGroups()->attach($insiders);
    $members = Space::factory()->inGroup($this->group)->create();
    $groupPost = flwPost($this->replier, $groups, 'In the group');
    $memberPost = flwPost($this->replier, $members, 'For members');
    $root = ChatMessage::factory()->inSpace($this->chat)->create(['author_id' => $this->replier->id]);
    foreach ([$this->plain, $this->other] as $member) {
        app(FollowsThreads::class)->follow($member, $groupPost);
        app(FollowsThreads::class)->follow($member, $memberPost);
        flwLevel($member, $groups, 'all');
        flwLevel($member, $members, 'none');
    }
    app(FollowsThreads::class)->follow($this->plain, $root);

    $insiders->members()->detach($this->plain);
    AccessGroupsChanged::dispatch($this->community->cuid);

    // The member who lost the access keeps what is about the spaces that they still view
    expect(flwFollow($this->plain, $groupPost))->toBeNull()
        ->and(flwFollow($this->plain, $memberPost))->not->toBeNull()
        ->and(flwFollow($this->plain, $root))->not->toBeNull()
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->plain->id)->pluck('space_id')->all())->toBe([$members->id])
        // The member who still has the access keeps everything
        ->and(flwFollow($this->other, $groupPost))->not->toBeNull()
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->other->id)->count())->toBe(2);
});

test('a space that becomes a groups space deletes the follows and the levels of the members outside its groups', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach($this->replier->id);
    $post = flwPost($this->replier, $this->space);
    app(FollowsThreads::class)->follow($this->plain, $post);
    flwLevel($this->plain, $this->space, 'all');
    flwLevel($this->third, $this->space, 'all');

    app(UpdatesSpaces::class)->update($this->owner, $this->space, ['visibility' => 'groups', 'access_groups' => [$insiders->cuid]]);

    expect(flwFollow($this->plain, $post))->toBeNull()
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->plain->id)->count())->toBe(0)
        // The admin views every space: the choice stays
        ->and(NotificationSpaceLevel::query()->where('user_id', $this->third->id)->count())->toBe(1);
});

test('a block deletes the rows that the blocked member caused, including a new lesson', function () {
    $first = flwLesson($this->replier, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);
    flwLesson($this->replier, $this->course, 'Lesson 2');
    expect(flwRows($this->plain))->toHaveCount(1);

    app(BlocksMembers::class)->block($this->plain, $this->replier);

    expect(flwRows($this->plain))->toHaveCount(0);
});

test('the rows of level 2 hold one item, are bound to their item, and the check allows no row without one', function () {
    $lesson = Page::factory()->inSpace($this->course)->create();
    $row = Notification::factory()->state([
        'user_id' => $this->plain->id, 'kind' => KnowiiNotificationKind::LessonNew, 'comment_id' => null, 'page_id' => $lesson->id,
        'community_id' => $this->community->id, 'space_id' => $this->course->id, 'actor_id' => $this->replier->id, 'dedupe_key' => 'lesson_new:space:'.$this->course->id,
    ])->create();
    expect($row->page_id)->toBe($lesson->id);

    // A lesson row and a comment row at the same time, or none: the database refuses
    expect(fn () => DB::table('notifications')->where('id', $row->id)->update(['post_id' => Post::factory()->inSpace($this->space)->create()->id]))->toThrow(QueryException::class);
});

test('a deleted lesson takes its rows with it', function () {
    $first = flwLesson($this->replier, $this->course, 'Lesson 1');
    flwStart($this->plain, $this->course, $first);
    $second = flwLesson($this->replier, $this->course, 'Lesson 2');
    expect(flwRows($this->plain))->toHaveCount(1);

    $second->delete();

    expect(flwRows($this->plain))->toHaveCount(0);
});

// Personal data (the export is tested in UserExportTest)

test('retention still deletes the rows of level 2, by the same two periods, and keeps the choices of the member', function () {
    flwPost($this->replier, $this->announce, 'Old news');
    $post = flwPost($this->author, $this->space);
    flwComment($this->plain, $post);
    flwComment($this->replier, $post);
    flwLevel($this->plain, $this->announce, 'all');
    $read = flwRows($this->plain, KnowiiNotificationKind::ThreadComment)->first();
    $unread = flwRows($this->other, KnowiiNotificationKind::SpacePost)->first();
    $fresh = flwRows($this->author, KnowiiNotificationKind::SpacePost)->first();
    $read->forceFill(['seen_at' => now()->subDays(70), 'read_at' => now()->subDays(61)])->save();
    Notification::query()->whereKey($unread->id)->toBase()->update(['created_at' => now()->subDays(91)]);
    $follows = NotificationFollow::query()->count();
    $levels = NotificationSpaceLevel::query()->count();

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Notification::query()->whereKey($read->id)->exists())->toBeFalse()
        ->and(Notification::query()->whereKey($unread->id)->exists())->toBeFalse()
        ->and(Notification::query()->whereKey($fresh->id)->exists())->toBeTrue()
        // The choices stay while the member is in the community
        ->and(NotificationFollow::query()->count())->toBe($follows)
        ->and(NotificationSpaceLevel::query()->count())->toBe($levels);
});
