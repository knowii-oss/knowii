<?php

use App\Actions\Notifications\ListNotifications;
use App\Actions\Notifications\MarkNotificationsRead;
use App\Actions\Notifications\MarkNotificationsSeen;
use App\Actions\Notifications\Notify;
use App\Actions\Notifications\PruneNotifications;
use App\ApiRoutes;
use App\Contracts\Chat\CreatesChatMessages;
use App\Contracts\Chat\DeletesChatMessages;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Notifications\ListsNotifications;
use App\Contracts\Notifications\MarksNotificationsRead;
use App\Contracts\Notifications\MarksNotificationsSeen;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\PrunesNotifications;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Users\BlocksMembers;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Notifications\NotificationsChanged;
use App\Jobs\PruneUnviewableNotifications;
use App\Jobs\WriteNotifications;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\ChatRead;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\Sanctum;

/*
 * Notifications (#1474, ADR 0042): who is told about replies and mentions, bundling, access, blocks, the API.
 *
 * The queue is "sync" in the tests: the jobs run when the caller commits.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->space = Space::factory()->inGroup($this->group)->create();
    $this->chat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    // The author of the post, the member who answers, and a third member
    $this->author = $this->c['member'];
    $this->replier = $this->c['moderator'];
    $this->third = $this->c['admin'];
    // A plain member: the owner and the admins view every space, so they cannot show a closed space
    $this->plain = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($this->plain, ['role' => 'member']);
});

function ntfPost(User $author, Space $space, string $body = 'A post without mentions', string $title = 'A post for the tests'): Post
{
    return app(CreatesPosts::class)->create($author, $space, ['title' => $title, 'body' => $body]);
}

function ntfComment(User $author, Post $post, string $body = 'A comment without mentions', ?Comment $parent = null): Comment
{
    return app(CreatesComments::class)->create($author, $post, ['body' => $body, 'parent_cuid' => $parent?->cuid]);
}

function ntfChatMessage(User $author, Space $space, string $body): ChatMessage
{
    return app(CreatesChatMessages::class)->create($author, $space, ['body' => $body]);
}

/**
 * The notification rows of a member, oldest first
 *
 * @return Collection<int, Notification>
 */
function ntfRows(User $user): Collection
{
    return Notification::query()->where('user_id', $user->id)->orderBy('id')->get();
}

function ntfPath(string $route, array $params = []): string
{
    return ApiRoutes::path($route, $params);
}

/**
 * A level 1 row about a comment, for the API tests (a reply to the recipient, by an author who is a member)
 */
function ntfRow(User $recipient, Comment $comment, array $state = []): Notification
{
    return Notification::factory()->forComment($comment, $recipient)->state($state)->create();
}

// The contract, the kinds

test('the contracts are bound to the actions (ADR 0042)', function () {
    expect(app(Notifies::class))->toBeInstanceOf(Notify::class)
        ->and(app(ListsNotifications::class))->toBeInstanceOf(ListNotifications::class)
        ->and(app(MarksNotificationsSeen::class))->toBeInstanceOf(MarkNotificationsSeen::class)
        ->and(app(MarksNotificationsRead::class))->toBeInstanceOf(MarkNotificationsRead::class)
        ->and(app(PrunesNotifications::class))->toBeInstanceOf(PruneNotifications::class);
});

test('every kind has a level, and all the kinds of the first slice are level 1', function () {
    foreach (KnowiiNotificationKind::cases() as $kind) {
        expect($kind->level())->toBe(1);
    }
    expect(KnowiiNotificationKind::valuesOfLevel(1))->toBe(['post_comment', 'comment_reply', 'mention'])
        ->and(KnowiiNotificationKind::valuesOfLevel(2))->toBe([]);
});

test('a first-level comment on a post tells the author of the post, with the comment as subject', function () {
    $post = ntfPost($this->author, $this->space);
    $comment = ntfComment($this->replier, $post);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->kind)->toBe(KnowiiNotificationKind::PostComment)
        ->and($rows[0]->comment_id)->toBe($comment->id)
        ->and($rows[0]->post_id)->toBeNull()
        ->and($rows[0]->chat_message_id)->toBeNull()
        ->and($rows[0]->actor_id)->toBe($this->replier->id)
        ->and($rows[0]->community_id)->toBe($this->community->id)
        ->and($rows[0]->space_id)->toBe($this->space->id)
        ->and($rows[0]->seen_at)->toBeNull()
        ->and($rows[0]->read_at)->toBeNull()
        ->and(Notification::query()->count())->toBe(1);
});

test('a reply to a comment tells the author of that comment, not the author of the post', function () {
    $post = ntfPost($this->author, $this->space);
    $comment = ntfComment($this->replier, $post);
    $reply = ntfComment($this->third, $post, 'A reply', $comment);

    $rows = ntfRows($this->replier);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->kind)->toBe(KnowiiNotificationKind::CommentReply)
        ->and($rows[0]->comment_id)->toBe($reply->id)
        ->and($rows[0]->actor_id)->toBe($this->third->id)
        // The author of the post got the first comment only: a reply is not a comment on the post
        ->and(ntfRows($this->author))->toHaveCount(1)
        ->and(ntfRows($this->third))->toHaveCount(0);
});

