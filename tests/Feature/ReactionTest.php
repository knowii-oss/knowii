<?php

use App\ApiRoutes;
use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
    $this->post = Post::factory()->inSpace($this->space)->create();
    $this->comment = Comment::factory()->onPost($this->post)->create();
});

function reactionsPath(Post $post, ?Comment $comment = null): string
{
    return ApiRoutes::path($comment ? ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS : ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $post->community->cuid,
        ApiRoutes::PARAM_SPACE => $post->space->cuid,
        ApiRoutes::PARAM_POST => $post->cuid,
        ApiRoutes::PARAM_COMMENT => $comment?->cuid,
    ]));
}

test('a reaction toggles on and off, one of each kind per member', function () {
    $toggle = app(TogglesReactions::class);

    expect($toggle->toggle($this->c['member'], $this->post, ['kind' => 'like']))->toBe([['kind' => 'like', 'count' => 1, 'reacted' => true]]);
    $toggle->toggle($this->c['admin'], $this->post, ['kind' => 'like']);
    $toggle->toggle($this->c['member'], $this->post, ['kind' => 'celebrate']);

    expect($toggle->toggle($this->c['member'], $this->post, ['kind' => 'like']))->toBe([
        ['kind' => 'like', 'count' => 1, 'reacted' => false],
        ['kind' => 'celebrate', 'count' => 1, 'reacted' => true],
    ])->and(Reaction::count())->toBe(2);
});

test('members react to comments too', function () {
    app(TogglesReactions::class)->toggle($this->c['member'], $this->comment, ['kind' => 'funny']);

    expect(Reaction::first()->comment_id)->toBe($this->comment->id)
        ->and(Reaction::first()->post_id)->toBeNull()
        ->and(Reaction::first()->community_id)->toBe($this->c['community']->id);
});

test('a reaction points to exactly one item', function () {
    Reaction::create(['community_id' => $this->post->community_id, 'user_id' => $this->c['member']->id, 'kind' => 'like']);
})->throws(QueryException::class);

test('outsiders cannot react and the kind is a fixed set', function () {
    expect(fn () => app(TogglesReactions::class)->toggle($this->c['outsider'], $this->post, ['kind' => 'like']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TogglesReactions::class)->toggle($this->c['member'], $this->post, ['kind' => 'angry']))->toThrow(ValidationException::class);
});

test('posts and comments carry their reaction summary for the current member', function () {
    $this->post->reactions()->create(['community_id' => $this->post->community_id, 'user_id' => $this->c['admin']->id, 'kind' => 'love']);
    $this->comment->reactions()->create(['community_id' => $this->post->community_id, 'user_id' => $this->c['member']->id, 'kind' => 'like']);

    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid]))
        ->assertJsonPath('data.0.reactions', [['kind' => 'love', 'count' => 1, 'reacted' => false]]);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_POST => $this->post->cuid]))
        ->assertJsonPath('data.0.reactions', [['kind' => 'like', 'count' => 1, 'reacted' => true]]);
});

test('the API toggles reactions and lists who reacted', function () {
    $this->actingAs($this->c['member'])->postJson(reactionsPath($this->post), ['kind' => 'insightful'])
        ->assertOk()
        ->assertJsonPath('data', [['kind' => 'insightful', 'count' => 1, 'reacted' => true]]);
    $this->actingAs($this->c['admin'])->postJson(reactionsPath($this->post, $this->comment), ['kind' => 'love'])->assertOk();

    $this->getJson(reactionsPath($this->post))
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'insightful')
        ->assertJsonPath('data.0.members.0.username', $this->c['member']->username)
        ->assertJsonMissingPath('data.0.members.0.email');
    $this->getJson(reactionsPath($this->post, $this->comment))->assertJsonPath('data.0.members.0.username', $this->c['admin']->username);

    $this->actingAs($this->c['outsider'])->postJson(reactionsPath($this->post), ['kind' => 'like'])->assertForbidden();
});

test('reactions go away with their comment, and with the comments of a deleted post', function () {
    app(TogglesReactions::class)->toggle($this->c['member'], $this->post, ['kind' => 'like']);
    app(TogglesReactions::class)->toggle($this->c['member'], $this->comment, ['kind' => 'like']);
    $other = Comment::factory()->onPost($this->post)->create();
    app(TogglesReactions::class)->toggle($this->c['member'], $other, ['kind' => 'like']);

    $this->comment->delete();
    expect(Reaction::count())->toBe(2);

    // The database deletes the comments of the post, then their reactions
    $this->post->delete();
    expect(Reaction::count())->toBe(0);
});
