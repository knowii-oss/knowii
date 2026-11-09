<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Resources]);
    $this->resource = CommunityResource::factory()->create(['space_id' => $this->space->id]);
});

function resourceCommentsPath(CommunityResource $resource, ?Comment $comment = null, string $suffix = ''): string
{
    return ApiRoutes::path($comment ? ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT : ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, array_filter([
        ApiRoutes::PARAM_COMMUNITY => $resource->community->cuid,
        ApiRoutes::PARAM_SPACE => $resource->space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
        ApiRoutes::PARAM_RESOURCE_COMMENT => $comment?->cuid,
    ])).$suffix;
}

test('members comment a resource and reply, through the API (#1034)', function () {
    $this->actingAs($this->c['member']);

    $created = $this->postJson(resourceCommentsPath($this->resource), ['body' => 'Worth **reading** <script>x</script>'])->assertCreated();
    expect($created->json('data.body_html'))->toContain('<strong>reading</strong>')->not->toContain('<script>');

    $this->postJson(resourceCommentsPath($this->resource), ['body' => 'Agreed', 'parent_cuid' => $created->json('data.cuid')])->assertCreated();

    $list = $this->getJson(resourceCommentsPath($this->resource))->assertOk();
    expect($list->json('metadata.totalCount'))->toBe(2)
        ->and($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.replies.0.body'))->toBe('Agreed');

    $comment = Comment::whereCuid($created->json('data.cuid'))->firstOrFail();
    expect($comment->community_resource_id)->toBe($this->resource->id)
        ->and($comment->post_id)->toBeNull()
        ->and($comment->community_id)->toBe($this->c['community']->id);
});

test('outsiders neither read nor write the comments of a resource (#1034)', function () {
    $this->actingAs($this->c['outsider']);

    $this->getJson(resourceCommentsPath($this->resource))->assertForbidden();
    $this->postJson(resourceCommentsPath($this->resource), ['body' => 'Hello'])->assertForbidden();
});

test('authors edit their comments, moderators delete any comment (#1034)', function () {
    $comment = app(CreatesComments::class)->create($this->c['member'], $this->resource, ['body' => 'First']);

    $this->actingAs($this->c['admin'])->patchJson(resourceCommentsPath($this->resource, $comment), ['body' => 'Hijack'])->assertForbidden();
    $this->actingAs($this->c['member'])->patchJson(resourceCommentsPath($this->resource, $comment), ['body' => 'Edited'])
        ->assertOk()->assertJsonPath('data.body', 'Edited');
    $this->actingAs($this->c['moderator'])->deleteJson(resourceCommentsPath($this->resource, $comment))->assertNoContent();

    expect(Comment::query()->count())->toBe(0);
});

test('members react to the comments of a resource (#1034)', function () {
    $comment = app(CreatesComments::class)->create($this->c['member'], $this->resource, ['body' => 'First']);

    $this->actingAs($this->c['admin'])->postJson(resourceCommentsPath($this->resource, $comment, '/reactions'), ['kind' => 'like'])
        ->assertOk()->assertJsonPath('data', [['kind' => 'like', 'count' => 1, 'reacted' => true]]);
    $this->getJson(resourceCommentsPath($this->resource, $comment, '/reactions'))->assertOk();
    $this->actingAs($this->c['outsider'])->postJson(resourceCommentsPath($this->resource, $comment, '/reactions'), ['kind' => 'like'])->assertForbidden();
});

test('a comment is only reachable through its own resource (#1034)', function () {
    $comment = app(CreatesComments::class)->create($this->c['member'], $this->resource, ['body' => 'First']);
    $other = CommunityResource::factory()->create(['space_id' => $this->space->id]);
    $post = Post::factory()->inSpace(Space::factory()->inGroup($this->space->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion]))->create();
    $postComment = app(CreatesComments::class)->create($this->c['member'], $post, ['body' => 'On a post']);

    $this->actingAs($this->c['member']);
    $this->patchJson(resourceCommentsPath($other, $comment), ['body' => 'x'])->assertNotFound();
    $this->patchJson(resourceCommentsPath($this->resource, $postComment), ['body' => 'x'])->assertNotFound();
    $this->postJson(resourceCommentsPath($other), ['body' => 'x', 'parent_cuid' => $comment->cuid])->assertStatus(400);
});

test('a comment belongs to exactly one post or one resource (#1034, ADR 0014)', function () {
    Comment::query()->insert([
        'cuid' => 'nothing',
        'community_id' => $this->c['community']->id,
        'author_id' => $this->c['member']->id,
        'body' => 'x',
        'body_html' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

test('deleting a resource deletes its comments (#1034)', function () {
    app(CreatesComments::class)->create($this->c['member'], $this->resource, ['body' => 'First']);

    $this->resource->delete();

    expect(Comment::query()->count())->toBe(0);
});

test('the resource page carries its comments and the comment permissions (#1034)', function () {
    app(CreatesComments::class)->create($this->c['member'], $this->resource, ['body' => 'First']);

    $this->actingAs($this->c['member'])
        ->get(route('resources.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'resourceSlug' => $this->resource->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('comments', 1)
            ->where('permissions.canComment', true)
            ->where('permissions.canModerate', false));
});