test('a mention tells the mentioned member, in a post, a comment and a chat message', function () {
    $post = ntfPost($this->replier, $this->space, 'Hello @'.$this->third->username);
    $comment = ntfComment($this->replier, ntfPost($this->author, $this->space), 'Look @'.$this->third->username);
    $message = ntfChatMessage($this->replier, $this->chat, 'Chat @'.$this->third->username);

    $rows = ntfRows($this->third);
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('kind')->unique()->all())->toBe([KnowiiNotificationKind::Mention])
        ->and($rows[0]->post_id)->toBe($post->id)
        ->and($rows[1]->comment_id)->toBe($comment->id)
        ->and($rows[2]->chat_message_id)->toBe($message->id)
        ->and($rows[2]->space_id)->toBe($this->chat->id);
});

test('nobody is told about their own action', function () {
    $post = ntfPost($this->author, $this->space, 'Me, @'.$this->author->username);
    ntfComment($this->author, $post, 'I comment my own post, @'.$this->author->username);

    expect(Notification::query()->count())->toBe(0);
});

test('an edit tells nobody, also when it adds a mention', function () {
    $post = ntfPost($this->replier, $this->space);
    $comment = ntfComment($this->replier, $post);

    app(UpdatesPosts::class)->update($this->replier, $post, ['body' => 'Now with @'.$this->third->username]);
    app(UpdatesComments::class)->update($this->replier, $comment, ['body' => 'Now with @'.$this->third->username]);

    expect(Notification::query()->count())->toBe(0)
        ->and($post->mentions()->count())->toBe(1);
});

test('the order of the reasons is mention, then reply, then comment: one row per member and item', function () {
    // The author of the post is also the author of the comment that is answered, and is mentioned in the answer
    $post = ntfPost($this->author, $this->space);
    $comment = ntfComment($this->author, $post);
    ntfComment($this->replier, $post, 'Answer to @'.$this->author->username, $comment);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->kind)->toBe(KnowiiNotificationKind::Mention);

    // Without the mention, the same answer is a reply
    ntfComment($this->third, $post, 'Another answer', $comment);
    expect(ntfRows($this->author)->pluck('kind')->all())->toBe([KnowiiNotificationKind::Mention, KnowiiNotificationKind::CommentReply]);
});

test('a direct message gives no notification', function () {
    $conversation = Conversation::create(['community_id' => $this->community->id, 'created_by' => $this->replier->id]);
    $conversation->participants()->attach([$this->replier->id, $this->third->id]);
    $message = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $this->community->id, 'author_id' => $this->replier->id, 'body' => 'Hi @'.$this->third->username])->create();
    $message->mentions()->create(['community_id' => $this->community->id, 'user_id' => $this->third->id]);

    app(Notifies::class)->notify($message);

    expect(Notification::query()->count())->toBe(0);
});

// Bundling

test('a new comment bundles into the open row: new author, "New" again, moved up, same subject', function () {
    $post = ntfPost($this->author, $this->space);
    $this->travelTo(now()->subHour());
    $first = ntfComment($this->replier, $post);
    $row = ntfRows($this->author)->first();
    $row->forceFill(['seen_at' => now()])->save();
    $before = $row->fresh()->updated_at;

    $this->travelBack();
    $second = ntfComment($this->third, $post);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->comment_id)->toBe($first->id)
        ->and($rows[0]->actor_id)->toBe($this->third->id)
        ->and($rows[0]->seen_at)->toBeNull()
        ->and($rows[0]->updated_at->gt($before))->toBeTrue()
        ->and($second->id)->not->toBe($first->id);
});

test('replies to the same comment bundle, replies to another comment do not', function () {
    $post = ntfPost($this->author, $this->space);
    $mine = ntfComment($this->replier, $post);
    $other = ntfComment($this->replier, $post, 'Another comment');
    ntfComment($this->third, $post, 'First reply', $mine);
    ntfComment($this->author, $post, 'Second reply', $mine);
    ntfComment($this->third, $post, 'Reply elsewhere', $other);

    $rows = ntfRows($this->replier);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->actor_id)->toBe($this->author->id)
        ->and($rows[1]->actor_id)->toBe($this->third->id);
});

test('a read row is never opened again: the next comment makes a new row', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    app(MarksNotificationsRead::class)->read($this->author, ntfRows($this->author)->first());

    $next = ntfComment($this->third, $post);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->read_at)->not->toBeNull()
        ->and($rows[1]->read_at)->toBeNull()
        ->and($rows[1]->comment_id)->toBe($next->id);
});

test('mentions do not bundle', function () {
    ntfPost($this->replier, $this->space, 'One @'.$this->third->username, 'First mention post');
    ntfPost($this->replier, $this->space, 'Two @'.$this->third->username, 'Second mention post');

    expect(ntfRows($this->third))->toHaveCount(2);
});

test('storm cap: 3 unread mention rows from one author in an hour are the most', function () {
    foreach (range(1, 4) as $index) {
        ntfPost($this->replier, $this->space, 'Storm @'.$this->third->username, 'Storm post '.$index);
    }
    expect(ntfRows($this->third))->toHaveCount(3);

    // Another author is not capped, and the cap lifts after the hour
    ntfPost($this->author, $this->space, 'Other @'.$this->third->username, 'Other author post');
    expect(ntfRows($this->third))->toHaveCount(4);

    $this->travel(61)->minutes();
    ntfPost($this->replier, $this->space, 'Later @'.$this->third->username, 'Later post');
    expect(ntfRows($this->third))->toHaveCount(5);
});

