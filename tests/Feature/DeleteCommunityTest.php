<?php

use App\Models\Community;
use App\Models\User;

test('communities can be deleted', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $user->ownedCommunities()->save($community = Community::factory()->make([
        'personal_community' => false,
    ]));

    $community->users()->attach(
        $otherUser = User::factory()->create(), ['role' => 'test-role']
    );

    $this->delete('/communities/'.$community->id);

    expect($community->fresh())->toBeNull();
    expect($otherUser->fresh()->communities)->toHaveCount(0);
});

test('personal communities cannot be deleted', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $this->delete('/communities/'.$user->personalCommunity()->id);

    expect($user->personalCommunity()->fresh())->not->toBeNull();
});
