<?php

use App\Actions\Users\VerifyUsernameAvailability;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('usernames availability can be verified', function () {
    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $input = [
      'usernameToCheck' => 'foo',
    ];

    $checker = new VerifyUsernameAvailability();
    $result = $checker->verify($user, $input);

    expect($result)->toBeTrue();
});

test('username availability check rejects invalid input', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  $input = [
    'foo' => 'bar',
  ];

  $this->expectException(ValidationException::class);

  $checker = new VerifyUsernameAvailability();
  $checker->verify($user, $input);
});

test('username availability check returns false is the username is already taken', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  User::factory()->withPersonalCommunity()->create([
    'username' => 'foo',
  ]);

  $input = [
    'usernameToCheck' => 'foo',
  ];

  $checker = new VerifyUsernameAvailability();
  $result = $checker->verify($user, $input);

  expect($result)->toBeFalse();
});

test('username availability check is case insensitive', function () {
  $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

  User::factory()->withPersonalCommunity()->create([
    'username' => 'FOOBaR',
  ]);

  $input = [
    'usernameToCheck' => 'foObar',
  ];

  $checker = new VerifyUsernameAvailability();
  $result = $checker->verify($user, $input);

  expect($result)->toBeFalse();
});
