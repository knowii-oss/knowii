<?php

use App\Contracts\Communities\DeletesCommunities;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;

test('communities can be deleted', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

  $user->ownedCommunities()->save($community = Community::factory()->make([
    'visibility' => KnowiiCommunityVisibility::Public,
  ]));

  $community->users()->attach(
    $otherUser = User::factory()->create(), ['role' => KnowiiCommunityMemberRole::Member]
  );

  $deleter = app(DeletesCommunities::class);
  $deleter->delete($user, $community->cuid);

  expect($community->fresh())->toBeNull();
  expect($otherUser->fresh()->communities)->toHaveCount(0);
});
