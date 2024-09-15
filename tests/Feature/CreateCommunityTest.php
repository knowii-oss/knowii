<?php

use App\Actions\Communities\CreateCommunity;
use App\KnowiiCommunityVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('communities can be created via the creator', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    expect($user->ownedCommunities)->toHaveCount(1);

    $input = [
      'name' => 'Test Community',
      'description' => 'Awesome community',
      'visibility' => KnowiiCommunityVisibility::Public->value,
    ];

    $creator = new CreateCommunity();

    $creator->create($user, $input);

    expect($user->fresh()->ownedCommunities)->toHaveCount(2);
    expect($user->ownedCommunities()->latest('id')->first()->name)->toEqual('Test Community');
    expect($user->ownedCommunities()->latest('id')->first()->description)->toEqual('Awesome community');
    expect($user->ownedCommunities()->latest('id')->first()->visibility)->toEqual(KnowiiCommunityVisibility::Public);
});
