<?php

use App\KnowiiApiResponseType;
use App\Models\User;
use Illuminate\Http\Response;

test('usernames availability can be verified via the user api controller', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

  $input = [
    'usernameToCheck' => 'foo',
  ];

  $response = $this->json('POST', 'api/v1/users/is-username-available', $input, [
    'Accept' => 'application/json',
  ]);

  $response->assertOk();

  expect($response->json('data.isUsernameAvailable'))->toBeTrue();
});

test('api controller returns false when a username is not available', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

  User::factory()->withPersonalCommunity()->create([
    'username' => 'foo',
  ]);

  $input = [
    'usernameToCheck' => 'foo',
  ];

  $response = $this->json('POST', 'api/v1/users/is-username-available', $input, [
    'Accept' => 'application/json',
  ]);

  $response->assertOk();

  expect($response->json('data.isUsernameAvailable'))->toBeFalse();
});

test('api controller username availability check is case insensitive', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

  User::factory()->withUserProfile()->withPersonalCommunity()->create([
    'username' => 'fOObaR',
  ]);

  $input = [
    'usernameToCheck' => 'foobar',
  ];

  $response = $this->json('POST', 'api/v1/users/is-username-available', $input, [
    'Accept' => 'application/json',
  ]);

  $response->assertOk();

  expect($response->json('data.isUsernameAvailable'))->toBeFalse();
});


test('usernames availability checks return validation exception if the input is incorrect', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->withPersonalCommunity()->create());

  $input = [
  ];

  $response = $this->json('POST', 'api/v1/users/is-username-available', $input, [
    'Accept' => 'application/json',
  ]);

  $response->assertStatus(Response::HTTP_BAD_REQUEST);

  expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});
