<?php

use App\ApiRoutes;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Users\DeletesMemberContent;
use App\Enums\KnowiiNotificationKind;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Visus\Cuid2\Cuid2;

/*
 * A delete by the author keeps the replies of other people (#1565, ADR 0050)
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->author = $this->c['member'];
    $this->other = $this->c['admin'];
    $this->moderator = $this->c['moderator'];
    $this->space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->community->id]))
        ->create(['type' => KnowiiSpaceType::Discussion]);
});

function dkrPost(Space $space, User $author, array $state = []): Post
{
    return Post::factory()->inSpace($space)->create(['author_id' => $author->id, ...$state]);
}

function dkrComment(Post $post, ?User $author, ?Comment $parent = null, array $state = []): Comment
{
    $factory = Comment::factory()->onPost($post);

    return ($parent === null ? $factory : $factory->replyTo($parent))->create(['author_id' => $author?->id, ...$state]);
}

/**
 * A reaction, a mention, an upload usage and a notification on the item: what the marker loses
 */
function dkrAttachEverything(Post|Comment $item, User $author, User $other): Notification
{
    $community = $item->community;
    $upload = Upload::query()->forceCreate([
        'cuid' => (new Cuid2)->toString(), 'community_id' => $community->id, 'user_id' => $author->id, 'disk' => 'public',
        'path' => 'uploads/'.(new Cuid2)->toString().'.webp', 'mime_type' => 'image/webp', 'width' => 1, 'height' => 1, 'size_bytes' => 1, 'alt' => 'An image',
    ]);
    $item->uploadUsages()->create(['upload_id' => $upload->id]);
    $item->mentions()->create(['community_id' => $community->id, 'user_id' => $other->id]);
    $item->reactions()->create(['community_id' => $community->id, 'user_id' => $other->id, 'emoji' => '👍']);

    if ($item instanceof Comment) {
        return Notification::factory()->forComment($item, $other, $author)->create();
    }

    return Notification::query()->create([
        'cuid' => (new Cuid2)->toString(), 'user_id' => $other->id, 'community_id' => $community->id, 'space_id' => $item->space_id,
        'kind' => KnowiiNotificationKind::Mention, 'actor_id' => $author->id, 'post_id' => $item->id, 'dedupe_key' => 'test:'.(new Cuid2)->toString(),
    ]);
}

function dkrCommentPath(Comment $comment): string
{
    $post = $comment->post ?? throw new LogicException('A comment of a post');

    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [
        ApiRoutes::PARAM_COMMUNITY => $post->community->cuid,
        ApiRoutes::PARAM_SPACE => $post->space->cuid,
        ApiRoutes::PARAM_POST => $post->cuid,
        ApiRoutes::PARAM_COMMENT => $comment->cuid,
    ]);
}

function dkrPostPath(Post $post): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST, [
        ApiRoutes::PARAM_COMMUNITY => $post->community->cuid,
        ApiRoutes::PARAM_SPACE => $post->space->cuid,
        ApiRoutes::PARAM_POST => $post->cuid,
    ]);
}

/**
 * The columns of a marker that must be the same, whoever made it
 *
 * @return array<string, bool|int|string|null>
 */
function dkrMarkerColumns(Post|Comment $item): array
{
    $fresh = $item->fresh() ?? throw new LogicException('The marker is gone');
    $columns = $fresh->getAttributes();
    // The identity of the row and the times
    unset($columns['id'], $columns['cuid'], $columns['created_at'], $columns['updated_at'], $columns['post_id'], $columns['parent_id']);
    $columns['deleted_at'] = $fresh->deleted_at !== null;
    if ($fresh instanceof Post) {
        $columns['slug'] = $fresh->slug === 'deleted-'.strtolower($fresh->cuid);
    }
    $columns['reactions'] = $fresh->reactions()->count();
    $columns['mentions'] = $fresh->mentions()->count();
    $columns['upload_usages'] = $fresh->uploadUsages()->count();

    /** @var array<string, bool|int|string|null> $columns */
    return $columns;
}

