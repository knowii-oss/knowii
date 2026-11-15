<?php

use App\ApiRoutes;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceType;
use App\Events\Conversations\ConversationChanged;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Visus\Cuid2\Cuid2;

/*
 * Delete one's content with the account (#1344, ADR 0043)
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class, ConversationChanged::class]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->me = $this->c['member'];
    $this->other = $this->c['admin'];
    $this->discussion = dcSpace($this->community, KnowiiSpaceType::Discussion);
    $this->chat = dcSpace($this->community, KnowiiSpaceType::Chat);
});

function dcSpace(Community $community, KnowiiSpaceType $type): Space
{
    return Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))->create(['type' => $type]);
}

function dcPost(Space $space, User $author, array $state = []): Post
{
    return Post::factory()->inSpace($space)->create(['author_id' => $author->id, ...$state]);
}

function dcComment(Post $post, User $author, ?Comment $parent = null): Comment
{
    $factory = Comment::factory()->onPost($post);

    return ($parent === null ? $factory : $factory->replyTo($parent))->create(['author_id' => $author->id]);
}

function dcMessage(Space $space, User $author, ?ChatMessage $parent = null): ChatMessage
{
    $factory = ChatMessage::factory()->inSpace($space);
    $message = ($parent === null ? $factory : $factory->replyTo($parent))->create(['author_id' => $author->id]);
    if ($parent !== null) {
        $parent->update([
            'reply_count' => $parent->replies()->whereNull('deleted_at')->count(),
            'last_reply_at' => $parent->replies()->whereNull('deleted_at')->max('created_at'),
        ]);
    }

    return $message;
}

function dcDelete(User $user, bool|string|null $content = true): void
{
    $body = ['password' => 'password'];
    if ($content !== null) {
        $body['delete_content'] = $content;
    }

    test()->actingAs($user)->delete('/user', $body);
}

test('without the box nothing changes: the content stays, without an author (ADR 0024)', function () {
    $post = dcPost($this->discussion, $this->me);
    $comment = dcComment(dcPost($this->discussion, $this->other), $this->me);
    $message = dcMessage($this->chat, $this->me);
    $conversation = Conversation::create(['community_id' => $this->community->id, 'created_by' => $this->me->id]);
    $conversation->participants()->attach([$this->me->id, $this->other->id]);
    $direct = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $this->community->id, 'author_id' => $this->me->id])->create();

    dcDelete($this->me, null);

    expect(User::query()->whereKey($this->me->id)->exists())->toBeFalse()
        ->and($post->fresh()?->author_id)->toBeNull()
        ->and($post->fresh()?->deleted_at)->toBeNull()
        ->and($post->fresh()?->title)->toBe($post->title)
        ->and($comment->fresh()?->deleted_at)->toBeNull()
        ->and($comment->fresh()?->body)->toBe($comment->body)
        ->and($message->fresh()?->deleted_at)->toBeNull()
        ->and($direct->fresh()?->deleted_at)->toBeNull()
        ->and($direct->fresh()?->body)->toBe($direct->body);
});

test('the box turned off has the same effect as no box', function () {
    $post = dcPost($this->discussion, $this->me);

    dcDelete($this->me, false);

    expect($post->fresh()?->title)->toBe($post->title)->and($post->fresh()?->author_id)->toBeNull();
});

test('the option must be a boolean: an invalid value refuses the deletion and keeps everything', function () {
    $post = dcPost($this->discussion, $this->me);

    $this->actingAs($this->me)->delete('/user', ['password' => 'password', 'delete_content' => 'maybe'])->assertSessionHasErrors('delete_content');

    expect(User::query()->whereKey($this->me->id)->exists())->toBeTrue()
        ->and($post->fresh()?->author_id)->toBe($this->me->id);
});

test('with the box, a post without comments of others goes with its own comments', function () {
    $post = dcPost($this->discussion, $this->me);
    $own = dcComment($post, $this->me);
    dcComment($post, $this->me, $own);

    dcDelete($this->me);

    expect(Post::query()->whereKey($post->id)->exists())->toBeFalse()
        ->and(Comment::query()->where('post_id', $post->id)->exists())->toBeFalse();
});

test('with the box, a post with comments of others stays as a marker with nothing of its content', function () {
    $post = dcPost($this->discussion, $this->me, ['title' => 'My secret plan', 'is_pinned' => true, 'pinned_at' => now()]);
    $upload = Upload::query()->forceCreate([
        'cuid' => (new Cuid2)->toString(), 'community_id' => $this->community->id, 'user_id' => $this->me->id, 'disk' => 'public',
        'path' => 'uploads/a.webp', 'mime_type' => 'image/webp', 'width' => 1, 'height' => 1, 'size_bytes' => 1, 'alt' => 'An image',
    ]);
    $post->uploadUsages()->create(['upload_id' => $upload->id]);
    $post->mentions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id]);
    $post->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id, 'emoji' => '👍']);
    $answer = dcComment($post, $this->other);
    $notified = Notification::query()->create([
        'cuid' => (new Cuid2)->toString(), 'user_id' => $this->other->id, 'community_id' => $this->community->id, 'space_id' => $this->discussion->id,
        'kind' => KnowiiNotificationKind::Mention, 'actor_id' => $this->me->id, 'post_id' => $post->id, 'dedupe_key' => 'test:post',
    ]);
    $about = Notification::factory()->forComment($answer, $this->me, $this->other)->create();

    dcDelete($this->me);

    $marker = Post::query()->findOrFail($post->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->title)->toBe('')
        ->and($marker->body)->toBe('')
        ->and($marker->body_html)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->slug)->not->toContain('secret')
        ->and($marker->is_pinned)->toBeFalse()
        ->and($marker->uploadUsages()->count())->toBe(0)
        ->and($marker->mentions()->count())->toBe(0)
        ->and($marker->reactions()->count())->toBe(0)
        ->and(Comment::query()->whereKey($answer->id)->exists())->toBeTrue()
        ->and(Notification::query()->whereKey($notified->id)->exists())->toBeFalse()
        // The notification of the other member's comment goes with the account of its recipient
        ->and(Notification::query()->whereKey($about->id)->exists())->toBeFalse();
    Event::assertNotDispatched(SpaceContentChanged::class);
});

test('a marker post shows to others with no title, text or author, and is not listed', function () {
    $post = dcPost($this->discussion, $this->me, ['title' => 'My secret plan', 'body' => 'The secret text']);
    dcComment($post, $this->other);
    $visible = dcPost($this->discussion, $this->other);

    dcDelete($this->me);
    $marker = $post->fresh();
    app('auth')->forgetGuards();

    $api = $this->actingAs($this->other)->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST, [
        ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->discussion->cuid, ApiRoutes::PARAM_POST => $marker->cuid,
    ]))->assertOk();
    expect($api->json('data.title'))->toBe('')
        ->and($api->json('data.body'))->toBe('')
        ->and($api->json('data.body_html'))->toBe('')
        ->and($api->json('data.author'))->toBeNull()
        ->and($api->json('data.deleted_at'))->not->toBeNull()
        ->and($api->getContent())->not->toContain('secret');

    $list = $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->discussion->cuid]))->assertOk();
    expect(array_column($list->json('data'), 'cuid'))->toBe([$visible->cuid]);

    $this->get(route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->discussion->slug]))
        ->assertInertia(fn ($page) => $page->has('posts.items', 1)->where('posts.pagination.totalCount', 1));

    $this->get(route('posts.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->discussion->slug, 'postSlug' => $marker->slug]))
        ->assertInertia(fn ($page) => $page->component('Posts/Show')
            ->where('post.title', '')
            ->where('post.author', null)
            ->where('permissions.canUpdatePost', false)
            ->where('permissions.canPinPost', false)
            ->where('permissions.canComment', false)
            ->has('comments', 1));
});

test('with the box, a comment with replies stays as a marker and one without replies goes', function () {
    $post = dcPost($this->discussion, $this->other);
    $withReplies = dcComment($post, $this->me);
    $reply = dcComment($post, $this->other, $withReplies);
    $alone = dcComment($post, $this->me);
    $withOwnReplyOnly = dcComment($post, $this->me);
    $ownReply = dcComment($post, $this->me, $withOwnReplyOnly);
    $withReplies->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id, 'emoji' => '👍']);
    $notified = Notification::factory()->forComment($withReplies, $this->other, $this->me)->create();

    dcDelete($this->me);

    $marker = Comment::query()->findOrFail($withReplies->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->body)->toBe('')
        ->and($marker->body_html)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->reactions()->count())->toBe(0)
        ->and(Notification::query()->whereKey($notified->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($reply->id)->exists())->toBeTrue()
        ->and(Comment::query()->whereKey($alone->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($withOwnReplyOnly->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($ownReply->id)->exists())->toBeFalse();

    app('auth')->forgetGuards();
    $list = $this->actingAs($this->other)->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [
        ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->discussion->cuid, ApiRoutes::PARAM_POST => $post->cuid,
    ]))->assertOk();
    expect($list->json('data.0.body'))->toBe('')
        ->and($list->json('data.0.author'))->toBeNull()
        ->and($list->json('data.0.deleted_at'))->not->toBeNull()
        ->and($list->json('data.0.replies.0.cuid'))->toBe($reply->cuid);
});

test('a marker comment whose last reply goes with the account goes too', function () {
    $post = dcPost($this->discussion, $this->c['owner']);
    $first = dcComment($post, $this->me);
    $reply = dcComment($post, $this->other, $first);

    dcDelete($this->me);
    // The comment is a marker for the reply of the other member
    expect(Comment::query()->findOrFail($first->id)->deleted_at)->not->toBeNull();

    app('auth')->forgetGuards();
    dcDelete($this->other);

    expect(Comment::query()->whereKey($reply->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($first->id)->exists())->toBeFalse();
});

test('comments on resources go too, and resources and pages stay under "Deleted member"', function () {
    $resourcesSpace = dcSpace($this->community, KnowiiSpaceType::Resources);
    $resource = CommunityResource::factory()->create(['space_id' => $resourcesSpace->id, 'curator_id' => $this->me->profile->id]);
    $comment = Comment::factory()->state(['post_id' => null, 'community_resource_id' => $resource->id, 'community_id' => $this->community->id, 'author_id' => $this->me->id])->create();
    $page = Page::factory()->inSpace(dcSpace($this->community, KnowiiSpaceType::Pages))->create(['author_id' => $this->me->id]);

    dcDelete($this->me);

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and($resource->fresh()?->curator_id)->toBeNull()
        ->and(CommunityResource::query()->whereKey($resource->id)->exists())->toBeTrue()
        ->and($page->fresh()?->author_id)->toBeNull()
        ->and($page->fresh()?->title)->toBe($page->title);
});

test('with the box, chat messages follow the thread rule: a marker with replies, gone without', function () {
    $withThread = dcMessage($this->chat, $this->me);
    $answer = dcMessage($this->chat, $this->other, $withThread);
    $alone = dcMessage($this->chat, $this->me);
    $ownThread = dcMessage($this->chat, $this->me);
    dcMessage($this->chat, $this->me, $ownThread);
    $replyInOtherThread = dcMessage($this->chat, $this->me, $answer->parent);
    $others = dcMessage($this->chat, $this->other);
    $withThread->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id, 'emoji' => '👍']);

    dcDelete($this->me);

    $marker = ChatMessage::query()->findOrFail($withThread->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->body)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->reactions()->count())->toBe(0)
        // The reply of the member in the thread went: the thread counts the answer of the other member only
        ->and($marker->reply_count)->toBe(1)
        ->and(ChatMessage::query()->whereKey($answer->id)->exists())->toBeTrue()
        ->and(ChatMessage::query()->whereKey($alone->id)->exists())->toBeFalse()
        ->and(ChatMessage::query()->whereKey($ownThread->id)->exists())->toBeFalse()
        ->and(ChatMessage::query()->whereKey($replyInOtherThread->id)->exists())->toBeFalse()
        ->and(ChatMessage::query()->where('parent_id', $ownThread->id)->exists())->toBeFalse()
        ->and($others->fresh()?->body)->toBe($others->body);
});

test('with the box, direct messages become markers and the other person keeps theirs', function () {
    $conversation = Conversation::create(['community_id' => $this->community->id, 'created_by' => $this->me->id]);
    $conversation->participants()->attach([$this->me->id, $this->other->id]);
    $mine = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $this->community->id, 'author_id' => $this->me->id, 'body' => 'my private words'])->create();
    $theirs = ChatMessage::factory()->state(['space_id' => null, 'conversation_id' => $conversation->id, 'community_id' => $this->community->id, 'author_id' => $this->other->id])->create();
    $mine->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id, 'emoji' => '👍']);

    dcDelete($this->me);

    $marker = ChatMessage::query()->findOrFail($mine->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->body)->toBe('')
        ->and($marker->body_html)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->reactions()->count())->toBe(0)
        ->and($theirs->fresh()?->body)->toBe($theirs->body)
        ->and($theirs->fresh()?->deleted_at)->toBeNull();

    app('auth')->forgetGuards();
    $list = $this->actingAs($this->other)->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES, [
        ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_CONVERSATION => $conversation->cuid,
    ]))->assertOk();
    $byCuid = collect($list->json('data'))->keyBy('cuid');
    expect($byCuid[$mine->cuid]['body'])->toBeNull()
        ->and($byCuid[$mine->cuid]['author'])->toBeNull()
        ->and($byCuid[$mine->cuid]['deleted_at'])->not->toBeNull()
        ->and($byCuid[$theirs->cuid]['body'])->toBe($theirs->body);
    Event::assertNotDispatched(ConversationChanged::class);
});

test('the content of other members is untouched', function () {
    $post = dcPost($this->discussion, $this->other);
    $comment = dcComment($post, $this->other);
    $message = dcMessage($this->chat, $this->other);
    $mine = dcPost($this->discussion, $this->me);
    dcComment($mine, $this->me);
    $reaction = $post->reactions()->create(['community_id' => $this->community->id, 'user_id' => $this->other->id, 'emoji' => '👍']);

    dcDelete($this->me);

    expect($post->fresh()?->title)->toBe($post->title)
        ->and($post->fresh()?->author_id)->toBe($this->other->id)
        ->and($post->fresh()?->deleted_at)->toBeNull()
        ->and($comment->fresh()?->body)->toBe($comment->body)
        ->and($message->fresh()?->body)->toBe($message->body)
        ->and(Reaction::query()->whereKey($reaction->id)->exists())->toBeTrue();
});

test('a member of two communities loses their content in both', function () {
    $second = Community::factory()->create();
    $second->users()->attach($this->me, ['role' => KnowiiCommunityMemberRole::Member]);
    $secondSpace = dcSpace($second, KnowiiSpaceType::Discussion);
    $secondChat = dcSpace($second, KnowiiSpaceType::Chat);
    $first = dcPost($this->discussion, $this->me);
    $inSecond = dcPost($secondSpace, $this->me);
    $inSecondWithAnswer = dcPost($secondSpace, $this->me);
    dcComment($inSecondWithAnswer, $this->other);
    $message = dcMessage($secondChat, $this->me);
    $unrelated = dcPost($secondSpace, $this->other);

    dcDelete($this->me);

    expect(Post::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(Post::query()->whereKey($inSecond->id)->exists())->toBeFalse()
        ->and($inSecondWithAnswer->fresh()?->deleted_at)->not->toBeNull()
        ->and(ChatMessage::query()->whereKey($message->id)->exists())->toBeFalse()
        ->and($unrelated->fresh()?->title)->toBe($unrelated->title);
});

test('the deletion holds for a member with thousands of items and keeps the markers', function () {
    $post = dcPost($this->discussion, $this->other);
    $answered = dcPost($this->discussion, $this->me);
    dcComment($answered, $this->other);
    $now = now()->toDateTimeString();
    $rows = static fn (int $count, callable $row): array => array_map($row, range(1, $count));
    foreach (array_chunk($rows(1200, static fn (int $i): array => [
        'cuid' => (new Cuid2)->toString(), 'community_id' => test()->community->id, 'post_id' => $post->id, 'author_id' => test()->me->id,
        'body' => 'c'.$i, 'body_html' => 'c'.$i, 'created_at' => $now, 'updated_at' => $now,
    ]), 300) as $chunk) {
        DB::table('comments')->insert($chunk);
    }
    foreach (array_chunk($rows(1100, static fn (int $i): array => [
        'cuid' => (new Cuid2)->toString(), 'community_id' => test()->community->id, 'space_id' => test()->chat->id, 'author_id' => test()->me->id,
        'body' => 'm'.$i, 'body_html' => 'm'.$i, 'created_at' => $now, 'updated_at' => $now,
    ]), 300) as $chunk) {
        DB::table('chat_messages')->insert($chunk);
    }

    dcDelete($this->me);

    expect(Comment::query()->where('post_id', $post->id)->count())->toBe(0)
        ->and(ChatMessage::query()->where('space_id', $this->chat->id)->count())->toBe(0)
        ->and($answered->fresh()?->deleted_at)->not->toBeNull();
});
