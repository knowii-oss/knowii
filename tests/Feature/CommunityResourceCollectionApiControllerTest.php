<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\User;
use Illuminate\Http\Response;

test('resource collections can be created via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
        'name' => 'Test',
        'description' => 'Awesome collection',
    ];

    $requestUrl = ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    $response = $this->json('POST', $requestUrl, $input, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_CREATED);

    expect($community->communityResourceCollections)->toHaveCount(1);
    expect($community->communityResourceCollections()->latest('id')->first()->name)->toEqual('Test');
    expect($community->communityResourceCollections()->latest('id')->first()->description)->toEqual('Awesome collection');
});

test('resource collections can be deleted via the API', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $community = $user->ownedCommunities()->first();

    expect($community->communityResourceCollections)->toHaveCount(0);

    $resourceCollection = $community->communityResourceCollections()->create([
        'name' => 'Foo',
        'description' => 'Bar',
    ]);

    $community = $community->fresh();

    expect($community->communityResourceCollections)->toHaveCount(1);

    $requestUrl = ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTION, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_COMMUNITY_RESOURCE_COLLECTION => $resourceCollection->cuid,
    ]);

    $response = $this->delete($requestUrl, [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_NO_CONTENT);

    expect($community->fresh()->communityResourceCollections)->toHaveCount(0);
});

test('a collection can be created without a description (#1213)', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());
    $community = $user->ownedCommunities()->first();

    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]), ['name' => 'No description'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'No description');
});

test('a collection of another community cannot be deleted through this community (#1213)', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());
    $other = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $foreign = $other->ownedCommunities()->first()->communityResourceCollections()->create(['name' => 'Foreign', 'description' => null]);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTION, [
        ApiRoutes::PARAM_COMMUNITY => $user->ownedCommunities()->first()->cuid,
        ApiRoutes::PARAM_COMMUNITY_RESOURCE_COLLECTION => $foreign->cuid,
    ]))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

function resourceCollectionsPath(Community $community, ?CommunityResourceCollection $collection = null): string
{
    return $collection === null
        ? ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid])
        : ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTION, [
            ApiRoutes::PARAM_COMMUNITY => $community->cuid,
            ApiRoutes::PARAM_COMMUNITY_RESOURCE_COLLECTION => $collection->cuid,
        ]);
}

test('the create API returns the collection with every field of the client schema', function () {
    $c = communityWithMembers();

    $this->actingAs($c['moderator'])
        ->postJson(resourceCollectionsPath($c['community']), ['name' => 'Reading list', 'description' => null])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Reading list')
        ->assertJsonPath('data.slug', 'reading-list')
        ->assertJsonStructure(['data' => ['cuid', 'name', 'slug', 'description', 'created_at', 'updated_at']])
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.community_id');
});

test('owners, admins and moderators create and delete collections; members and outsiders cannot', function (string $role, int $status) {
    $c = communityWithMembers();
    $existing = CommunityResourceCollection::factory()->create(['community_id' => $c['community']->id]);

    $this->actingAs($c[$role])->postJson(resourceCollectionsPath($c['community']), ['name' => 'Links'])->assertStatus($status);
    $this->actingAs($c[$role])->deleteJson(resourceCollectionsPath($c['community'], $existing))
        ->assertStatus($status === Response::HTTP_CREATED ? Response::HTTP_NO_CONTENT : $status);
})->with([
    'owner' => ['owner', Response::HTTP_CREATED],
    'admin' => ['admin', Response::HTTP_CREATED],
    'moderator' => ['moderator', Response::HTTP_CREATED],
    'member' => ['member', Response::HTTP_FORBIDDEN],
    'outsider' => ['outsider', Response::HTTP_FORBIDDEN],
]);

test('the create API validates the input', function (array $input) {
    $c = communityWithMembers();

    $response = $this->actingAs($c['owner'])->postJson(resourceCollectionsPath($c['community']), $input)
        ->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value)
        ->and($c['community']->communityResourceCollections()->count())->toBe(0);
})->with([
    'missing name' => [['description' => 'No name']],
    'name too short' => [['name' => str_repeat('a', Constants::MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME - 1)]],
    'name too long' => [['name' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME + 1)]],
    'forbidden characters' => [['name' => 'Links <script>']],
    'description too long' => [['name' => 'Links', 'description' => str_repeat('a', Constants::MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION + 1)]],
]);

test('the collection API requires authentication', function () {
    $c = communityWithMembers();
    $collection = CommunityResourceCollection::factory()->create(['community_id' => $c['community']->id]);

    $this->postJson(resourceCollectionsPath($c['community']), ['name' => 'Links'])->assertUnauthorized();
    $this->deleteJson(resourceCollectionsPath($c['community'], $collection))->assertUnauthorized();
});

test('the collection API returns 404 for an unknown community', function () {
    $c = communityWithMembers();

    $this->actingAs($c['owner'])->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_RESOURCE_COLLECTIONS, [ApiRoutes::PARAM_COMMUNITY => 'unknown']), ['name' => 'Links'])
        ->assertNotFound();
});