// The delete of a comment

test('the author deletes a comment with replies of others: a marker stays, with every reply, and loses what the account deletion removes', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->author, state: ['body' => 'My secret words', 'edited_at' => now()]);
    $answer = dkrComment($post, $this->other, $comment);
    $ownReply = dkrComment($post, $this->author, $comment);
    $notified = dkrAttachEverything($comment, $this->author, $this->other);

    $this->actingAs($this->author)->deleteJson(dkrCommentPath($comment))
        ->assertOk()
        ->assertJsonPath('message', 'Comment deleted, its replies stay');

    $marker = Comment::query()->findOrFail($comment->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->body)->toBe('')
        ->and($marker->body_html)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->edited_at)->toBeNull()
        ->and($marker->reactions()->count())->toBe(0)
        ->and($marker->mentions()->count())->toBe(0)
        ->and($marker->uploadUsages()->count())->toBe(0)
        ->and(Notification::query()->whereKey($notified->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($answer->id)->exists())->toBeTrue()
        // The own replies of the author stay too
        ->and(Comment::query()->whereKey($ownReply->id)->value('author_id'))->toBe($this->author->id);

    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::COMMENT_UPDATED
        && $event->payload['comment_cuid'] === $comment->cuid);
    Event::assertNotDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::COMMENT_DELETED);

    // The list shows the marker in place, with its replies, and nothing of the old text
    $list = $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [
        ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_POST => $post->cuid,
    ]))->assertOk();
    expect($list->json('data.0.cuid'))->toBe($comment->cuid)
        ->and($list->json('data.0.deleted_at'))->not->toBeNull()
        ->and($list->json('data.0.author'))->toBeNull()
        ->and($list->json('data.0.replies'))->toHaveCount(2)
        ->and($list->getContent())->not->toContain('secret');
});

test('the author deletes a comment without replies of others: it goes with their own replies', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->author);
    $ownReply = dkrComment($post, $this->author, $comment);
    $alone = dkrComment($post, $this->author);

    $this->actingAs($this->author)->deleteJson(dkrCommentPath($comment))->assertNoContent();
    $this->deleteJson(dkrCommentPath($alone))->assertNoContent();

    expect(Comment::query()->whereKey([$comment->id, $ownReply->id, $alone->id])->exists())->toBeFalse();
    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::COMMENT_DELETED
        && $event->payload['comment_cuid'] === $comment->cuid);
});

test('a reply of a deleted account counts as a reply of another person, a marker does not', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->author);
    dkrComment($post, null, $comment);

    app(DeletesComments::class)->delete($this->author, $comment);

    expect(Comment::query()->findOrFail($comment->id)->deleted_at)->not->toBeNull();
});

test('a moderator leaves a marker by default and deletes the whole thread with delete_replies', function () {
    $post = dkrPost($this->space, $this->other);
    $kept = dkrComment($post, $this->author);
    $keptReply = dkrComment($post, $this->other, $kept);
    $gone = dkrComment($post, $this->author);
    $goneReplies = [dkrComment($post, $this->other, $gone)->id, dkrComment($post, $this->moderator, $gone)->id];

    $this->actingAs($this->moderator)->deleteJson(dkrCommentPath($kept))->assertOk();
    $this->deleteJson(dkrCommentPath($gone), ['delete_replies' => true])->assertNoContent();

    expect(Comment::query()->findOrFail($kept->id)->deleted_at)->not->toBeNull()
        ->and(Comment::query()->whereKey($keptReply->id)->exists())->toBeTrue()
        ->and(Comment::query()->whereKey([$gone->id, ...$goneReplies])->exists())->toBeFalse();
});

test('a moderator deletes their own comment with only their own replies: it goes', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->moderator);
    dkrComment($post, $this->moderator, $comment);

    expect(app(DeletesComments::class)->delete($this->moderator, $comment, ['delete_replies' => false]))->toBeNull()
        ->and(Comment::query()->where('post_id', $post->id)->count())->toBe(0);
});

