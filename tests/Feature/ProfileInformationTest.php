<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->put('/user/profile-information', [
      'email' => 'test@example.com',
      'name' => 'Test Name',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com');
});

test('username can be updated via profile information', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->create());

  $this->put('/user/profile-information', [
    'email' => 'test@example.com',
    'username' => 'foobar36',
    'name' => 'Test Name',
  ]);

  expect($user->fresh())
    ->name->toEqual('Test Name')
    ->email->toEqual('test@example.com')
    ->username->toEqual('foobar36');
});

test('username cannot be set to an invalid value via profile information', function () {
  $this->actingAs($user = User::factory()->withUserProfile()->create());

  $response = $this->put('/user/profile-information', [
    'name' => 'Test Name',
    'email' => 'test@example.com',
    'username' => 'f', // too short
  ]);

  expect($response->exception)->toBeInstanceOf(ValidationException::class);
});
