<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    // Members can comment even where only moderators post (Announcements)
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
    $this->post = Post::factory()->inSpace($this->space)->create(['author_id' => $this->c['moderator']->id]);
});

function commentsPath(Post $post, ?Comment $comment = null): string
{
    return ApiRoutes::path($comment ? ApiRoutes::COMMUNITY_SPACE_POST_COMMENT : ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $post->community->cuid,
        ApiRoutes::PARAM_SPACE => $post->space->cuid,
        ApiRoutes::PARAM_POST => $post->cuid,
        ApiRoutes::PARAM_COMMENT => $comment?->cuid,
    ]));
}

test('members comment posts, also in spaces where only moderators post', function () {
    $comment = app(CreatesComments::class)->create($this->c['member'], $this->post, ['body' => 'Nice **post** <script>x</script>']);

    expect($comment->post_id)->toBe($this->post->id)
        ->and($comment->parent_id)->toBeNull()
        ->and($comment->community_id)->toBe($this->c['community']->id)
        ->and($comment->body_html)->toContain('<strong>post</strong>')
        ->and($comment->body_html)->not->toContain('<script>');
});

test('outsiders cannot comment', function () {
    app(CreatesComments::class)->create($this->c['outsider'], $this->post, ['body' => 'Hi']);
})->throws(AuthorizationException::class);

test('replies have one level: a reply to a reply goes to the top-level comment', function () {
    $creator = app(CreatesComments::class);
    $top = $creator->create($this->c['member'], $this->post, ['body' => 'Top']);
    $reply = $creator->create($this->c['admin'], $this->post, ['body' => 'Reply', 'parent_cuid' => $top->cuid]);
    $replyToReply = $creator->create($this->c['member'], $this->post, ['body' => 'Reply to the reply', 'parent_cuid' => $reply->cuid]);

    expect($reply->parent_id)->toBe($top->id)->and($replyToReply->parent_id)->toBe($top->id);
});

test('a reply must target a comment of the same post', function () {
    $other = Comment::factory()->onPost(Post::factory()->inSpace($this->space)->create())->create();

    app(CreatesComments::class)->create($this->c['member'], $this->post, ['body' => 'Hi', 'parent_cuid' => $other->cuid]);
})->throws(ValidationException::class);

test('a comment needs a body', function (string $body) {
    app(CreatesComments::class)->create($this->c['member'], $this->post, ['body' => $body]);
})->with(['empty' => '', 'too long' => str_repeat('a', 10001)])->throws(ValidationException::class);

test('only the author edits a comment', function () {
    $comment = Comment::factory()->onPost($this->post)->create(['author_id' => $this->c['member']->id]);

    expect(fn () => app(UpdatesComments::class)->update($this->c['moderator'], $comment, ['body' => 'Hijacked']))->toThrow(AuthorizationException::class);

    $updated = app(UpdatesComments::class)->update($this->c['member'], $comment, ['body' => '_Edited_']);
    expect($updated->body_html)->toContain('<em>Edited</em>')->and($updated->edited_at)->not->toBeNull();
});

test('the author or a moderator deletes a comment, its own replies go with it and the count stays right', function () {
    // The replies of other people would keep a marker (#1565): DeleteKeepsRepliesTest
    $top = Comment::factory()->onPost($this->post)->create(['author_id' => $this->c['member']->id]);
    Comment::factory()->replyTo($top)->count(2)->create(['author_id' => $this->c['member']->id]);
    $other = Comment::factory()->onPost($this->post)->create();

    expect(fn () => app(DeletesComments::class)->delete($this->c['outsider'], $top))->toThrow(AuthorizationException::class);

    app(DeletesComments::class)->delete($this->c['member'], $top);
    expect($this->post->comments()->count())->toBe(1);

    app(DeletesComments::class)->delete($this->c['moderator'], $other);
    expect($this->post->comments()->count())->toBe(0);
});

test('the API returns the comments with their replies, and the count on the posts', function () {
    $first = Comment::factory()->onPost($this->post)->create(['body' => 'First', 'created_at' => now()->subMinutes(3)]);
    Comment::factory()->onPost($this->post)->create(['body' => 'Second', 'created_at' => now()->subMinutes(2)]);
    Comment::factory()->replyTo($first)->create(['body' => 'Reply']);

    $this->actingAs($this->c['member'])->getJson(commentsPath($this->post))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'First')
        ->assertJsonPath('data.0.replies.0.body', 'Reply')
        ->assertJsonPath('data.0.replies.0.parent_cuid', $first->cuid)
        ->assertJsonPath('metadata.totalCount', 3);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [
        ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid,
        ApiRoutes::PARAM_SPACE => $this->space->cuid,
    ]))->assertJsonPath('data.0.comments_count', 3);
});

test('the API creates, edits and deletes comments', function () {
    $this->actingAs($this->c['member']);

    $cuid = $this->postJson(commentsPath($this->post), ['body' => 'Hello'])
        ->assertCreated()
        ->assertJsonPath('data.parent_cuid', null)
        ->assertJsonPath('data.edited_at', null)
        ->assertJsonPath('data.author.username', $this->c['member']->username)
        ->json('data.cuid');
    $comment = Comment::where('cuid', $cuid)->firstOrFail();

    $this->postJson(commentsPath($this->post), ['body' => 'Reply', 'parent_cuid' => $cuid])
        ->assertCreated()
        ->assertJsonPath('data.parent_cuid', $cuid);

    $this->patchJson(commentsPath($this->post, $comment), ['body' => 'Hello again'])->assertOk()->assertJsonPath('data.body', 'Hello again');
    $this->deleteJson(commentsPath($this->post, $comment))->assertNoContent();
    expect(Comment::count())->toBe(0);
});

