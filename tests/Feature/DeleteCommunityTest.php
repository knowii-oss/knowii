<?php

use App\Contracts\Communities\DeletesCommunities;
use App\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('communities can be deleted', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  $user->ownedCommunities()->save($community = Community::factory()->make([
    'visibility' => KnowiiCommunityVisibility::Public,
  ]));

  $community->users()->attach(
    $otherUser = User::factory()->create(), ['role' => 'test-role']
  );

  $deleter = app(DeletesCommunities::class);
  $deleter->delete($user, $community->cuid);

  expect($community->fresh())->toBeNull();
  expect($otherUser->fresh()->communities)->toHaveCount(0);
});

test('personal communities cannot be deleted', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  $this->expectException(ValidationException::class);

  $deleter = app(DeletesCommunities::class);
  $deleter->delete($user, $user->personalCommunity()->cuid);

  expect($user->personalCommunity()->fresh())->not->toBeNull();
});



