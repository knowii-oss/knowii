<?php

use App\Enums\KnowiiCommunityVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

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
