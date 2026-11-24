<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/*
 * A signed-in member who sends a list, a map, an empty value or a very long text where a text is expected gets a validation
 * error, never a server error: on the places that ask for the current password, on the routes of the code of two-factor
 * authentication, and on the API tokens page. Nothing of the account changes, and the session goes on.
 */

const NO_TEXT_NEW_PASSWORD = 'A-new-password-of-2026!';

/**
 * The request of one field with one value, sent by the session of the member, for a JSON answer
 *
 * @param  array<string, string>  $cookies
 */
function noTextRequest(string $field, mixed $value, array $cookies, User $user, Community $owned, User $admin): TestResponse
{
    $browser = browserWith($cookies)->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')]);
    $transfer = ApiRoutes::path(ApiRoutes::COMMUNITY_OWNERSHIP, [ApiRoutes::PARAM_COMMUNITY => $owned->cuid]);

    return match ($field) {
        'confirmation: password' => $browser->postJson('/user/confirm-password', ['password' => $value]),
        'change of the password: current_password' => $browser->putJson('/user/password', ['current_password' => $value, 'password' => NO_TEXT_NEW_PASSWORD, 'password_confirmation' => NO_TEXT_NEW_PASSWORD]),
        'change of the password: password' => $browser->putJson('/user/password', ['current_password' => 'password', 'password' => $value, 'password_confirmation' => $value]),
        'other sessions: password' => $browser->deleteJson('/user/other-browser-sessions', ['password' => $value]),
        'deletion on the page: password' => $browser->deleteJson('/user', ['password' => $value]),
        'deletion on the page: code' => $browser->deleteJson('/user', ['password' => 'password', 'code' => $value]),
        'deletion on the API: password' => $browser->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => $value]),
        'deletion on the API: code' => $browser->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => 'password', 'code' => $value]),
        'profile form: current_password' => $browser->putJson('/user/profile-information', ['name' => $user->name, 'email' => 'a-new-address@example.org', 'current_password' => $value]),
        'transfer: password' => $browser->postJson($transfer, ['member' => $admin->cuid, 'password' => $value]),
        'transfer: member' => $browser->postJson($transfer, ['member' => $value, 'password' => 'password']),
        'confirmation of the second factor: code' => $browser->postJson('/user/confirmed-two-factor-authentication', ['code' => $value]),
        'new API token: name' => $browser->postJson('/user/api-tokens', ['name' => $value, 'permissions' => ['user:read']]),
        'new API token: permissions' => $browser->postJson('/user/api-tokens', ['name' => 'a token', 'permissions' => ['user:read', $value]]),
        'new API token: expires_in' => $browser->postJson('/user/api-tokens', ['name' => 'a token', 'expires_in' => $value]),
    };
}

/**
 * The name of the field in the errors of the answer
 */
function noTextErrorField(string $field): string
{
    return $field === 'new API token: permissions' ? 'permissions.1' : trim((string) strrchr($field, ' '));
}