test('an author who is not a moderator cannot delete the replies of others', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->author);
    $answer = dkrComment($post, $this->other, $comment);

    $this->actingAs($this->author)->deleteJson(dkrCommentPath($comment), ['delete_replies' => true])->assertForbidden();

    expect(Comment::query()->findOrFail($comment->id)->deleted_at)->toBeNull()
        ->and(Comment::query()->whereKey($answer->id)->exists())->toBeTrue();
});

test('a member who is not the author and not a moderator cannot delete a comment', function (bool $withReplies) {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->other);

    $this->actingAs($this->author)->deleteJson(dkrCommentPath($comment), $withReplies ? ['delete_replies' => true] : [])->assertForbidden();
    expect(Comment::query()->whereKey($comment->id)->exists())->toBeTrue();
})->with(['without the option' => false, 'with the option' => true]);

test('a token without posts:write cannot delete a comment', function () {
    $comment = dkrComment(dkrPost($this->space, $this->other), $this->author);
    Sanctum::actingAs($this->author, ['posts:read']);

    $this->deleteJson(dkrCommentPath($comment))->assertForbidden();
    expect(Comment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('delete_replies must be a boolean', function () {
    $comment = dkrComment(dkrPost($this->space, $this->other), $this->author);

    $this->actingAs($this->moderator)->deleteJson(dkrCommentPath($comment), ['delete_replies' => 'everything'])
        ->assertStatus(400)
        ->assertJsonValidationErrorFor('delete_replies');
    expect(Comment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('a marker is not deleted a second time, like a chat message, but a moderator deletes it with its replies', function () {
    $post = dkrPost($this->space, $this->other);
    $comment = dkrComment($post, $this->author);
    $answer = dkrComment($post, $this->other, $comment);
    app(DeletesComments::class)->delete($this->author, $comment);
    $marker = $comment->fresh();

    expect(fn () => app(DeletesComments::class)->delete($this->author, $marker))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeletesComments::class)->delete($this->moderator, $marker))->toThrow(AuthorizationException::class);

    $this->actingAs($this->moderator)->deleteJson(dkrCommentPath($marker), ['delete_replies' => true])->assertNoContent();
    expect(Comment::query()->whereKey([$comment->id, $answer->id])->exists())->toBeFalse();
});

test('a marker stays when its last reply goes, like a chat message (#1185)', function () {
    $post = dkrPost($this->space, $this->c['owner']);
    $comment = dkrComment($post, $this->author);
    $answer = dkrComment($post, $this->other, $comment);
    app(DeletesComments::class)->delete($this->author, $comment);

    expect(app(DeletesComments::class)->delete($this->other, $answer->fresh()))->toBeNull()
        ->and(Comment::query()->findOrFail($comment->id)->deleted_at)->not->toBeNull();
});

test('the comments of a resource follow the same rule', function () {
    $resources = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->community->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);
    $resource = CommunityResource::factory()->create(['community_id' => $this->community->id, 'space_id' => $resources->id]);
    $comment = Comment::factory()->create([
        'post_id' => null, 'community_resource_id' => $resource->id, 'community_id' => $this->community->id, 'author_id' => $this->author->id,
    ]);
    Comment::factory()->create([
        'post_id' => null, 'community_resource_id' => $resource->id, 'community_id' => $this->community->id, 'author_id' => $this->other->id, 'parent_id' => $comment->id,
    ]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [
        ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $resources->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid, ApiRoutes::PARAM_COMMENT => $comment->cuid,
    ]);

    $this->actingAs($this->author)->deleteJson($path, ['delete_replies' => true])->assertForbidden();
    $this->deleteJson($path)->assertOk();
    expect(Comment::query()->findOrFail($comment->id)->deleted_at)->not->toBeNull();

    app('auth')->forgetGuards();
    $this->actingAs($this->moderator)->deleteJson($path, ['delete_replies' => true])->assertNoContent();
    expect(Comment::query()->where('community_resource_id', $resource->id)->count())->toBe(0);
});

// The delete of a post

test('the author deletes a post with comments of others: a marker stays, with every comment, and loses what the account deletion removes', function () {
    $post = dkrPost($this->space, $this->author, ['title' => 'My secret plan', 'is_pinned' => true, 'pinned_at' => now(), 'edited_at' => now()]);
    $answer = dkrComment($post, $this->other);
    $own = dkrComment($post, $this->author);
    $notified = dkrAttachEverything($post, $this->author, $this->other);

    $this->actingAs($this->author)->deleteJson(dkrPostPath($post))
        ->assertOk()
        ->assertJsonPath('message', 'Post deleted, its comments stay');

    $marker = Post::query()->findOrFail($post->id);
    expect($marker->deleted_at)->not->toBeNull()
        ->and($marker->title)->toBe('')
        ->and($marker->body)->toBe('')
        ->and($marker->body_html)->toBe('')
        ->and($marker->author_id)->toBeNull()
        ->and($marker->slug)->toBe('deleted-'.strtolower($post->cuid))
        ->and($marker->is_pinned)->toBeFalse()
        ->and($marker->pinned_at)->toBeNull()
        ->and($marker->edited_at)->toBeNull()
        ->and($marker->reactions()->count())->toBe(0)
        ->and($marker->mentions()->count())->toBe(0)
        ->and($marker->uploadUsages()->count())->toBe(0)
        ->and(Notification::query()->whereKey($notified->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey([$answer->id, $own->id])->count())->toBe(2);

    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::POST_UPDATED
        && $event->payload['post_cuid'] === $post->cuid);
    Event::assertNotDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::POST_DELETED);

    // The author stays on the page of the marker: it opens with its new slug, without the actions of an author
    $this->get(route('posts.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->space->slug, 'postSlug' => $marker->slug]))
        ->assertInertia(fn ($page) => $page->component('Posts/Show')
            ->where('post.title', '')
            ->where('permissions.canUpdatePost', false)
            ->where('permissions.canDeletePost', false)
            ->where('permissions.canDeletePostWithComments', false)
            ->has('comments', 2));
});

test('the author deletes a post without comments of others: it goes with their own comments', function () {
    $post = dkrPost($this->space, $this->author);
    $own = dkrComment($post, $this->author);
    // A marker comment is not a comment of another person
    dkrComment($post, null, state: ['body' => '', 'body_html' => '', 'deleted_at' => now()]);

    $this->actingAs($this->author)->deleteJson(dkrPostPath($post))->assertNoContent();

    expect(Post::query()->whereKey($post->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($own->id)->exists())->toBeFalse();
    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => $event->name === SpaceContentChanged::POST_DELETED);
});

test('a comment of a deleted account keeps the post as a marker', function () {
    $post = dkrPost($this->space, $this->author);
    dkrComment($post, null);

    expect(app(DeletesPosts::class)->delete($this->author, $post)?->deleted_at)->not->toBeNull();
});

test('a moderator leaves a post marker by default and deletes the whole discussion with delete_comments', function () {
    $kept = dkrPost($this->space, $this->author);
    dkrComment($kept, $this->other);
    $gone = dkrPost($this->space, $this->author);
    $comments = [dkrComment($gone, $this->other)->id, dkrComment($gone, $this->author)->id];

    $this->actingAs($this->moderator)->deleteJson(dkrPostPath($kept))->assertOk();
    $this->deleteJson(dkrPostPath($gone), ['delete_comments' => true])->assertNoContent();

    expect(Post::query()->findOrFail($kept->id)->deleted_at)->not->toBeNull()
        ->and(Post::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($comments)->exists())->toBeFalse();
});

test('an author who is not a moderator cannot delete the comments of others with the post', function () {
    $post = dkrPost($this->space, $this->author);
    dkrComment($post, $this->other);

    $this->actingAs($this->author)->deleteJson(dkrPostPath($post), ['delete_comments' => true])->assertForbidden();
    expect(Post::query()->findOrFail($post->id)->deleted_at)->toBeNull();
});

test('a member who is not the author and not a moderator cannot delete a post', function (bool $withComments) {
    $post = dkrPost($this->space, $this->other);

    $this->actingAs($this->author)->deleteJson(dkrPostPath($post), $withComments ? ['delete_comments' => true] : [])->assertForbidden();
    expect(Post::query()->whereKey($post->id)->exists())->toBeTrue();
})->with(['without the option' => false, 'with the option' => true]);

test('a token without posts:write cannot delete a post', function () {
    $post = dkrPost($this->space, $this->author);
    Sanctum::actingAs($this->author, ['posts:read']);

    $this->deleteJson(dkrPostPath($post))->assertForbidden();
    expect(Post::query()->whereKey($post->id)->exists())->toBeTrue();
});

test('delete_comments must be a boolean', function () {
    $post = dkrPost($this->space, $this->author);

    $this->actingAs($this->moderator)->deleteJson(dkrPostPath($post), ['delete_comments' => 'all'])
        ->assertStatus(400)
        ->assertJsonValidationErrorFor('delete_comments');
    expect(Post::query()->whereKey($post->id)->exists())->toBeTrue();
});

test('a post marker is not deleted a second time, but a moderator deletes it with its comments', function () {
    $post = dkrPost($this->space, $this->author);
    $answer = dkrComment($post, $this->other);
    $marker = app(DeletesPosts::class)->delete($this->author, $post) ?? throw new LogicException('A marker');

    expect(fn () => app(DeletesPosts::class)->delete($this->moderator, $marker))->toThrow(AuthorizationException::class);

    $this->actingAs($this->moderator)->deleteJson(dkrPostPath($marker), ['delete_comments' => true])->assertNoContent();
    expect(Post::query()->whereKey($post->id)->exists())->toBeFalse()
        ->and(Comment::query()->whereKey($answer->id)->exists())->toBeFalse();
});

// One marker for the ordinary delete and for the account deletion

test('the ordinary delete and the account deletion give the same markers', function () {
    $byDelete = dkrPost($this->space, $this->author, ['title' => 'Plan', 'is_pinned' => true, 'pinned_at' => now(), 'edited_at' => now()]);
    $byAccount = dkrPost($this->space, $this->author, ['title' => 'Plan', 'is_pinned' => true, 'pinned_at' => now(), 'edited_at' => now()]);
    $host = dkrPost($this->space, $this->c['owner']);
    $commentByDelete = dkrComment($host, $this->author, state: ['edited_at' => now()]);
    $commentByAccount = dkrComment($host, $this->author, state: ['edited_at' => now()]);
    foreach ([$byDelete, $byAccount, $commentByDelete, $commentByAccount] as $item) {
        dkrAttachEverything($item, $this->author, $this->other);
    }
    dkrComment($byDelete, $this->other);
    dkrComment($byAccount, $this->other);
    dkrComment($host, $this->other, $commentByDelete);
    dkrComment($host, $this->other, $commentByAccount);

    app(DeletesPosts::class)->delete($this->author, $byDelete);
    app(DeletesComments::class)->delete($this->author, $commentByDelete);
    app(DeletesMemberContent::class)->delete($this->author);

    expect(dkrMarkerColumns($byDelete))->toBe(dkrMarkerColumns($byAccount))
        ->and(dkrMarkerColumns($byDelete)['deleted_at'])->toBeTrue()
        ->and(dkrMarkerColumns($commentByDelete))->toBe(dkrMarkerColumns($commentByAccount))
        ->and(dkrMarkerColumns($commentByDelete)['deleted_at'])->toBeTrue();
});
