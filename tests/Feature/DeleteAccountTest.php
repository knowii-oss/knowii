<?php

use App\Models\User;
use Laravel\Jetstream\Features;

test('user accounts can be deleted', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->delete('/user', [
        'password' => 'password',
    ]);

    expect($user->fresh())->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');

test('correct password must be provided before account can be deleted', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->delete('/user', [
        'password' => 'wrong-password',
    ]);

    expect($user->fresh())->not->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');


test('deleting a user account removes the user id from the user profile', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->create());

  $userProfile = $user->profile;

  expect($userProfile->user_id)->not->toBeNull();

  $this->delete('/user', [
    'password' => 'password',
  ]);

  expect($userProfile->fresh()->user_id)->toBeNull();
});

test('deleting a user account does not remove the username and email from the user profile', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->create());

  $userProfile = $user->profile;

  $initialUsername = $userProfile->username;
  $initialEmail = $userProfile->email;

  expect($userProfile->username)->not->toBeNull();
  expect($userProfile->email)->not->toBeNull();

  $this->delete('/user', [
    'password' => 'password',
  ]);

  $freshUserProfile = $userProfile->fresh();

  expect($freshUserProfile->username)->not->toBeNull();
  expect($freshUserProfile->username)->toBe($initialUsername);
  expect($freshUserProfile->email)->not->toBeNull();
  expect($freshUserProfile->email)->toBe($initialEmail);
});