test('storm cap: a mention that the member read does not count', function () {
    foreach (range(1, 3) as $index) {
        ntfPost($this->replier, $this->space, 'Storm @'.$this->third->username, 'Storm post '.$index);
    }
    app(MarksNotificationsRead::class)->read($this->third, ntfRows($this->third)->first());

    ntfPost($this->replier, $this->space, 'Once more @'.$this->third->username, 'Storm post 4');

    expect(ntfRows($this->third))->toHaveCount(4);
});

// Silence and age

test('silenced runs a callback without any notification, and returns its result', function () {
    $post = ntfPost($this->author, $this->space);

    $result = Notify::silenced(fn () => Notify::silenced(fn () => ntfComment($this->replier, $post)));

    expect($result)->toBeInstanceOf(Comment::class)
        ->and(Notify::isSilenced())->toBeFalse()
        ->and(Notification::query()->count())->toBe(0);

    ntfComment($this->replier, $post);
    expect(Notification::query()->count())->toBe(1);
});

test('silenced stops being silent when the callback throws', function () {
    expect(fn () => Notify::silenced(fn () => throw new RuntimeException('Import failed')))->toThrow(RuntimeException::class)
        ->and(Notify::isSilenced())->toBeFalse();
});

test('an item that is older than 15 minutes at the moment of the event gives no notification', function () {
    $post = ntfPost($this->author, $this->space);
    $old = Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id, 'created_at' => now()->subMinutes(20)]);
    $recent = Comment::factory()->onPost($post)->create(['author_id' => $this->third->id, 'created_at' => now()->subMinutes(5)]);

    app(Notifies::class)->notify($old);
    expect(Notification::query()->count())->toBe(0);

    app(Notifies::class)->notify($recent);
    expect(ntfRows($this->author))->toHaveCount(1);
});

test('the notifications are written by a job on the queue notifications', function () {
    Queue::fake();
    $post = Post::factory()->inSpace($this->space)->create(['author_id' => $this->author->id]);
    $comment = Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]);

    app(Notifies::class)->notify($comment);

    Queue::assertPushedOn('notifications', WriteNotifications::class, fn (WriteNotifications $job) => $job->item->is($comment) && $job->afterCommit === true);
});

// Access, blocks

test('a member who cannot view the space is not told', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->author->id, $this->replier->id]);
    $space = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $space->accessGroups()->attach($insiders);

    $post = ntfPost($this->author, $space, 'Group post, @'.$this->plain->username);
    ntfComment($this->replier, $post, 'Group comment, @'.$this->plain->username);

    // The member in the group is told, the member outside of it is not (the mention row exists, the notification does not)
    expect(ntfRows($this->author))->toHaveCount(1)
        ->and(ntfRows($this->plain))->toHaveCount(0)
        ->and($post->mentions()->count())->toBe(1);
});

test('a member who left the community is not told', function () {
    $post = ntfPost($this->author, $this->space);
    $this->community->removeUser($this->author);

    ntfComment($this->replier, $post);

    expect(Notification::query()->count())->toBe(0);
});

test('a member who blocked the author is not told, the author is told in the other direction', function () {
    $post = ntfPost($this->author, $this->space);
    $this->author->blockedUsers()->attach($this->replier);

    ntfComment($this->replier, $post, 'Blocked, @'.$this->author->username);
    expect(Notification::query()->count())->toBe(0);

    // The replier blocked nobody: the author of the post is the one who blocked, so the other direction gives a row
    $post2 = ntfPost($this->replier, $this->space, 'Second post', 'Second post of the tests');
    ntfComment($this->author, $post2);
    expect(ntfRows($this->replier))->toHaveCount(1);
});

test('a new block deletes the rows that the blocked member caused, and tells the other tabs', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    ntfComment($this->third, $post, 'Not blocked');
    $other = ntfPost($this->third, $this->space, 'Third post', 'Third post of the tests');
    ntfComment($this->replier, $other);
    // The row of the author bundles both comments: the last author is the member who stays
    expect(ntfRows($this->author)->first()->actor_id)->toBe($this->third->id);

    Event::fake([NotificationsChanged::class]);
    app(BlocksMembers::class)->block($this->author, $this->replier);

    // The subject of the bundle is the comment of the blocked member: the row goes too
    expect(ntfRows($this->author))->toHaveCount(0)
        ->and(ntfRows($this->third))->toHaveCount(1);
    Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event) => $event->userCuid === $this->author->cuid && $event->notificationCuid !== null);
});

// Deletions

test('the database cascades delete the rows of a deleted post, comment, space and community', function () {
    $post = ntfPost($this->author, $this->space);
    $comment = ntfComment($this->replier, $post);
    ntfComment($this->third, ntfPost($this->author, $this->space, 'Second', 'Second post of the tests'));
    $mention = ntfPost($this->replier, $this->space, 'Hello @'.$this->third->username, 'Post with a mention');
    expect(Notification::query()->count())->toBe(3);

    app(DeletesComments::class)->delete($this->replier, $comment);
    expect(Notification::query()->where('comment_id', $comment->id)->count())->toBe(0);

    app(DeletesPosts::class)->delete($this->replier, $mention);
    expect(ntfRows($this->third))->toHaveCount(0);

    $this->space->delete();
    expect(Notification::query()->count())->toBe(0);

    $comment = ntfComment($this->replier, ntfPost($this->author, Space::factory()->inGroup($this->group)->create(), 'Another space', 'Another post'));
    expect(Notification::query()->count())->toBe(1);
    $this->community->purge();
    expect(Notification::query()->count())->toBe(0);
});

