<?php

use App\Models\User;

test('members can be removed from communities', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $user->personalCommunity()->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->delete('/communities/'.$user->personalCommunity()->id.'/members/'.$otherUser->id);

    expect($user->personalCommunity()->fresh()->users)->toHaveCount(0);
});

test('only community owner can remove members', function () {
    $user = User::factory()->withPersonalCommunity()->create();

    $user->personalCommunity()->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->actingAs($otherUser);

    $response = $this->delete('/communities/'.$user->personalCommunity()->id.'/members/'.$user->id);

    $response->assertStatus(403);
});