test('a value that is no text is a validation error, never a server error', function (string $field, mixed $value) {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $owned = Community::factory()->create(['owner_id' => $user->id]);
    $admin = User::factory()->withUserProfile()->create();
    $owned->users()->attach($admin, ['role' => KnowiiCommunityMemberRole::Admin]);

    $cookies = signInWithForm($user)['session'];
    if ($field === 'confirmation of the second factor: code') {
        // A second factor that waits for its code
        browserWith($cookies)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        browserWith($cookies)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    } elseif (str_ends_with($field, ': code')) {
        // A second factor that is on
        $user->forceFill([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
    $stored = $user->fresh();

    $response = noTextRequest($field, $value, $cookies, $user, $owned, $admin);

    expect($response->getStatusCode())->toBe(400);
    $response->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    expect(array_keys((array) $response->json('errors')))->toContain(noTextErrorField($field));

    // Nothing of the account changed, and the session goes on
    $after = $user->fresh();
    expect($after)->not->toBeNull()
        ->and(Hash::check('password', (string) $after?->password))->toBeTrue()
        ->and($after?->pending_email)->toBeNull()
        ->and($after?->session_version)->toBe(0)
        ->and($after?->getRawOriginal('two_factor_confirmed_at'))->toBe($stored?->getRawOriginal('two_factor_confirmed_at'))
        ->and($after?->tokens()->count())->toBe(0)
        ->and($owned->fresh()?->owner_id)->toBe($user->id);
    browserWith($cookies)->getJson('/user/confirmed-password-status')->assertOk();
})->with([
    'confirmation: password',
    'change of the password: current_password',
    'change of the password: password',
    'other sessions: password',
    'deletion on the page: password',
    'deletion on the page: code',
    'deletion on the API: password',
    'deletion on the API: code',
    'profile form: current_password',
    'transfer: password',
    'transfer: member',
    'confirmation of the second factor: code',
    'new API token: name',
    'new API token: permissions',
    'new API token: expires_in',
])->with([
    'a list' => [['x']],
    'a list of lists' => [[['x'], ['a' => 'b']]],
    'a map' => [['a' => ['b' => 'c']]],
    'an empty value' => [''],
]);

test('the permissions of a new API token are a list of texts: any other value is a validation error', function (mixed $permissions, string $error) {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $cookies = signInWithForm($user)['session'];

    $response = browserWith($cookies)->postJson('/user/api-tokens', ['name' => 'a token', 'permissions' => $permissions]);

    $response->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    expect(array_keys((array) $response->json('errors')))->toContain($error)
        ->and($user->tokens()->count())->toBe(0);

    // The form of the page gets the error too, and a list of texts still makes a token
    browserWith($cookies)->post('/user/api-tokens', ['name' => 'a token', 'permissions' => $permissions])->assertSessionHasErrors([$error]);
    browserWith($cookies)->post('/user/api-tokens', ['name' => 'a token', 'permissions' => ['user:read', 'not-a-permission']])->assertSessionHasNoErrors();
    expect($user->tokens()->sole()->abilities)->toBe(['user:read']);
})->with([
    'a text' => ['user:read', 'permissions'],
    'a number' => [12, 'permissions'],
    'a list of lists' => [[['user:read'], ['a' => 'b']], 'permissions.0'],
    'a map of lists' => [['a' => ['b' => 'c']], 'permissions.a'],
    'a list with a number' => [['user:read', 12], 'permissions.1'],
]);

test('a very long text is never a server error', function (string $field, int $status) {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $owned = Community::factory()->create(['owner_id' => $user->id]);
    $admin = User::factory()->withUserProfile()->create();
    $owned->users()->attach($admin, ['role' => KnowiiCommunityMemberRole::Admin]);

    $cookies = signInWithForm($user)['session'];
    if ($field === 'confirmation of the second factor: code') {
        browserWith($cookies)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        browserWith($cookies)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    } elseif (str_ends_with($field, ': code')) {
        $user->forceFill([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    $response = noTextRequest($field, str_repeat('a', 20000), $cookies, $user, $owned, $admin);

    expect($response->getStatusCode())->toBe($status);
    if ($status === 400) {
        $response->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    }
    // A wrong password, or a value that is too long: nothing of the account changed, and the session goes on
    $after = $user->fresh();
    expect($after)->not->toBeNull()
        ->and(Hash::check('password', (string) $after?->password))->toBeTrue()
        ->and($after?->pending_email)->toBeNull()
        ->and($after?->session_version)->toBe(0)
        ->and($owned->fresh()?->owner_id)->toBe($user->id);
    browserWith($cookies)->getJson('/user/confirmed-password-status')->assertOk();
})->with([
    ['confirmation: password', 400],
    ['change of the password: current_password', 400],
    ['other sessions: password', 400],
    ['deletion on the page: password', 400],
    ['deletion on the page: code', 400],
    ['deletion on the API: password', 400],
    ['deletion on the API: code', 400],
    ['profile form: current_password', 400],
    ['transfer: password', 400],
    // No member has this identifier
    ['transfer: member', 404],
    ['confirmation of the second factor: code', 400],
    ['new API token: name', 400],
    ['new API token: expires_in', 400],
]);

test('a value that is no text is no try of the current password', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $cookies = signInWithForm($user)['session'];

    // More requests than the tries of a session: a list, a map, an empty value and no value at all
    foreach (range(1, 3) as $round) {
        foreach ([['x'], ['a' => ['b' => 'c']], '', null] as $value) {
            browserWith($cookies)->postJson('/user/confirm-password', ['password' => $value])->assertStatus(400)->assertJsonValidationErrorFor('password', 'errors');
        }
        browserWith($cookies)->postJson('/user/confirm-password')->assertStatus(400);
    }

    // The session goes on, with all its tries
    foreach (range(1, 4) as $try) {
        browserWith($cookies)->postJson('/user/confirm-password', ['password' => 'wrong-'.$try])->assertStatus(400);
    }
    browserWith($cookies)->postJson('/user/confirm-password', ['password' => 'password'])->assertStatus(201);
});