test('deleting an account deletes its rows, and the rows that it caused stay without an author', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);

    $this->replier->delete();
    expect(ntfRows($this->author)->first()->actor_id)->toBeNull();

    $this->author->delete();
    expect(Notification::query()->count())->toBe(0);
});

test('a deleted chat message keeps its marker and loses its notifications', function () {
    $message = ntfChatMessage($this->replier, $this->chat, 'Chat @'.$this->third->username);
    expect(ntfRows($this->third))->toHaveCount(1);

    app(DeletesChatMessages::class)->delete($this->replier, $message);

    expect(ChatMessage::query()->whereKey($message->id)->exists())->toBeTrue()
        ->and($message->fresh()->isDeleted())->toBeTrue()
        ->and(ntfRows($this->third))->toHaveCount(0);
});

test('a chat message that was deleted before the job ran tells nobody', function () {
    $message = Notify::silenced(fn () => ntfChatMessage($this->replier, $this->chat, 'Chat @'.$this->third->username));
    app(DeletesChatMessages::class)->delete($this->replier, $message);

    app(Notifies::class)->deliver($message->fresh());

    expect(ntfRows($this->third))->toHaveCount(0);
});

test('a member who leaves or is removed loses their rows in that community only', function () {
    $other = Community::factory()->create(['owner_id' => $this->c['owner']->id]);
    $otherGroup = SpaceGroup::factory()->create(['community_id' => $other->id]);
    $otherSpace = Space::factory()->inGroup($otherGroup)->create();
    $other->users()->attach([$this->author->id => ['role' => 'member'], $this->replier->id => ['role' => 'member']]);

    ntfComment($this->replier, ntfPost($this->author, $this->space));
    ntfComment($this->replier, ntfPost($this->author, $otherSpace, 'Elsewhere', 'Post in another community'));
    expect(ntfRows($this->author))->toHaveCount(2);

    $this->community->removeUser($this->author);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->community_id)->toBe($other->id);
});

test('the job that follows an access change deletes the rows of the members who lost the access', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->author->id, $this->replier->id, $this->third->id]);
    $space = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $space->accessGroups()->attach($insiders);
    $members = Space::factory()->inGroup($this->group)->create();

    ntfComment($this->replier, ntfPost($this->author, $space, 'In the group', 'Post in a group space'));
    ntfComment($this->replier, ntfPost($this->author, $members, 'Open to all', 'Post in a members space'));
    expect(ntfRows($this->author))->toHaveCount(2);

    // The member leaves the access group: the row of the groups space goes, the row of the members space stays
    $insiders->members()->detach($this->author);
    AccessGroupsChanged::dispatch($this->community->cuid);

    $rows = ntfRows($this->author);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->space_id)->toBe($members->id);
});

test('the access change is handled by a job on the queue notifications', function () {
    Queue::fake();

    AccessGroupsChanged::dispatch($this->community->cuid);

    Queue::assertPushedOn('notifications', PruneUnviewableNotifications::class);
});

test('a space that becomes a groups space deletes the rows of the members outside its groups', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach($this->replier->id);
    ntfComment($this->replier, ntfPost($this->author, $this->space));
    ntfComment($this->replier, ntfPost($this->third, $this->space, 'Third post', 'The third member post'));
    expect(Notification::query()->count())->toBe(2);

    app(UpdatesSpaces::class)->update($this->c['owner'], $this->space, ['visibility' => 'groups', 'access_groups' => [$insiders->cuid]]);

    // The author of the post lost the access and the row with it. The admin views every space: the row of the admin stays
    expect(ntfRows($this->author))->toHaveCount(0)
        ->and(ntfRows($this->third))->toHaveCount(1);
});

// Read-time check

test('the list and the count delete the row of a member who lost the access', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->author->id, $this->replier->id]);
    $space = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $space->accessGroups()->attach($insiders);
    ntfComment($this->replier, ntfPost($this->author, $space));
    expect(ntfRows($this->author))->toHaveCount(1);

    // No event here: nothing started a job. The read finds out by itself
    $insiders->members()->detach($this->author);

    $this->actingAs($this->author->fresh())->getJson(ntfPath(ApiRoutes::NOTIFICATIONS_COUNT))
        ->assertOk()
        ->assertJsonPath('data.count', 0);
    expect(Notification::query()->count())->toBe(0);

    $insiders->members()->attach($this->author);
    ntfComment($this->replier, ntfPost($this->author, $space, 'Second', 'Second post in the group space'));
    $insiders->members()->detach($this->author);

    $this->actingAs($this->author->fresh())->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))
        ->assertOk()
        ->assertJsonPath('metadata.pagination.totalCount', 0)
        ->assertJsonCount(0, 'data');
    expect(Notification::query()->count())->toBe(0);
});

// The table

