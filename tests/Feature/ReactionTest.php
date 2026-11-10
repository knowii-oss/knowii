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
use App\Support\Emoji;
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

test('a reaction toggles on and off, one of each emoji per member', function () {
    $toggle = app(TogglesReactions::class);

    expect($toggle->toggle($this->c['member'], $this->post, ['emoji' => '👍']))->toBe([['emoji' => '👍', 'count' => 1, 'reacted' => true]]);
    $toggle->toggle($this->c['admin'], $this->post, ['emoji' => '👍']);
    $toggle->toggle($this->c['member'], $this->post, ['emoji' => '🎉']);

    expect($toggle->toggle($this->c['member'], $this->post, ['emoji' => '👍']))->toBe([
        ['emoji' => '👍', 'count' => 1, 'reacted' => false],
        ['emoji' => '🎉', 'count' => 1, 'reacted' => true],
    ])->and(Reaction::count())->toBe(2);
});

test('members react to comments too', function () {
    app(TogglesReactions::class)->toggle($this->c['member'], $this->comment, ['emoji' => '😄']);

    expect(Reaction::first()->comment_id)->toBe($this->comment->id)
        ->and(Reaction::first()->post_id)->toBeNull()
        ->and(Reaction::first()->community_id)->toBe($this->c['community']->id);
});

test('a reaction points to exactly one item', function () {
    Reaction::create(['community_id' => $this->post->community_id, 'user_id' => $this->c['member']->id, 'emoji' => '👍']);
})->throws(QueryException::class);

test('outsiders cannot react, and the emoji comes from the allow-list', function () {
    expect(fn () => app(TogglesReactions::class)->toggle($this->c['outsider'], $this->post, ['emoji' => '👍']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TogglesReactions::class)->toggle($this->c['member'], $this->post, ['emoji' => 'angry']))->toThrow(ValidationException::class);
});

test('posts and comments carry their reaction summary for the current member', function () {
    $this->post->reactions()->create(['community_id' => $this->post->community_id, 'user_id' => $this->c['admin']->id, 'emoji' => '❤️']);
    $this->comment->reactions()->create(['community_id' => $this->post->community_id, 'user_id' => $this->c['member']->id, 'emoji' => '👍']);

    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid]))
        ->assertJsonPath('data.0.reactions', [['emoji' => '❤️', 'count' => 1, 'reacted' => false]]);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->space->cuid, ApiRoutes::PARAM_POST => $this->post->cuid]))
        ->assertJsonPath('data.0.reactions', [['emoji' => '👍', 'count' => 1, 'reacted' => true]]);
});

test('the API toggles reactions and lists who reacted', function () {
    $this->actingAs($this->c['member'])->postJson(reactionsPath($this->post), ['emoji' => '💡'])
        ->assertOk()
        ->assertJsonPath('data', [['emoji' => '💡', 'count' => 1, 'reacted' => true]]);
    $this->actingAs($this->c['admin'])->postJson(reactionsPath($this->post, $this->comment), ['emoji' => '❤️'])->assertOk();

    $this->getJson(reactionsPath($this->post))
        ->assertOk()
        ->assertJsonPath('data.0.emoji', '💡')
        ->assertJsonPath('data.0.members.0.username', $this->c['member']->username)
        ->assertJsonMissingPath('data.0.members.0.email');
    $this->getJson(reactionsPath($this->post, $this->comment))->assertJsonPath('data.0.members.0.username', $this->c['admin']->username);

    $this->actingAs($this->c['outsider'])->postJson(reactionsPath($this->post), ['emoji' => '👍'])->assertForbidden();
});

test('reactions go away with their comment, and with the comments of a deleted post', function () {
    app(TogglesReactions::class)->toggle($this->c['member'], $this->post, ['emoji' => '👍']);
    app(TogglesReactions::class)->toggle($this->c['member'], $this->comment, ['emoji' => '👍']);
    $other = Comment::factory()->onPost($this->post)->create();
    app(TogglesReactions::class)->toggle($this->c['member'], $other, ['emoji' => '👍']);

    $this->comment->delete();
    expect(Reaction::count())->toBe(2);

    // The database deletes the comments of the post, then their reactions
    $this->post->delete();
    expect(Reaction::count())->toBe(0);
});

test('any emoji of the picker works, with a skin tone, and new emoji come last (#1204)', function () {
    $toggle = app(TogglesReactions::class);
    $toggle->toggle($this->c['member'], $this->post, ['emoji' => '🦄']);
    $toggle->toggle($this->c['admin'], $this->post, ['emoji' => '👍🏽']);

    expect($toggle->toggle($this->c['member'], $this->post, ['emoji' => '🦄']))->toBe([['emoji' => '👍🏽', 'count' => 1, 'reacted' => false]])
        ->and(fn () => $toggle->toggle($this->c['member'], $this->post, ['emoji' => '🦄🦄']))->toThrow(ValidationException::class)
        ->and(fn () => $toggle->toggle($this->c['member'], $this->post, ['emoji' => 'a']))->toThrow(ValidationException::class)
        ->and(fn () => $toggle->toggle($this->c['member'], $this->post, []))->toThrow(ValidationException::class);
});

test('an item takes 20 different emoji at most; more of an existing emoji still works (#1204)', function () {
    $emoji = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚'];
    foreach ($emoji as $one) {
        $this->post->reactions()->create(['community_id' => $this->post->community_id, 'user_id' => $this->c['admin']->id, 'emoji' => $one]);
    }

    $this->actingAs($this->c['member'])->postJson(reactionsPath($this->post), ['emoji' => '🦄'])->assertStatus(422);
    $this->actingAs($this->c['member'])->postJson(reactionsPath($this->post), ['emoji' => '😀'])->assertOk()
        ->assertJsonPath('data.0', ['emoji' => '😀', 'count' => 2, 'reacted' => true]);
});

test('the frequently used emoji of a member: most used first, then most recent, only theirs (#1204)', function () {
    $toggle = app(TogglesReactions::class);
    $toggle->toggle($this->c['member'], $this->post, ['emoji' => '🎉']);
    $toggle->toggle($this->c['member'], $this->comment, ['emoji' => '🎉']);
    $this->travel(1)->minutes();
    $toggle->toggle($this->c['member'], $this->post, ['emoji' => '🦄']);
    $this->travel(1)->minutes();
    $toggle->toggle($this->c['member'], $this->comment, ['emoji' => '👍']);
    $toggle->toggle($this->c['admin'], $this->post, ['emoji' => '🔥']);

    $this->actingAs($this->c['member'])->getJson('/api/v1/user/frequent-emoji')
        ->assertOk()
        ->assertJsonPath('data', ['🎉', '👍', '🦄']);
});

test('the five former kinds become their emoji (#1204)', function () {
    expect(Emoji::QUICK)->toBe(['👍', '❤️', '💡', '😄', '🎉']);
    foreach (Emoji::QUICK as $quick) {
        expect(Emoji::isAllowed($quick))->toBeTrue();
    }
});
