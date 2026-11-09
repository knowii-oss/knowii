<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Models\User;

test('ping answers with a success envelope', function () {
    $response = $this->getJson(ApiRoutes::path(ApiRoutes::PING))->assertOk();

    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value)
        ->and($response->json('message'))->toBe('Pong');
});

test('the CSRF cookie endpoint sets the XSRF-TOKEN cookie', function () {
    $this->get('/api/v1/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
});

test('the current user endpoint requires authentication', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('the current user endpoint never exposes secrets or internal ids', function () {
    $user = User::factory()->withUserProfile()->create();

    $response = $this->actingAs($user)->getJson('/api/user')->assertOk()
        ->assertJsonPath('cuid', $user->cuid)
        ->assertJsonPath('username', $user->username);

    foreach (['id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
        $response->assertJsonMissingPath($field);
    }
});
