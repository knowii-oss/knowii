<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Models\User;
use Illuminate\Http\Resources\MissingValue;

test('ping answers with a success envelope', function () {
    $response = $this->getJson(ApiRoutes::path(ApiRoutes::PING))->assertOk();

    expect($response->json('type'))->toBe(KnowiiApiResponseType::Success->value)
        ->and($response->json('message'))->toBe('Pong');
});

test('the CSRF cookie endpoint sets the XSRF-TOKEN cookie', function () {
    $this->get('/api/v1/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
});

test('the current user endpoint requires authentication', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});

test('the current user endpoint returns the user resource with the account fields and the profile (#1145)', function () {
    $user = User::factory()->withUserProfile()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/v1/user')->assertOk()
        ->assertJsonPath('type', KnowiiApiResponseType::Success->value)
        ->assertJsonPath('data.cuid', $user->cuid)
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.two_factor_enabled', false)
        ->assertJsonPath('data.profile.cuid', $user->profile->cuid);

    expect($response->json('data.email_verified_at'))->not->toBeNull();

    foreach (['id', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'current_team_id'] as $field) {
        $response->assertJsonMissingPath('data.'.$field);
    }
});

test('the user resource of another member carries no account field (#1145)', function () {
    $user = User::factory()->withUserProfile()->create();
    $other = User::factory()->withUserProfile()->create();
    $this->actingAs($other);

    $json = $user->load('profile')->toResource()->toArray(request());

    expect(array_keys(array_filter($json, fn ($value) => ! $value instanceof MissingValue)))
        ->toEqualCanonicalizing(['cuid', 'name', 'username', 'avatar_url', 'created_at']);
});