test('the table holds one item per row and one open row per member and thing', function () {
    $post = ntfPost($this->author, $this->space);
    $comment = ntfComment($this->replier, $post);
    $row = ntfRows($this->author)->first();

    // Exactly one of the three items
    expect(fn () => DB::transaction(fn () => Notification::query()->whereKey($row->id)->toBase()->update(['post_id' => $post->id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => Notification::query()->whereKey($row->id)->toBase()->update(['comment_id' => null])))->toThrow(QueryException::class);

    // One open row per member and dedupe key, and a read row does not block a new one
    $clone = fn () => Notification::create([
        'user_id' => $row->user_id, 'community_id' => $row->community_id, 'kind' => $row->kind,
        'comment_id' => Comment::factory()->onPost($post)->create()->id, 'dedupe_key' => $row->dedupe_key,
    ]);
    expect(fn () => DB::transaction($clone))->toThrow(QueryException::class);

    $row->forceFill(['read_at' => now()])->save();
    expect(DB::transaction($clone))->toBeInstanceOf(Notification::class)
        ->and($comment->id)->toBe($row->comment_id);
});

// The count

test('the count holds the rows that the member did not see, 14 days old at most, after the access check', function () {
    $post = ntfPost($this->author, $this->space);
    $comments = collect(range(1, 4))->map(fn () => Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]));
    ntfRow($this->author, $comments[0]);
    ntfRow($this->author, $comments[1], ['seen_at' => now()]);
    ntfRow($this->author, $comments[2], ['updated_at' => now()->subDays(15)]);
    ntfRow($this->author, $comments[3], ['updated_at' => now()->subDays(13)]);
    // The row of another member is not counted
    ntfRow($this->third, $comments[0]);

    $this->actingAs($this->author)->getJson(ntfPath(ApiRoutes::NOTIFICATIONS_COUNT))
        ->assertOk()
        ->assertJsonPath('data.count', 2);
});

// The API

test('the list has the documented shape, built from the live content', function () {
    $post = ntfPost($this->author, $this->space, 'Body of the post', 'My title');
    $body = "Hello <b>bold</b> **markdown** and a [link](https://example.com/x)\n\n".str_repeat('word ', 100);
    $comment = ntfComment($this->replier, $post, $body);

    $response = $this->actingAs($this->author)->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk();

    $item = $response->json('data.0');
    expect($item['cuid'])->toBe(ntfRows($this->author)->first()->cuid)
        ->and($item['kind'])->toBe('post_comment')
        ->and($item['level'])->toBe(1)
        ->and($item['community'])->toBe(['cuid' => $this->community->cuid, 'slug' => $this->community->slug, 'name' => $this->community->name])
        ->and($item['space'])->toBe(['cuid' => $this->space->cuid, 'slug' => $this->space->slug, 'name' => $this->space->name])
        ->and($item['actor']['cuid'])->toBe($this->replier->cuid)
        ->and(array_keys($item['actor']))->toBe(['cuid', 'name', 'username', 'avatar_url'])
        ->and($item['actors'])->toHaveCount(1)
        ->and($item['others_count'])->toBe(0)
        ->and($item['title'])->toBe('My title')
        ->and($item['target'])->toBe(['post_slug' => $post->slug, 'comment_cuid' => $comment->cuid, 'chat_message_cuid' => null, 'thread_cuid' => null, 'resource_cuid' => null])
        ->and($item['seen_at'])->toBeNull()
        ->and($item['read_at'])->toBeNull()
        ->and($item)->toHaveKeys(['created_at', 'updated_at'])
        ->and(mb_strlen($item['excerpt']))->toBeLessThanOrEqual(300)
        ->and($item['excerpt'])->toStartWith('Hello bold markdown and a link')
        ->and($item['excerpt'])->not->toContain('<')->not->toContain('**')->not->toContain('https://')
        ->and($item['excerpt'])->toEndWith('…');
    expect($response->json('metadata.pagination'))->toMatchArray(['limit' => 20, 'offset' => 0, 'totalCount' => 1, 'pageCount' => 1]);
    // No text is stored in the row: the table has no column for it
    expect(Schema::getColumnListing('notifications'))->not->toContain('body')->not->toContain('excerpt')->not->toContain('title');
});

test('the list of a mention in a chat thread names the message and its thread', function () {
    $root = ntfChatMessage($this->third, $this->chat, 'The root');
    $reply = app(CreatesChatMessages::class)->create($this->replier, $this->chat, ['body' => 'In the thread @'.$this->author->username, 'parent_cuid' => $root->cuid]);

    $item = $this->actingAs($this->author)->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk()->json('data.0');

    expect($item['kind'])->toBe('mention')
        ->and($item['title'])->toBeNull()
        ->and($item['excerpt'])->toStartWith('In the thread')
        ->and($item['target'])->toMatchArray(['chat_message_cuid' => $reply->cuid, 'thread_cuid' => $root->cuid, 'post_slug' => null, 'comment_cuid' => null]);
});

test('the list is paginated 20 by 20, newest news first, and filters by community and age', function () {
    $post = ntfPost($this->author, $this->space);
    $comments = collect(range(1, 23))->map(fn () => Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]));
    foreach ($comments as $index => $comment) {
        ntfRow($this->author, $comment, ['updated_at' => now()->subHours(30 - $index)]);
    }
    $old = Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]);
    ntfRow($this->author, $old, ['updated_at' => now()->subDays(20)]);

    $this->actingAs($this->author);
    $first = $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk();
    expect($first->json('data'))->toHaveCount(20)
        ->and($first->json('metadata.pagination.totalCount'))->toBe(23)
        ->and($first->json('metadata.pagination.pageCount'))->toBe(2)
        ->and($first->json('metadata.pagination.nextOffset'))->toBe(20)
        ->and($first->json('data.0.target.comment_cuid'))->toBe($comments[22]->cuid);

    $second = $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS).'?offset=20')->assertOk();
    expect($second->json('data'))->toHaveCount(3)->and($second->json('metadata.pagination.nextOffset'))->toBeNull();

    $older = $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS).'?older=true')->assertOk();
    expect($older->json('data'))->toHaveCount(1)->and($older->json('data.0.target.comment_cuid'))->toBe($old->cuid);

    $none = $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS).'?community='.Community::factory()->create()->cuid)->assertOk();
    expect($none->json('data'))->toBe([]);
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS).'?community='.$this->community->cuid.'&limit=5')->assertOk()->assertJsonCount(5, 'data');
});

