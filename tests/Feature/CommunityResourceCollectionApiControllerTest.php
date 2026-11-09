<?php

use App\ApiRoutes;
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
