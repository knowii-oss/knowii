<?php

namespace Tests\Feature;

use App\Actions\CommunityResourceCollections\CreateCommunityResourceCollection;
use App\Models\User;
use App\Constants;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resource collections can be created via the creator', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());


    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => 'Test collection',
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $creator->create($user, $community, $input);

    expect($community->resourceCollections)->toHaveCount(1);
    expect($community->resourceCollections()->latest('id')->first()->name)->toEqual('Test collection');
    expect($community->resourceCollections()->latest('id')->first()->description)->toEqual('Awesome collection');
});

test('creation is rejected by the creator if the name is too short', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => 'a',
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('creation is rejected by the creator if the name is too long', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => str_repeat('a', Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME+1),
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('creation is rejected by the creator if the name contains forbidden characters', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => 'foo ##!}{',
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('creation is rejected by the creator if the description is too long', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => 'foo',
      'description' => str_repeat('a', Constants::$MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION+1),
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('creation is rejected by the creator if the name is missing', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('creation is rejected by the creator if the name is empty', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input = [
      'name' => '',
      'description' => 'Awesome collection',
    ];

    $creator = new CreateCommunityResourceCollection();

    $this->expectException(ValidationException::class);

    $creator->create($user, $community, $input);
});

test('resource collection slug generation avoids duplicate slugs', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);
    $community = $user->ownedCommunities()->first();

    $input1 = [
      'name' => 'Test',
      'description' => 'Awesome collection',
    ];

    $input2 = [
        'name' => 'Test',
        'description' => 'Awesome collection',
      ];

    $creator = new CreateCommunityResourceCollection();

    $creator->create($user, $community, $input1);
    $creator->create($user, $community, $input2);

    expect($community->fresh()->resourceCollections)->toHaveCount(2);

    $collection1 = $community->resourceCollections()->latest('id')->skip(1)->first();
    $collection2 = $community->resourceCollections()->latest('id')->first();
    expect($collection1->slug)->toEqual('test');
    expect($collection2->slug)->toEqual('test-2');
});
