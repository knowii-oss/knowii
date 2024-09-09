<?php

use App\Models\User;

test('communities can be created', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $this->post('/communities', [
        'name' => 'Test Community',
        'description' => 'Awesome community',
    ]);

    expect($user->fresh()->ownedCommunities)->toHaveCount(2);
    expect($user->fresh()->ownedCommunities()->latest('id')->first()->name)->toEqual('Test Community');
    expect($user->fresh()->ownedCommunities()->latest('id')->first()->description)->toEqual('Awesome community');
});
