<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Models\User;
use Illuminate\Http\Response;

test('users can log in via the API', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => $user->email,
        'password' => 'password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_OK);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value);
    $this->assertAuthenticatedAs($user);
});

test('the login API returns a token when requested', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => $user->email,
        'password' => 'password',
        'includeToken' => true,
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_OK);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value);
    expect($response->json('data.token'))->not->toBeNull();
    expect($response->json('data.tokenValidUntil'))->not->toBeNull();
});

test('the login API rejects an invalid password with an authentication issue', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthenticationIssue->value);
    $this->assertGuest();
});

test('the login API rejects unknown credentials with an authentication issue', function () {
    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthenticationIssue->value);
    $this->assertGuest();
});

test('the login API rejects a missing email with a validation issue', function () {
    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'password' => 'password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the login API rejects a missing password with a validation issue', function () {
    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => 'someone@example.com',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});

test('the login API rejects an invalid email format with a validation issue', function () {
    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => 'not-an-email',
        'password' => 'password',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::ValidationIssue->value);
});
