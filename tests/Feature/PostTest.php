<?php

use App\ApiRoutes;
use App\Constants;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
    $this->announcements = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'posting_policy' => KnowiiSpacePostingPolicy::Moderators]);
});

function postsPath(Space $space, ?Post $post = null, string $route = ApiRoutes::COMMUNITY_SPACE_POSTS): string
{
    return ApiRoutes::path($route, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_POST => $post?->cuid,
    ]));
}

test('members write posts with sanitized Markdown', function () {
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, [
        'title' => '  Hello world  ',
        'body' => 'Hi **all** <script>alert(1)</script>',
    ]);

    expect($post->title)->toBe('Hello world')
        ->and($post->slug)->toBe('hello-world')
        ->and($post->author_id)->toBe($this->c['member']->id)
        ->and($post->community_id)->toBe($this->c['community']->id)
        ->and($post->body_html)->toContain('<strong>all</strong>')
        ->and($post->body_html)->not->toContain('<script>');
});

test('outsiders cannot post, and members cannot post in moderators-only spaces', function () {
    expect(fn () => app(CreatesPosts::class)->create($this->c['outsider'], $this->space, ['title' => 'Hey there', 'body' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreatesPosts::class)->create($this->c['member'], $this->announcements, ['title' => 'News!', 'body' => 'x']))
        ->toThrow(AuthorizationException::class);

    $post = app(CreatesPosts::class)->create($this->c['moderator'], $this->announcements, ['title' => 'News!', 'body' => 'x']);
    expect($post->exists)->toBeTrue();
});

test('posts only go to discussion spaces', function () {
    $chat = Space::factory()->inGroup($this->space->spaceGroup)->create(['type' => KnowiiSpaceType::Chat]);

    app(CreatesPosts::class)->create($this->c['member'], $chat, ['title' => 'Hello', 'body' => 'x']);
})->throws(BusinessException::class);

test('a post needs a title and a body', function (array $input) {
    app(CreatesPosts::class)->create($this->c['member'], $this->space, $input);
})->with([
    'no title' => [['body' => 'x']],
    'short title' => [['title' => 'ab', 'body' => 'x']],
    'empty body' => [['title' => 'Hello', 'body' => '']],
    'long body' => [['title' => 'Hello', 'body' => str_repeat('a', 50001)]],
])->throws(ValidationException::class);

test('slugs are unique within a space', function () {
    $creator = app(CreatesPosts::class);
    $first = $creator->create($this->c['member'], $this->space, ['title' => 'Same title', 'body' => 'x']);
    $second = $creator->create($this->c['member'], $this->space, ['title' => 'Same title', 'body' => 'y']);
    $elsewhere = $creator->create($this->c['moderator'], $this->announcements, ['title' => 'Same title', 'body' => 'z']);

    expect([$first->slug, $second->slug, $elsewhere->slug])->toBe(['same-title', 'same-title-2', 'same-title']);
});

test('only the author edits a post; the slug stays and the edit is marked', function () {
    $post = Post::factory()->inSpace($this->space)->create(['author_id' => $this->c['member']->id, 'title' => 'First']);

    expect(fn () => app(UpdatesPosts::class)->update($this->c['moderator'], $post, ['title' => 'Hijacked']))->toThrow(AuthorizationException::class);

    $updated = app(UpdatesPosts::class)->update($this->c['member'], $post, ['title' => 'Second', 'body' => '_new_']);

    expect($updated->title)->toBe('Second')
        ->and($updated->slug)->toBe($post->slug)
        ->and($updated->body_html)->toContain('<em>new</em>')
        ->and($updated->edited_at)->not->toBeNull();
});

test('the author or a moderator deletes a post, other members cannot', function () {
    $post = Post::factory()->inSpace($this->space)->create(['author_id' => $this->c['member']->id]);
    $other = Post::factory()->inSpace($this->space)->create(['author_id' => $this->c['member']->id]);

    expect(fn () => app(DeletesPosts::class)->delete($this->c['outsider'], $post))->toThrow(AuthorizationException::class);

    app(DeletesPosts::class)->delete($this->c['member'], $post);
    app(DeletesPosts::class)->delete($this->c['moderator'], $other);

    expect(Post::count())->toBe(0);
});

test('moderators pin posts, members cannot', function () {
    $post = Post::factory()->inSpace($this->space)->create();

    expect(fn () => app(PinsPosts::class)->pin($this->c['member'], $post, ['pinned' => true]))->toThrow(AuthorizationException::class);

    app(PinsPosts::class)->pin($this->c['moderator'], $post, ['pinned' => true]);
    expect($post->fresh()->is_pinned)->toBeTrue()->and($post->fresh()->pinned_at)->not->toBeNull();

    app(PinsPosts::class)->pin($this->c['moderator'], $post, ['pinned' => false]);
    expect($post->fresh()->is_pinned)->toBeFalse()->and($post->fresh()->pinned_at)->toBeNull();
});

test('the API lists posts pinned first, then newest, with the pagination of the guide', function () {
    $old = Post::factory()->inSpace($this->space)->create(['title' => 'Old', 'created_at' => now()->subDays(3)]);
    Post::factory()->inSpace($this->space)->create(['title' => 'New', 'created_at' => now()->subDay()]);
    Post::factory()->inSpace($this->space)->create(['title' => 'Newest', 'created_at' => now()]);
    $old->update(['is_pinned' => true, 'pinned_at' => now()]);

    $response = $this->actingAs($this->c['member'])->getJson(postsPath($this->space).'?limit=2')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Old')
        ->assertJsonPath('data.1.title', 'Newest')
        ->assertJsonMissingPath('data.0.body')
        ->assertJsonPath('metadata.pagination.totalCount', 3)
        ->assertJsonPath('metadata.pagination.nextOffset', 2)
        ->assertJsonPath('metadata.pagination.pageCount', 2);

    $this->getJson(postsPath($this->space).'?limit=2&offset=2')
        ->assertJsonPath('data.0.title', 'New')
        ->assertJsonPath('metadata.pagination.nextOffset', null)
        ->assertJsonPath('metadata.pagination.previousOffset', 0);
});

test('the API creates, reads, edits, pins and deletes a post', function () {
    $this->actingAs($this->c['member']);

    $cuid = $this->postJson(postsPath($this->space), ['title' => 'My first post', 'body' => 'Hello'])
        ->assertCreated()
        ->assertJsonPath('data.author.username', $this->c['member']->username)
        ->assertJsonPath('data.body', 'Hello')
        // The client schema needs every field, also the ones with a database default
        ->assertJsonPath('data.is_pinned', false)
        ->assertJsonPath('data.pinned_at', null)
        ->assertJsonPath('data.edited_at', null)
        ->assertJsonMissingPath('data.author.email')
        ->json('data.cuid');
    $post = Post::where('cuid', $cuid)->firstOrFail();

    $this->getJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST))->assertOk()->assertJsonPath('data.body_html', '<p>Hello</p>');
    $this->patchJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST), ['body' => 'Edited'])->assertOk()->assertJsonPath('data.body', 'Edited');
    $this->postJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST_PIN), ['pinned' => true])->assertForbidden();

    $this->actingAs($this->c['moderator'])->postJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST_PIN), ['pinned' => true])
        ->assertOk()
        ->assertJsonPath('data.is_pinned', true);

    $this->actingAs($this->c['member'])->deleteJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST))->assertNoContent();
});