test('a comment of another post gives a 404', function () {
    $foreign = Comment::factory()->onPost(Post::factory()->inSpace($this->space)->create())->create(['author_id' => $this->c['member']->id]);

    $this->actingAs($this->c['member'])->patchJson(commentsPath($this->post, $foreign), ['body' => 'x'])->assertNotFound();
});

test('the post page gets the comments and the permission to comment', function () {
    Comment::factory()->onPost($this->post)->create();

    $this->actingAs($this->c['member'])
        ->get(route('posts.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'postSlug' => $this->post->slug]))
        ->assertInertia(fn ($page) => $page->component('Posts/Show')
            ->has('comments', 1)
            ->where('post.comments_count', 1)
            ->where('permissions.canComment', true));
});

test('editing or deleting a comment of a post needs the access to its space (#1516)', function (string $route) {
    $space = Space::factory()->inGroup($this->space->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups]);
    $buyers = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Buyers']);
    $space->accessGroups()->attach($buyers);
    $buyers->members()->attach($this->c['member']->id);
    $post = Post::factory()->inSpace($space)->create(['author_id' => $this->c['admin']->id]);
    $comment = app(CreatesComments::class)->create(User::query()->findOrFail($this->c['member']->id), $post, ['body' => 'Mine']);

    $buyers->members()->detach($this->c['member']->id);
    $this->actingAs(User::query()->findOrFail($this->c['member']->id));

    ($route === 'update' ? $this->patchJson(commentsPath($post, $comment), ['body' => 'Changed']) : $this->deleteJson(commentsPath($post, $comment)))->assertForbidden();
    expect($comment->fresh()?->body)->toBe('Mine');
})->with(['update', 'delete']);

test('the comments are paged, the replies counted, and a page that starts inside a thread has its top-level comment again', function () {
    $first = Comment::factory()->onPost($this->post)->create(['body' => 'First', 'created_at' => now()->subMinutes(10)]);
    $second = Comment::factory()->onPost($this->post)->create(['body' => 'Second', 'created_at' => now()->subMinutes(9)]);
    // Replies come after the next top-level comment in time, and stay in their thread
    foreach ([1, 2, 3] as $minute) {
        Comment::factory()->replyTo($first)->create(['body' => "Reply {$minute}", 'created_at' => now()->subMinutes(5 - $minute)]);
    }
    $this->actingAs($this->c['member']);

    $page = fn (int $offset) => $this->getJson(commentsPath($this->post)."?limit=2&offset={$offset}")->assertOk();

    $one = $page(0);
    expect($one->json('data.*.body'))->toBe(['First'])
        ->and($one->json('data.0.replies.*.body'))->toBe(['Reply 1'])
        ->and($one->json('metadata.totalCount'))->toBe(5)
        ->and($one->json('metadata.pagination.nextOffset'))->toBe(2);
    $two = $page(2);
    expect($two->json('data.*.cuid'))->toBe([$first->cuid])
        ->and($two->json('data.0.replies.*.body'))->toBe(['Reply 2', 'Reply 3'])
        ->and($two->json('data.0.replies.0.parent_cuid'))->toBe($first->cuid);
    $three = $page(4);
    expect($three->json('data.*.cuid'))->toBe([$second->cuid])
        ->and($three->json('data.0.replies'))->toBe([])
        ->and($three->json('metadata.pagination.nextOffset'))->toBeNull();
});

test('one page holds at most 100 comments, and the post page gets the first page with its pagination', function () {
    Comment::factory()->count(Constants::COMMENTS_PER_PAGE + 5)->onPost($this->post)->create();
    $this->actingAs($this->c['member']);

    $list = $this->getJson(commentsPath($this->post).'?limit=1000')->assertOk();
    expect($list->json('data'))->toHaveCount(Constants::COMMENTS_PER_PAGE)
        ->and($list->json('metadata.pagination.limit'))->toBe(Constants::COMMENTS_PER_PAGE)
        ->and($list->json('metadata.pagination.nextOffset'))->toBe(Constants::COMMENTS_PER_PAGE)
        ->and($list->json('metadata.totalCount'))->toBe(Constants::COMMENTS_PER_PAGE + 5);
    // Without a limit: the first 100
    expect($this->getJson(commentsPath($this->post))->json('data'))->toHaveCount(Constants::COMMENTS_PER_PAGE);

    $this->get(route('posts.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'postSlug' => $this->post->slug]))
        ->assertInertia(fn ($page) => $page->component('Posts/Show')
            ->has('comments', Constants::COMMENTS_PER_PAGE)
            ->where('commentsPagination.nextOffset', Constants::COMMENTS_PER_PAGE)
            ->where('commentsPagination.totalCount', Constants::COMMENTS_PER_PAGE + 5));
});
