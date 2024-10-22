<?php

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

  $requestUrl = 'api/v1/communities/' . $community->cuid . '/resource-collections';

  // TODO stop hardcoding URLs in tests
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

  // TODO stop hardcoding URLs in tests
  $requestUrl = 'api/v1/communities/' . $community->cuid . '/resource-collections/' . $resourceCollection->cuid;

  $response = $this->delete($requestUrl, [
    'Accept' => 'application/json',
  ]);

  $response->assertStatus(Response::HTTP_NO_CONTENT);

  expect($community->fresh()->communityResourceCollections)->toHaveCount(0);
});
