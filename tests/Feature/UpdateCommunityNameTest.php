<?php

use App\Actions\Communities\UpdateCommunityName;
use App\Models\User;

test('community names can be updated', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  $input = [
    'name' => 'Cool Community',
  ];

  $updater = new UpdateCommunityName();
  $updater->update($user, $user->personalCommunity(), $input);

  expect($user->personalCommunity()->fresh()->name)->toEqual('Cool Community');
});
