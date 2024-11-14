<?php

use App\Contracts\CommunityResourceCollections\DeletesCommunityResourceCollections;
use App\Models\User;

test('resource collections can be deleted', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

    $community = $user->ownedCommunities()->first();

    expect($community->communityResourceCollections)->toHaveCount(0);

    $collection = $community->communityResourceCollections()->create([
        'name' => 'Foo',
        'description' => 'Bar',
    ]);

    expect($community->fresh()->communityResourceCollections)->toHaveCount(1);

    $deleter = app(DeletesCommunityResourceCollections::class);
    $deleter->delete($user, $collection);

    expect($community->fresh()->communityResourceCollections)->toHaveCount(0);
});
