<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Jobs\CaptureCommunityResource;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Create a resources space in the personal community of the owner, and return the API path to add resources to it.
 */
function textArticlesPathFor(User $owner, KnowiiSpaceType $type = KnowiiSpaceType::Resources): string
{
    $community = $owner->ownedCommunities()->first();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
        ->create(['type' => $type]);

    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]);
}

test('the resources API requires authentication', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('the resources API rejects a request from an unauthorized user', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);

    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthorizationIssue->value);
});

test('the resources API returns not found for an unknown community', function () {
    $this->actingAs(User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => 'does-not-exist',
        ApiRoutes::PARAM_SPACE => 'does-not-exist',
    ]);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::NotFound->value);
});

test('the resources API rejects a name that is too short with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'ab',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the resources API rejects an invalid url with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'not-a-valid-url',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the resources API rejects an invalid level with a validation issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => 'super-hard',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('resources can be created via the API: the answer comes at once and the capture is queued (ADR 0018)', function () {
    Queue::fake();
    Http::preventStrayRequests();
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner);
    $this->actingAs($owner);

    $response = $this->json('POST', $path, [
        'name' => 'Some Article',
        'description' => 'Worth reading',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_CREATED);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value)
        ->and($response->json('data.name'))->toBe('Some Article')
        ->and($response->json('data.description'))->toBe('Worth reading')
        ->and($response->json('data.capture_status'))->toBe('pending')
        ->and($response->json('data.space.type'))->toBe(KnowiiSpaceType::Resources->value)
        ->and($response->json('data.resource.url'))->toBe('https://example.com/some-article');
    Queue::assertPushed(CaptureCommunityResource::class);
});

test('video links are accepted: the domain list is gone (ADR 0018)', function () {
    Queue::fake();
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $this->actingAs($owner)->postJson(textArticlesPathFor($owner), [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'level' => KnowiiResourceLevel::Beginner->value,
    ])->assertCreated();
});

test('the resources API refuses a space of another community (#1213)', function () {
    // The attacker owns a personal community, so they may add resources there
    $attacker = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $victim = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $victimPath = textArticlesPathFor($victim);
    $victimSpaceCuid = explode('/', $victimPath)[5];

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $attacker->ownedCommunities()->first()->cuid,
        ApiRoutes::PARAM_SPACE => $victimSpaceCuid,
    ]);

    $this->actingAs($attacker)->postJson($path, [
        'name' => 'Injected',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ])->assertNotFound();

    expect(CommunityResource::query()->count())->toBe(0);
});

test('the resources API refuses a space of another type with a business issue', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $path = textArticlesPathFor($owner, KnowiiSpaceType::Discussion);

    $response = $this->actingAs($owner)->postJson($path, [
        'name' => 'Some Article',
        'url' => 'https://example.com/some-article',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::BusinessIssue->value);
});

/**
 * A resources space of a community with members, and its resources (oldest first).
 *
 * @return array{0: array{community: Community, owner: User, admin: User, moderator: User, member: User, outsider: User}, 1: Space, 2: list<CommunityResource>}
 */
function resourcesSpaceWithResources(int $count): array
{
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    $resources = [];
    for ($i = 0; $i < $count; $i++) {
        $resources[] = CommunityResource::factory()->create([
            'space_id' => $space->id,
            'created_at' => now()->subMinutes($count - $i),
        ]);
    }

    return [$c, $space, $resources];
}

function resourcesListPath(Space $space): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]);
}

test('members list the resources of a space, newest first, with offset pagination (#877)', function () {
    [$c, $space, $resources] = resourcesSpaceWithResources(3);

    $response = $this->actingAs($c['member'])->getJson(resourcesListPath($space).'?limit=2&offset=0')->assertOk();

    expect($response->json('data.*.cuid'))->toBe([$resources[2]->cuid, $resources[1]->cuid])
        ->and($response->json('metadata.pagination.totalCount'))->toBe(3)
        ->and($response->json('metadata.pagination.nextOffset'))->toBe(2);

    $next = $this->getJson(resourcesListPath($space).'?limit=2&offset=2')->assertOk();
    expect($next->json('data.*.cuid'))->toBe([$resources[0]->cuid])
        ->and($next->json('metadata.pagination.nextOffset'))->toBeNull();
});

test('the resources list never carries the large fields (#877)', function () {
    [$c, $space] = resourcesSpaceWithResources(1);

    $response = $this->actingAs($c['member'])->getJson(resourcesListPath($space))->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('capture')
        ->and($response->json('data.0.capture_status'))->toBe('captured')
        ->and($response->json('data.0.level'))->toBe('beginner')
        ->and($response->json('data.0.type'))->toBe('textArticle')
        ->and($response->json('data.0.resource.url'))->not->toBeEmpty();
});

test('only the readers of a space list its resources (#877)', function () {
    [$c, $space] = resourcesSpaceWithResources(1);

    $this->getJson(resourcesListPath($space))->assertUnauthorized();
    $this->actingAs($c['outsider'])->getJson(resourcesListPath($space))->assertForbidden();
});

test('the resources list refuses a space of another community (#877)', function () {
    [$c, $space] = resourcesSpaceWithResources(1);
    $other = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $other->ownedCommunities()->first()->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]);

    $this->actingAs($other)->getJson($path)->assertNotFound();
});

function resourcePath(Space $space, CommunityResource $resource): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);
}

test('members get one resource with its large fields (#876)', function () {
    [$c, $space, $resources] = resourcesSpaceWithResources(1);
    ResourceCapture::factory()->create(['community_resource_id' => $resources[0]->id, 'markdown' => 'Body']);

    $response = $this->actingAs($c['member'])->getJson(resourcePath($space, $resources[0]))->assertOk();

    expect($response->json('data.cuid'))->toBe($resources[0]->cuid)
        ->and($response->json('data.capture.markdown'))->toBe('Body')
        ->and($response->json('data.capture.source'))->toBe('server')
        ->and($response->json('data.space.cuid'))->toBe($space->cuid);
});

test('only the readers of a space get its resources (#876)', function () {
    [$c, $space, $resources] = resourcesSpaceWithResources(1);

    $this->getJson(resourcePath($space, $resources[0]))->assertUnauthorized();
    $this->actingAs($c['outsider'])->getJson(resourcePath($space, $resources[0]))->assertForbidden();
});

test('a resource of another space gives a 404 (#876)', function () {
    [$c, $space, $resources] = resourcesSpaceWithResources(1);
    $otherSpace = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    $this->actingAs($c['member'])->getJson(resourcePath($otherSpace, $resources[0]))->assertNotFound();
});

test('resources expose only the public summary of their curator (#1234)', function () {
    [$c, $space, $resources] = resourcesSpaceWithResources(1);
    $resources[0]->curator->forceFill(['phone' => '+32 470 00 00 00', 'location' => 'Brussels', 'bio' => 'Private bio'])->save();

    foreach ([resourcesListPath($space) => 'data.0.curator', resourcePath($space, $resources[0]) => 'data.curator'] as $path => $key) {
        $curator = $this->actingAs($c['member'])->getJson($path)->assertOk()->json($key);

        expect(array_keys($curator))->toEqualCanonicalizing(['cuid', 'name', 'username', 'avatar_url']);
    }
});