test('a post of another space gives a 404', function () {
    $post = Post::factory()->inSpace($this->announcements)->create();

    $this->actingAs($this->c['member'])->getJson(postsPath($this->space, $post, ApiRoutes::COMMUNITY_SPACE_POST))->assertNotFound();
});

test('outsiders cannot read posts of members-only spaces', function () {
    $this->space->update(['visibility' => KnowiiSpaceVisibility::Members]);

    $this->actingAs($this->c['outsider'])->getJson(postsPath($this->space))->assertForbidden();
});

test('posting is rate limited', function () {
    RateLimiter::clear('posts');
    $this->actingAs($this->c['member']);

    foreach (range(1, Constants::POSTS_PER_MINUTE) as $i) {
        $this->postJson(postsPath($this->space), ['title' => "Post number {$i}", 'body' => 'x'])->assertCreated();
    }

    $this->postJson(postsPath($this->space), ['title' => 'One too many', 'body' => 'x'])->assertStatus(429);
});

test('the Markdown preview uses the same rendering', function () {
    $this->actingAs($this->c['member'])
        ->postJson(ApiRoutes::path(ApiRoutes::MARKDOWN_PREVIEW), ['markdown' => '**hi** <b>x</b>'])
        ->assertOk()
        ->assertJsonPath('data.html', '<p><strong>hi</strong> &lt;b&gt;x&lt;/b&gt;</p>');
});

test('the space page lists the first posts and the post page shows a post', function () {
    $post = Post::factory()->inSpace($this->space)->create(['author_id' => $this->c['member']->id]);

    $this->actingAs($this->c['member'])
        ->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug]))
        ->assertInertia(fn ($page) => $page->component('Spaces/Show')->has('posts.items', 1)->where('posts.pagination.totalCount', 1));

    $this->get(route('posts.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'postSlug' => $post->slug]))
        ->assertInertia(fn ($page) => $page->component('Posts/Show')
            ->where('post.cuid', $post->cuid)
            ->where('permissions.canUpdatePost', true)
            ->where('permissions.canPinPost', false));
});