test('a bundle names up to 3 authors and counts the others, without a blocked author', function () {
    $post = ntfPost($this->author, $this->space);
    $authors = collect(range(1, 5))->map(function () {
        $user = User::factory()->withUserProfile()->create();
        $this->community->users()->attach($user, ['role' => 'member']);

        return $user;
    });
    foreach ($authors as $author) {
        ntfComment($author, $post);
    }
    ntfComment($authors[0], $post, 'The first author comments again');
    $this->author->blockedUsers()->attach($authors[1]);

    $item = $this->actingAs($this->author)->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk()->json('data.0');

    // 5 authors, one blocked: 4 are counted, 3 are named, newest first
    $names = array_column($item['actors'], 'cuid');
    expect(ntfRows($this->author))->toHaveCount(1)
        ->and($names)->toHaveCount(3)
        ->and($names)->not->toContain($authors[1]->cuid)
        ->and($item['others_count'])->toBe(1);
});

test('a deleted author shows as null and the bundle keeps working', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    $this->replier->delete();

    $item = $this->actingAs($this->author)->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk()->json('data.0');

    // The comment is still there, without an author: the row shows "Deleted member" on the client
    expect($item['actor'])->toBeNull()->and($item['actors'])->toBe([])->and($item['others_count'])->toBe(0);
});

test('the list runs the same number of queries for 2 rows and for 20', function () {
    $post = ntfPost($this->author, $this->space);
    $chatRoot = ChatMessage::factory()->inSpace($this->chat)->create(['author_id' => $this->third->id]);
    $makeRows = function (int $count) use ($post, $chatRoot) {
        foreach (range(1, $count) as $ignored) {
            $comment = Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]);
            ntfRow($this->author, $comment, ['kind' => KnowiiNotificationKind::PostComment]);
        }
        $reply = ChatMessage::factory()->replyTo($chatRoot)->create(['author_id' => $this->replier->id]);
        Notification::factory()->state(['user_id' => $this->author->id, 'kind' => KnowiiNotificationKind::Mention, 'comment_id' => null, 'chat_message_id' => $reply->id, 'community_id' => $this->community->id, 'space_id' => $this->chat->id, 'actor_id' => $this->replier->id])->create();
    };
    $queries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->author->fresh())->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $makeRows(1);
    $queries();
    $few = $queries();
    expect(Notification::query()->count())->toBe(2);

    $makeRows(17);
    expect(Notification::query()->count())->toBe(20);
    $many = $queries();

    expect($many)->toBe($few);
});

test('seen, read and undo work on the rows of the member only', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    ntfComment($this->third, ntfPost($this->replier, $this->space, 'Second', 'Second post of the tests'));
    $mine = ntfRows($this->author)->first();
    $theirs = ntfRows($this->replier)->first();
    $this->actingAs($this->author);
    $before = $mine->fresh()->updated_at;

    // Seen: a list. The row of another member stays as it was
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => [$mine->cuid, $theirs->cuid, 'unknown']])->assertOk();
    expect($mine->fresh()->seen_at)->not->toBeNull()
        ->and($mine->fresh()->read_at)->toBeNull()
        ->and($mine->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and($theirs->fresh()->seen_at)->toBeNull();
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS_COUNT))->assertJsonPath('data.count', 0);

    // Read: also seen. Undo: open again, still seen
    $mine->forceFill(['seen_at' => null])->save();
    $this->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $mine->cuid]))
        ->assertOk()
        ->assertJsonPath('data.cuid', $mine->cuid)
        ->assertJsonPath('data.level', 1);
    expect($mine->fresh()->read_at)->not->toBeNull()->and($mine->fresh()->seen_at)->not->toBeNull();
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATION_UNREAD, [ApiRoutes::PARAM_NOTIFICATION => $mine->cuid]))->assertOk();
    expect($mine->fresh()->read_at)->toBeNull()->and($mine->fresh()->seen_at)->not->toBeNull();

    // Another member's row gives 404, and stays untouched
    $this->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $theirs->cuid]))->assertNotFound();
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATION_UNREAD, [ApiRoutes::PARAM_NOTIFICATION => $theirs->cuid]))->assertNotFound();
    $this->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => 'unknown']))->assertNotFound();
    expect($theirs->fresh()->read_at)->toBeNull();

    // No endpoint deletes a row
    $this->deleteJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $mine->cuid]))->assertStatus(405);
    expect(Notification::query()->count())->toBe(2);
});

test('seen needs a list of 1 to 100 cuids: a validation issue is a 400', function () {
    $this->actingAs($this->author);

    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), [])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => []])->assertStatus(400);
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => array_fill(0, 101, 'x')])->assertStatus(400);
});

test('undo read is a business error while a newer row about the same thing is open', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    $old = ntfRows($this->author)->first();
    app(MarksNotificationsRead::class)->read($this->author, $old);
    ntfComment($this->third, $post);

    $this->actingAs($this->author)->postJson(ntfPath(ApiRoutes::NOTIFICATION_UNREAD, [ApiRoutes::PARAM_NOTIFICATION => $old->cuid]))->assertStatus(422);

    expect($old->fresh()->read_at)->not->toBeNull();
});

