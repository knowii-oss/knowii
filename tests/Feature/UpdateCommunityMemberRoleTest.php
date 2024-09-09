<?php

use App\Models\User;

test('community member roles can be updated', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $user->personalCommunity()->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->put('/communities/'.$user->personalCommunity()->id.'/members/'.$otherUser->id, [
        'role' => 'editor',
    ]);

    expect($otherUser->fresh()->hasCommunityRole(
        $user->personalCommunity()->fresh(), 'editor'
    ))->toBeTrue();
});

test('only community owner can update community member roles', function () {
    $user = User::factory()->withPersonalCommunity()->create();

    $user->personalCommunity()->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'admin']
    );

    $this->actingAs($otherUser);

    $this->put('/communities/'.$user->personalCommunity()->id.'/members/'.$otherUser->id, [
        'role' => 'editor',
    ]);

    expect($otherUser->fresh()->hasCommunityRole(
        $user->personalCommunity()->fresh(), 'admin'
    ))->toBeTrue();
});
