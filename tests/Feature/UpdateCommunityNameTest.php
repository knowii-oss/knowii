<?php

use App\Models\User;

test('community names can be updated', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $this->put('/communities/'.$user->personalCommunity()->id, [
        'name' => 'Test Community',
    ]);

    expect($user->fresh()->ownedCommunities)->toHaveCount(1);
    expect($user->personalCommunity()->fresh()->name)->toEqual('Test Community');
});