test('a notification that the member cannot view any more is not opened, it is deleted', function () {
    $insiders = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Insiders']);
    $insiders->members()->attach([$this->author->id, $this->replier->id]);
    $space = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $space->accessGroups()->attach($insiders);
    ntfComment($this->replier, ntfPost($this->author, $space));
    $row = ntfRows($this->author)->first();
    $insiders->members()->detach($this->author);

    $this->actingAs($this->author->fresh())->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]))->assertNotFound();

    expect(Notification::query()->count())->toBe(0);
});

// Chat

test('reading a chat space reads the mentions of the space up to the message that was read', function () {
    $first = ntfChatMessage($this->replier, $this->chat, 'One @'.$this->author->username);
    $second = ntfChatMessage($this->replier, $this->chat, 'Two @'.$this->author->username);
    $third = ntfChatMessage($this->replier, $this->chat, 'Three @'.$this->author->username);
    $otherChat = Space::factory()->inGroup($this->group)->create(['type' => KnowiiSpaceType::Chat]);
    ntfChatMessage($this->third, $otherChat, 'Elsewhere @'.$this->author->username);

    $this->actingAs($this->author)->postJson(ntfPath(ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->chat->cuid]), ['message_cuid' => $second->cuid])->assertOk();

    $rows = ntfRows($this->author)->keyBy('chat_message_id');
    expect($rows[$first->id]->read_at)->not->toBeNull()->and($rows[$first->id]->seen_at)->not->toBeNull()
        ->and($rows[$second->id]->read_at)->not->toBeNull()
        ->and($rows[$third->id]->read_at)->toBeNull()->and($rows[$third->id]->seen_at)->toBeNull()
        ->and($rows->filter(fn (Notification $row) => $row->read_at === null))->toHaveCount(2);
});

test('reading a chat mention moves the chat cursor forward, never backward', function () {
    // Two authors: three unread mentions from one author in an hour are the most
    $messages = collect(range(1, 5))->map(fn ($index) => ntfChatMessage($index % 2 === 0 ? $this->replier : $this->third, $this->chat, 'Message '.$index.' @'.$this->author->username));
    $rows = ntfRows($this->author)->keyBy('chat_message_id');
    $this->actingAs($this->author);
    $path = fn (Notification $row) => ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]);
    $cursor = fn () => ChatRead::query()->where('user_id', $this->author->id)->where('space_id', $this->chat->id)->value('last_read_message_id');

    // No cursor yet: it starts at the message of the row
    $this->putJson($path($rows[$messages[2]->id]))->assertOk();
    expect($cursor())->toBe($messages[2]->id);

    // Forward
    $this->putJson($path($rows[$messages[4]->id]))->assertOk();
    expect($cursor())->toBe($messages[4]->id);

    // Never backward
    $this->putJson($path($rows[$messages[0]->id]))->assertOk();
    expect($cursor())->toBe($messages[4]->id);
    // Undo read leaves the cursor where it is
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATION_UNREAD, [ApiRoutes::PARAM_NOTIFICATION => $rows[$messages[4]->id]->cuid]))->assertOk();
    expect($cursor())->toBe($messages[4]->id);
});

test('reading a mention in a thread moves the cursor to the message of the thread', function () {
    $root = ntfChatMessage($this->third, $this->chat, 'Root');
    $reply = app(CreatesChatMessages::class)->create($this->replier, $this->chat, ['body' => 'Thread @'.$this->author->username, 'parent_cuid' => $root->cuid]);

    $this->actingAs($this->author)->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => ntfRows($this->author)->first()->cuid]))->assertOk();

    expect(ChatRead::query()->where('user_id', $this->author->id)->value('last_read_message_id'))->toBe($root->id)
        ->and($reply->parent_id)->toBe($root->id);
});

// The post page

