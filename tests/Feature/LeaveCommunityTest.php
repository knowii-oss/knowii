<?php

use App\Models\User;

test('users can leave communities', function () {
    $user = User::factory()->withPersonalCommunity()->create();

    $user->personalCommunity()->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->actingAs($otherUser);

    $this->delete('/communities/'.$user->personalCommunity()->id.'/members/'.$otherUser->id);

    expect($user->personalCommunity()->fresh()->users)->toHaveCount(0);
});

test('community owners cannot leave their own community', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $response = $this->delete('/communities/'.$user->personalCommunity()->id.'/members/'.$user->id);

    $response->assertSessionHasErrorsIn('removeCommunityMember', ['community']);

    expect($user->personalCommunity()->fresh())->not->toBeNull();
});
