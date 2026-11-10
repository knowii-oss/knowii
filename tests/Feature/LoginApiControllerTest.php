<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

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

/**
 * A user with confirmed two-factor authentication and two recovery codes (#1256).
 *
 * @return array{0: User, 1: string}
 */
function userWithTwoFactor(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

function apiLogin(array $body): TestResponse
{
    return test()->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), $body);
}

test('an account with two-factor authentication needs a code: no session, no token without it (#1256)', function () {
    [$user] = userWithTwoFactor();

    apiLogin(['email' => $user->email, 'password' => 'password', 'includeToken' => true])
        ->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJsonPath('metadata.two_factor_required', true)
        ->assertJsonMissingPath('data.token');

    $this->assertGuest();
    expect($user->tokens()->count())->toBe(0);
});

test('a wrong two-factor code is refused (#1256)', function () {
    [$user] = userWithTwoFactor();

    apiLogin(['email' => $user->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);

    $this->assertGuest();
});

test('the code of the authenticator app completes the login (#1256)', function () {
    [$user, $secret] = userWithTwoFactor();
    $code = (new Google2FA)->getCurrentOtp($secret);

    apiLogin(['email' => $user->email, 'password' => 'password', 'code' => $code, 'includeToken' => true])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']]);

    $this->assertAuthenticatedAs($user);
});

test('a recovery code works once (#1256)', function () {
    [$user] = userWithTwoFactor();

    apiLogin(['email' => $user->email, 'password' => 'password', 'recovery_code' => 'recovery-one'])->assertOk();
    expect($user->fresh()?->recoveryCodes())->not->toContain('recovery-one');

    auth()->logout();
    apiLogin(['email' => $user->email, 'password' => 'password', 'recovery_code' => 'recovery-one'])->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('a wrong password never reveals whether the account has two-factor authentication (#1256)', function () {
    [$user] = userWithTwoFactor();

    apiLogin(['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJsonMissingPath('metadata.two_factor_required');
});

test('the API login is rate limited like the web login (#1256)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    for ($i = 0; $i < 5; $i++) {
        apiLogin(['email' => $user->email, 'password' => 'wrong'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    apiLogin(['email' => $user->email, 'password' => 'password'])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('the login API refuses an account whose email address is not verified: no session, no token (#1323)', function () {
    $user = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->json('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [
        'email' => $user->email,
        'password' => 'password',
        'includeToken' => true,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    expect($response->json('type'))->toBe(KnowiiApiResponseType::AuthenticationIssue->value)
        ->and($response->json('metadata.email_verification_required'))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
    $this->assertGuest();
});

test('an unverified account cannot use the API, even with a token (#1323)', function () {
    $user = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create();
    $token = $user->createToken('old', ['*'])->plainTextToken;

    $this->withToken($token)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertForbidden();
    $this->withToken($token)->postJson(ApiRoutes::path(ApiRoutes::COMMUNITIES), ['name' => 'Squatters', 'visibility' => 'public'])->assertForbidden();

    $user->markEmailAsVerified();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
});