test('the post page marks the notifications about the post and its comments as seen, not read', function () {
    $post = ntfPost($this->author, $this->space, 'Hello @'.$this->third->username);
    $mentionPost = ntfPost($this->replier, $this->space, 'Hello @'.$this->author->username, 'Mention in a post');
    ntfComment($this->replier, $post);
    $comment = ntfComment($this->third, $mentionPost, 'Hello @'.$this->author->username);
    $unrelated = ntfComment($this->third, ntfPost($this->replier, $this->space, 'Other', 'Other post of the tests'), 'Hello @'.$this->author->username);
    expect(ntfRows($this->author))->toHaveCount(4);

    $this->actingAs($this->author)
        ->get(route('posts.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->space->slug, 'postSlug' => $mentionPost->slug]))
        ->assertOk();

    $seen = ntfRows($this->author)->filter(fn (Notification $row) => $row->seen_at !== null);
    expect($seen)->toHaveCount(2)
        ->and($seen->pluck('read_at')->filter()->count())->toBe(0)
        ->and(Notification::query()->where('comment_id', $unrelated->id)->value('seen_at'))->toBeNull();
    expect(Notification::query()->where('user_id', $this->author->id)->where('comment_id', $comment->id)->value('seen_at'))->not->toBeNull();
});

// The event

test('the event goes to the private channel of the member, on the queue broadcasts, with identifiers only', function () {
    Event::fake([NotificationsChanged::class]);
    $post = ntfPost($this->author, $this->space);

    ntfComment($this->replier, $post);

    $row = ntfRows($this->author)->first();
    Event::assertDispatched(NotificationsChanged::class, function (NotificationsChanged $event) use ($row) {
        return $event->userCuid === $this->author->cuid
            && $event->broadcastWith() === ['notification_cuid' => $row->cuid, 'community_cuid' => $this->community->cuid]
            && $event->broadcastQueue() === 'broadcasts'
            && $event->broadcastAs() === 'notifications.changed'
            && array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ['private-user.'.$this->author->cuid];
    });
    Event::assertDispatchedTimes(NotificationsChanged::class, 1);
});

test('the event is queued on the queue broadcasts', function () {
    Queue::fake();

    NotificationsChanged::dispatch($this->author->cuid, $this->community->cuid, null);

    Queue::assertPushedOn('broadcasts', BroadcastEvent::class);
});

test('the event is sent when a row is bundled, seen, read, and deleted', function () {
    $post = ntfPost($this->author, $this->space);
    ntfComment($this->replier, $post);
    $row = ntfRows($this->author)->first();
    $this->actingAs($this->author);

    Event::fake([NotificationsChanged::class]);
    ntfComment($this->third, $post);
    Event::assertDispatchedTimes(NotificationsChanged::class, 1);

    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => [$row->cuid]])->assertOk();
    Event::assertDispatchedTimes(NotificationsChanged::class, 2);

    $this->putJson(ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]))->assertOk();
    Event::assertDispatchedTimes(NotificationsChanged::class, 3);

    $this->community->removeUser($this->author);
    Event::assertDispatchedTimes(NotificationsChanged::class, 4);
    Event::assertDispatched(NotificationsChanged::class, fn (NotificationsChanged $event) => $event->notificationCuid === null || $event->notificationCuid === $row->cuid);
});

test('the event tells nothing about the content', function () {
    $event = new NotificationsChanged('user-cuid', 'community-cuid', 'notification-cuid');

    expect(array_keys($event->broadcastWith()))->toBe(['notification_cuid', 'community_cuid']);
});

// Token abilities

test('the notification abilities exist and are not in the default set of a token', function () {
    expect(Jetstream::$permissions)->toContain('notifications:read')->toContain('notifications:write')
        ->and(Jetstream::$defaultPermissions)->not->toContain('notifications:read')->not->toContain('notifications:write');
});

test('a token reads notifications with notifications:read and writes with notifications:write only', function () {
    ntfComment($this->replier, ntfPost($this->author, $this->space));
    $row = ntfRows($this->author)->first();
    $readPath = ntfPath(ApiRoutes::NOTIFICATION_READ, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]);

    Sanctum::actingAs($this->author, ['notifications:read']);
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertOk();
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS_COUNT))->assertOk();
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => [$row->cuid]])->assertForbidden();
    $this->putJson($readPath)->assertForbidden();

    Sanctum::actingAs($this->author, ['notifications:write']);
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertForbidden();
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATIONS_SEEN), ['notification_cuids' => [$row->cuid]])->assertOk();
    $this->putJson($readPath)->assertOk();
    $this->postJson(ntfPath(ApiRoutes::NOTIFICATION_UNREAD, [ApiRoutes::PARAM_NOTIFICATION => $row->cuid]))->assertOk();

    Sanctum::actingAs($this->author, ['posts:read', 'messages:read', 'user:read']);
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertForbidden();
});

test('the notification routes need a signed-in member', function () {
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS))->assertUnauthorized();
    $this->getJson(ntfPath(ApiRoutes::NOTIFICATIONS_COUNT))->assertUnauthorized();
});

// Retention

test('retention:prune deletes read rows after 60 days by read date and unread rows after 90 days by creation date', function () {
    $post = ntfPost($this->author, $this->space);
    $make = function (array $state) use ($post) {
        return ntfRow($this->author, Comment::factory()->onPost($post)->create(['author_id' => $this->replier->id]), $state);
    };
    $readOld = $make(['read_at' => now()->subDays(61), 'seen_at' => now()->subDays(61), 'created_at' => now()->subDays(62)]);
    $readRecent = $make(['read_at' => now()->subDays(59), 'seen_at' => now()->subDays(59), 'created_at' => now()->subDays(200)]);
    $unreadOld = $make(['created_at' => now()->subDays(91)]);
    $unreadRecent = $make(['created_at' => now()->subDays(89)]);

    $this->artisan('retention:prune', ['--dry-run' => true])->assertSuccessful();
    expect(Notification::query()->count())->toBe(4);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Notification::query()->pluck('id')->all())->toEqualCanonicalizing([$readRecent->id, $unreadRecent->id])
        ->and(Notification::query()->whereKey($readOld->id)->exists())->toBeFalse()
        ->and(Notification::query()->whereKey($unreadOld->id)->exists())->toBeFalse();
});

test('the retention periods of the notifications come from the configuration', function () {
    config(['retention.notifications_read_days' => 10, 'retention.notifications_unread_days' => 20]);
    $comment = Comment::factory()->onPost(ntfPost($this->author, $this->space))->create(['author_id' => $this->replier->id]);
    $row = ntfRow($this->author, $comment, ['read_at' => now()->subDays(11), 'seen_at' => now()->subDays(11)]);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Notification::query()->whereKey($row->id)->exists())->toBeFalse();
});

test('the factory builds a valid row', function () {
    $row = Notification::factory()->create();

    expect($row->cuid)->not->toBe('')->and(Str::length($row->cuid))->toBe(24)->and($row->kind)->toBe(KnowiiNotificationKind::CommentReply);
});
