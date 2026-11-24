<?php

use App\Actions\Auth\ConfirmAccountDeletion;
use App\ApiRoutes;
use App\Constants;
use App\Contracts\Users\DeletesAccounts;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Exceptions\BusinessException;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\AccountDeletion;
use App\Support\OpenIdConnect;
use Firebase\JWT\JWT;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

/*
 * An account without a password deletes itself after a new sign-in at the OpenID Connect provider (#1521, ADR 0052).
 * The provider is fake, as in OpenIdConnectTest: HTTP fakes for the discovery document, the keys and the token endpoint,
 * and ID tokens that the test signs with an Ed25519 key made at run time.
 */

const FRESH_ISSUER = 'https://id.example.com';
const FRESH_CLIENT_ID = 'community-client';
const FRESH_SUBJECT = 'subject-of-the-member';

function freshB64(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * The claims of a good ID token for the flow in progress: a sign-in 5 seconds ago
 *
 * @param  array<string, mixed>  $override
 * @param  list<string>  $without
 * @return array<string, mixed>
 */
function freshClaims(array $override = [], array $without = []): array
{
    $claims = [
        'iss' => FRESH_ISSUER,
        'aud' => FRESH_CLIENT_ID,
        'sub' => FRESH_SUBJECT,
        'iat' => now()->getTimestamp() - 2,
        'exp' => now()->getTimestamp() + 300,
        'auth_time' => now()->getTimestamp() - 5,
        'nonce' => test()->freshNonce,
        'email' => 'member@example.com',
        'email_verified' => true,
        'name' => 'Ada Lovelace',
        ...$override,
    ];

    return array_diff_key($claims, array_flip($without));
}

/**
 * @param  array<string, mixed>  $claims
 */
function freshSign(array $claims): string
{
    return JWT::encode($claims, test()->freshKey['secret'], 'EdDSA', 'key-1');
}

/**
 * An account without a password, connected to an account of the provider (case 2)
 */
function freshMember(string $subject = FRESH_SUBJECT, string $email = 'member@example.com'): User
{
    $member = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => $email]);
    $member->forceFill(['password' => null])->save();
    UserIdentity::factory()->create(['user_id' => $member->id, 'issuer' => FRESH_ISSUER, 'subject' => $subject]);

    return $member->fresh() ?? $member;
}

/**
 * @return string The secret of the authenticator app
 */
function freshTurnOnTwoFactor(User $member): string
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $member->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

/**
 * The member starts the proof from the deletion section: the redirect to the provider. Keeps the nonce for the token.
 *
 * @return array<string, string>
 */
function freshStartProof(bool $deleteContent = false): array
{
    $response = test()->post('/auth/oidc/confirm-deletion', ['delete_content' => $deleteContent]);
    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    test()->freshNonce = $query['nonce'] ?? null;

    return [...$query, 'location' => $location];
}

/**
 * The provider sends the browser back with a code, and the token endpoint answers with the token
 *
 * @param  array<string, string>  $start
 * @param  array<string, mixed>  $claims
 * @param  list<string>  $without
 */
function freshCallback(array $start, array $claims = [], array $without = []): TestResponse
{
    test()->freshIdToken = freshSign(freshClaims($claims, $without));

    return test()->get('/auth/oidc/callback?'.http_build_query(['code' => 'auth-code', 'state' => $start['state'] ?? '']));
}

/**
 * The reasons that the log holds
 *
 * @return list<string>
 */
function freshLoggedReasons(): array
{
    $reasons = [];
    foreach (test()->freshLogged as $entry) {
        if (isset($entry['context']['reason'])) {
            $reasons[] = $entry['context']['reason'];
        }
    }

    return $reasons;
}

function freshAccountExists(User $user): bool
{
    return User::query()->whereKey($user->id)->exists();
}

function freshProofOf(User $user): mixed
{
    return User::query()->whereKey($user->id)->value('deletion_signed_in_at');
}

/**
 * The proof was refused: back on the deletion section with a plain sentence, nothing recorded, nothing deleted
 */
function freshAssertRefused(TestResponse $response, User $member, string $reason, string $message): void
{
    $response->assertRedirect(AccountDeletion::pageUrl())->assertSessionHas('account_deletion_error', $message);
    expect(freshLoggedReasons())->toContain($reason)
        ->and(freshProofOf($member))->toBeNull()
        ->and(freshAccountExists($member))->toBeTrue();
}

function freshPostOf(User $author): Post
{
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => communityWithMembers()['community']->id]))->create(['type' => KnowiiSpaceType::Discussion]);

    return Post::factory()->inSpace($space)->create(['author_id' => $author->id]);
}

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    config([
        'app.url' => 'https://community.example.com',
        'services.oidc' => [
            'issuer' => FRESH_ISSUER,
            'client_id' => FRESH_CLIENT_ID,
            'client_secret' => 'a-secret-that-is-not-real',
            'scopes' => 'openid profile email',
            'label' => 'Example account',
            'fresh_sign_in_seconds' => 300,
        ],
    ]);

    $pair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($pair);
    $this->freshKey = [
        'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        'jwk' => ['kty' => 'OKP', 'crv' => 'Ed25519', 'kid' => 'key-1', 'use' => 'sig', 'alg' => 'EdDSA', 'x' => freshB64($public)],
    ];
    $this->freshIdToken = '';
    $this->freshNonce = null;

    $logged = new ArrayObject;
    $this->freshLogged = $logged;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    Http::preventStrayRequests();
    Http::fake([
        'id.example.com/.well-known/openid-configuration' => fn () => Http::response([
            'issuer' => FRESH_ISSUER,
            'authorization_endpoint' => FRESH_ISSUER.'/authorize',
            'token_endpoint' => FRESH_ISSUER.'/token',
            'jwks_uri' => FRESH_ISSUER.'/jwks',
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'id_token_signing_alg_values_supported' => ['EdDSA'],
        ]),
        'id.example.com/jwks' => fn () => Http::response(['keys' => [$this->freshKey['jwk']]]),
        'id.example.com/token' => fn () => Http::response(['access_token' => 'AT-SECRET', 'token_type' => 'Bearer', 'id_token' => $this->freshIdToken]),
    ]);

    $this->member = freshMember();
});

// ---------------------------------------------------------------------------------------------------------------------
// The start of the proof
// ---------------------------------------------------------------------------------------------------------------------

test('the authorize request of the proof holds max_age=0 and prompt=login, and keeps its own flow in the session', function () {
    $this->actingAs($this->member);

    $start = freshStartProof();

    expect($start['location'])->toStartWith(FRESH_ISSUER.'/authorize?')
        ->and($start['max_age'])->toBe('0')
        ->and($start['prompt'])->toBe('login')
        ->and($start['redirect_uri'])->toBe('https://community.example.com/auth/oidc/callback')
        ->and($start['code_challenge_method'])->toBe('S256');

    $flow = session('oidc.flow');
    expect($flow['purpose'])->toBe('deletion')
        ->and($flow['link_user_id'])->toBe($this->member->id)
        ->and($flow['state'])->toBe($start['state'])
        ->and($flow['nonce'])->toBe($start['nonce'])
        ->and(freshB64(hash('sha256', $flow['verifier'], true)))->toBe($start['code_challenge']);
});

test('the sign-in and the connection of an account do not ask for a new sign-in', function () {
    $response = $this->get('/auth/oidc/redirect');
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->not->toHaveKey('max_age')->and($query)->not->toHaveKey('prompt');

    $other = User::factory()->withUserProfile()->create();
    $response = $this->actingAs($other)->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])->post('/auth/oidc/connect');
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->not->toHaveKey('max_age')->and($query)->not->toHaveKey('prompt');
});

test('the start of the proof needs a signed-in session and a POST', function () {
    $this->post('/auth/oidc/confirm-deletion')->assertRedirect(route('login'));
    // A GET starts nothing: the fallback route of the web answers 404 for a method that a route does not take (#1574)
    $this->actingAs($this->member)->get('/auth/oidc/confirm-deletion')->assertNotFound();
    expect(session('oidc.flow'))->toBeNull();
});

test('an account with a password cannot start a proof', function () {
    $withPassword = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    UserIdentity::factory()->create(['user_id' => $withPassword->id, 'issuer' => FRESH_ISSUER, 'subject' => 'subject-of-another']);

    $this->actingAs($withPassword)->post('/auth/oidc/confirm-deletion')
        ->assertRedirect(AccountDeletion::pageUrl())
        ->assertSessionHas('account_deletion_error', 'This account has a password: confirm the deletion with your password.');

    expect(session('oidc.flow'))->toBeNull()->and(freshLoggedReasons())->toContain('has_password');
    Http::assertNothingSent();
});

test('without a provider the route answers 404', function () {
    config(['services.oidc.issuer' => null]);

    $this->actingAs($this->member)->post('/auth/oidc/confirm-deletion')->assertNotFound();
});

// ---------------------------------------------------------------------------------------------------------------------
// A valid proof, then the deletion
// ---------------------------------------------------------------------------------------------------------------------

test('a valid proof is recorded, deletes nothing, and the deletion section says until when it holds', function () {
    $this->actingAs($this->member);
    $authTime = now()->getTimestamp() - 5;

    freshCallback(freshStartProof(true), ['auth_time' => $authTime])
        ->assertRedirect(AccountDeletion::pageUrl())
        ->assertSessionHas('account_deletion_status')
        ->assertSessionHas('account_deletion_delete_content', true);

    $member = $this->member->fresh();
    expect(freshAccountExists($this->member))->toBeTrue()
        ->and($member?->deletion_signed_in_at?->getTimestamp())->toBe($authTime)
        // The proof holds for fresh_sign_in_seconds after the sign-in, not after the callback
        ->and(AccountDeletion::confirmedUntil($member)?->getTimestamp())->toBe($authTime + 300);

    $this->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('accountDeletion.proof', 'sign_in')
        ->where('accountDeletion.label', 'Example account')
        ->where('accountDeletion.confirmed_until', AccountDeletion::confirmedUntil($member)?->toIso8601String())
        ->where('accountDeletion.two_factor', false)
        ->where('accountDeletion.error', null)
        ->where('accountDeletion.delete_content', true)
        ->where('accountDeletion.status', AccountDeletion::confirmedUntil($member)?->toIso8601String()));
    expect(freshLoggedReasons())->toBe([]);
});

test('with a valid proof the web deletes the account without a password', function () {
    $this->actingAs($this->member);
    freshCallback(freshStartProof());

    $this->delete('/user', ['delete_content' => false])->assertSessionHasNoErrors();

    expect(freshAccountExists($this->member))->toBeFalse();
});

test('with a valid proof and the box, the web deletes the posts of the member too', function () {
    $post = freshPostOf($this->member);
    $this->actingAs($this->member);
    freshCallback(freshStartProof(true));

    $this->delete('/user', ['delete_content' => true])->assertSessionHasNoErrors();

    expect(freshAccountExists($this->member))->toBeFalse()
        ->and(Post::query()->whereKey($post->id)->exists())->toBeFalse();
});

test('without the box the posts of the member stay, without an author', function () {
    $post = freshPostOf($this->member);
    $this->actingAs($this->member);
    freshCallback(freshStartProof());

    $this->delete('/user')->assertSessionHasNoErrors();

    expect(freshAccountExists($this->member))->toBeFalse()
        ->and($post->fresh()?->author_id)->toBeNull();
});

test('with a proof made in a browser, a token with the ability deletes the account through the API', function () {
    $this->actingAs($this->member);
    freshCallback(freshStartProof());
    Auth::forgetGuards();

    $token = $this->member->createToken('script', ['account:delete'])->plainTextToken;
    $this->withToken($token)->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['delete_content' => false])->assertNoContent();

    expect(freshAccountExists($this->member))->toBeFalse();
});

test('the API without a valid proof answers 422 with the address of the page where the proof is made', function () {
    Sanctum::actingAs($this->member, ['account:delete']);

    $response = $this->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('type', KnowiiApiResponseType::BusinessIssue->value)
        ->assertJsonPath('message', AccountDeletion::signInNeededMessage())
        ->assertJsonPath('metadata.sign_in_url', AccountDeletion::pageUrl());

    expect($response->json('message'))->toContain('A new sign-in is needed')
        ->and($response->json('message'))->toContain('/user/profile#delete-account')
        ->and($response->json('message'))->toContain('"Confirm with Example account"')
        ->and($response->json('message'))->toContain('within 5 minutes')
        ->and(freshAccountExists($this->member))->toBeTrue()
        ->and(freshLoggedReasons())->toContain('no_valid_proof');
});

test('the web without a valid proof refuses and deletes nothing', function () {
    $this->actingAs($this->member)->delete('/user', ['password' => 'password'])
        ->assertSessionHasErrors(['sign_in' => 'Confirm with Example account first. Then you can delete your account for 5 minutes.']);

    expect(freshAccountExists($this->member))->toBeTrue()->and(freshLoggedReasons())->toContain('no_valid_proof');
});

// ---------------------------------------------------------------------------------------------------------------------
// Refused proofs
// ---------------------------------------------------------------------------------------------------------------------

test('a token without auth_time is refused: the provider did not confirm a new sign-in', function () {
    $this->actingAs($this->member);

    freshAssertRefused(freshCallback(freshStartProof(), [], ['auth_time']), $this->member, 'no_auth_time', ConfirmAccountDeletion::NO_NEW_SIGN_IN_MESSAGE);
});

test('an auth_time that is not an integer is refused', function (mixed $value) {
    $this->actingAs($this->member);

    freshAssertRefused(freshCallback(freshStartProof(), ['auth_time' => $value]), $this->member, 'no_auth_time', ConfirmAccountDeletion::NO_NEW_SIGN_IN_MESSAGE);
})->with([
    'a string' => [(string) (time() - 5)],
    'a decimal number' => [time() - 5.5],
    'a boolean' => [true],
    'null' => [null],
]);

test('an old auth_time is refused: the person did not sign in again', function () {
    $this->actingAs($this->member);

    freshAssertRefused(
        freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() - 301]),
        $this->member,
        'old_auth_time',
        'This sign-in was not new enough to confirm the deletion. Please try again, and sign in when the provider asks you to. Your account was not changed.',
    );
});

test('a sign-in from before the start of the proof is refused: the provider did not ask again', function () {
    // The clock stands still, like in the test of the future auth_time below
    $this->freezeSecond();
    $this->actingAs($this->member);

    // Two minutes old: fresh enough for the time limit, but older than the flow, so it is the sign-in of the session
    freshAssertRefused(
        freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() - 120]),
        $this->member,
        'sign_in_before_flow',
        'The sign-in provider did not ask you to sign in again, so we cannot confirm the deletion. Please try again, and sign in when the provider asks you to. Your account was not changed.',
    );

    // A small difference between the clocks is accepted
    freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() - 50])->assertSessionHas('account_deletion_status');
    expect(freshProofOf($this->member))->not->toBeNull();
});

test('an auth_time more than 60 seconds in the future is refused, a small skew is accepted', function () {
    // The clock stands still: the limit is exact to the second, and a second that passes between the line that builds the
    // token and the check on the server would turn 61 into 60
    $this->freezeSecond();
    $this->actingAs($this->member);

    freshAssertRefused(
        freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() + 61]),
        $this->member,
        'future_auth_time',
        'The time of this sign-in could not be checked. Please try again. Your account was not changed.',
    );

    freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() + 30])->assertSessionHas('account_deletion_status');
    expect(freshProofOf($this->member))->not->toBeNull();
});

test('another account of the provider is refused', function () {
    $this->actingAs($this->member);

    freshAssertRefused(
        freshCallback(freshStartProof(), ['sub' => 'subject-of-somebody-else']),
        $this->member,
        'wrong_subject',
        'You signed in with another account than the one that is connected here. Please try again with the connected account. Your account was not changed.',
    );
    // Nothing else happens with the other provider account: no link, no account
    expect(UserIdentity::query()->where('subject', 'subject-of-somebody-else')->exists())->toBeFalse();
});

test('the proof started by one account cannot end in the session of another account', function () {
    $other = freshMember('subject-of-the-other', 'other@example.com');
    $this->actingAs($this->member);
    $start = freshStartProof();

    $this->actingAs($other);
    freshCallback($start, ['sub' => 'subject-of-the-other'])->assertRedirect(AccountDeletion::pageUrl())->assertSessionHas('account_deletion_error');

    expect(freshLoggedReasons())->toContain('session_changed')
        ->and(freshProofOf($this->member))->toBeNull()
        ->and(freshProofOf($other))->toBeNull()
        ->and(freshAccountExists($this->member))->toBeTrue()
        ->and(freshAccountExists($other))->toBeTrue();
});

test('a callback without a session is refused and records nothing', function () {
    $this->actingAs($this->member);
    $start = freshStartProof();

    // Another browser: no session, nobody signed in
    session()->flush();
    Auth::forgetGuards();

    freshCallback($start)->assertRedirect(route('login'))->assertSessionHas('oidc_error');
    $this->assertGuest();
    expect(freshLoggedReasons())->toContain('no_flow')->and(freshProofOf($this->member))->toBeNull();
});

test('a callback that a signed-out person brings back is refused, even with the flow of the member', function () {
    $this->actingAs($this->member);
    $start = freshStartProof();
    // The session keeps the flow, but nobody is signed in any more
    Auth::forgetGuards();

    freshCallback($start)->assertRedirect(route('login'));
    expect(freshLoggedReasons())->toContain('session_changed')->and(freshProofOf($this->member))->toBeNull();
});

test('the state is used once: a replay of the same callback is refused', function () {
    $this->actingAs($this->member);
    $start = freshStartProof();
    freshCallback($start)->assertSessionHas('account_deletion_status');
    $recorded = freshProofOf($this->member);

    $this->travel(10)->seconds();
    // Without its flow the callback cannot tell that it was a proof: the refusal of any callback without a flow
    freshCallback($start)->assertRedirect(route('profile.show'))->assertSessionHas('oidc_error');

    expect(freshLoggedReasons())->toContain('no_flow')->and(freshProofOf($this->member))->toEqual($recorded);
});

test('a failed proof leaves the earlier proof and the account as they were', function () {
    $this->actingAs($this->member);
    freshCallback(freshStartProof())->assertSessionHas('account_deletion_status');
    $recorded = freshProofOf($this->member);

    freshCallback(freshStartProof(), ['sub' => 'subject-of-somebody-else'])->assertSessionHas('account_deletion_error');

    expect(freshProofOf($this->member))->toEqual($recorded)->and(freshAccountExists($this->member))->toBeTrue();
});

test('the log of a refusal holds the reason and the account, never the token or the subject', function () {
    $this->actingAs($this->member);
    freshCallback(freshStartProof(), [], ['auth_time']);

    $all = json_encode(test()->freshLogged->getArrayCopy());
    expect($all)->toContain('no_auth_time')
        ->and($all)->toContain($this->member->cuid)
        ->and($all)->not->toContain(test()->freshIdToken)
        ->and($all)->not->toContain(FRESH_SUBJECT)
        ->and($all)->not->toContain('auth-code');
});

// ---------------------------------------------------------------------------------------------------------------------
// The proof is used once and expires
// ---------------------------------------------------------------------------------------------------------------------

test('a proof is used once: a second deletion with the same proof fails', function () {
    $member = $this->member;
    $member->forceFill(['deletion_signed_in_at' => now()->subSeconds(5)])->save();
    // Two requests that both read the account while the proof was valid
    $first = $member->fresh();
    $second = $member->fresh();

    app(DeletesAccounts::class)->deleteAfterSignIn($first);
    expect(freshAccountExists($member))->toBeFalse();

    expect(fn () => app(DeletesAccounts::class)->deleteAfterSignIn($second))->toThrow(BusinessException::class);
});

test('a proof that was used is read again under the lock: a copy of the account in memory does not help', function () {
    $member = $this->member;
    $member->forceFill(['deletion_signed_in_at' => now()->subSeconds(5)])->save();
    $stale = $member->fresh();
    // Another request used the proof
    User::query()->whereKey($member->id)->update(['deletion_signed_in_at' => null]);

    expect(fn () => app(DeletesAccounts::class)->deleteAfterSignIn($stale))->toThrow(BusinessException::class);
    expect(freshAccountExists($member))->toBeTrue();
});

test('a proof that expired before the deletion is refused on the web and through the API', function () {
    $this->actingAs($this->member);
    freshCallback(freshStartProof(), ['auth_time' => now()->getTimestamp() - 5]);

    $this->travel(296)->seconds();

    $this->delete('/user')->assertSessionHasErrors('sign_in');
    Sanctum::actingAs($this->member->fresh() ?? $this->member, ['account:delete']);
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER))->assertStatus(422);

    expect(freshAccountExists($this->member))->toBeTrue();
    // The page reads the session: a token works on the API only (ADR 0022)
    $this->actingAs($this->member->fresh() ?? $this->member);
    $this->get('/user/profile')->assertInertia(fn ($page) => $page->where('accountDeletion.confirmed_until', null));
});

test('the time of the proof is kept between 60 and 900 seconds', function () {
    config(['services.oidc.fresh_sign_in_seconds' => 5]);
    expect(OpenIdConnect::freshSignInSeconds())->toBe(60);
    config(['services.oidc.fresh_sign_in_seconds' => 5000]);
    expect(OpenIdConnect::freshSignInSeconds())->toBe(900);
    config(['services.oidc.fresh_sign_in_seconds' => 'nonsense']);
    expect(OpenIdConnect::freshSignInSeconds())->toBe(300);
    config(['services.oidc.fresh_sign_in_seconds' => '120']);
    expect(OpenIdConnect::freshSignInSeconds())->toBe(120);
});

// ---------------------------------------------------------------------------------------------------------------------
// Two-factor authentication
// ---------------------------------------------------------------------------------------------------------------------

test('with two-factor authentication on, the web asks for the code after the proof: missing or wrong, nothing is deleted', function () {
    $secret = freshTurnOnTwoFactor($this->member);
    $this->actingAs($this->member);
    freshCallback(freshStartProof());

    $this->delete('/user')->assertSessionHasErrors('code');
    $this->delete('/user', ['code' => '000000'])->assertSessionHasErrors(['code' => 'The provided two factor authentication code was invalid.']);
    expect(freshAccountExists($this->member))->toBeTrue()->and(freshProofOf($this->member))->not->toBeNull();

    $this->delete('/user', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHasNoErrors();
    expect(freshAccountExists($this->member))->toBeFalse();
});

test('with two-factor authentication on, the web limits the tries of the code', function () {
    freshTurnOnTwoFactor($this->member);
    $this->actingAs($this->member);
    freshCallback(freshStartProof());

    foreach (range(1, Constants::SECOND_FACTOR_TRIES_PER_MINUTE) as $try) {
        $this->delete('/user', ['code' => '00000'.$try])->assertSessionHasErrors(['code' => 'The provided two factor authentication code was invalid.']);
    }
    $this->delete('/user', ['code' => 'recovery-one'])->assertSessionHasErrors(['code' => 'Too many tries. Please wait a minute, then try again.']);

    expect(freshAccountExists($this->member))->toBeTrue();
});

test('with two-factor authentication on, the API asks for the code with the proof', function () {
    $secret = freshTurnOnTwoFactor($this->member);
    $this->actingAs($this->member);
    freshCallback(freshStartProof());
    Auth::forgetGuards();
    $token = $this->member->createToken('script', ['account:delete'])->plainTextToken;

    $this->withToken($token)->deleteJson(ApiRoutes::path(ApiRoutes::USER))->assertStatus(400)->assertJsonValidationErrorFor('code', 'errors');
    $this->withToken($token)->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['code' => '000000'])->assertStatus(400);
    expect(freshAccountExists($this->member))->toBeTrue();

    $this->withToken($token)->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertNoContent();
    expect(freshAccountExists($this->member))->toBeFalse();
});

test('the profile page says when the deletion needs the code', function () {
    freshTurnOnTwoFactor($this->member);

    $this->actingAs($this->member->fresh() ?? $this->member)->get('/user/profile')->assertInertia(fn ($page) => $page->where('accountDeletion.two_factor', true));
});

// ---------------------------------------------------------------------------------------------------------------------
// An account with a password, and an account without a way to make the proof
// ---------------------------------------------------------------------------------------------------------------------

test('an account with a password still needs its password: a recorded proof does not replace it', function () {
    $withPassword = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    UserIdentity::factory()->create(['user_id' => $withPassword->id, 'issuer' => FRESH_ISSUER, 'subject' => 'subject-of-another']);
    $withPassword->forceFill(['deletion_signed_in_at' => now()->subSeconds(5)])->save();

    expect(AccountDeletion::confirmedUntil($withPassword->fresh() ?? $withPassword))->toBeNull();

    $this->actingAs($withPassword)->delete('/user')->assertSessionHasErrors(['password' => 'The password is incorrect.']);
    Sanctum::actingAs($withPassword->fresh() ?? $withPassword, ['account:delete']);
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER))->assertStatus(400)->assertJsonValidationErrorFor('password', 'errors');
    expect(fn () => app(DeletesAccounts::class)->deleteAfterSignIn($withPassword->fresh() ?? $withPassword))->toThrow(BusinessException::class);
    expect(freshAccountExists($withPassword))->toBeTrue();

    $this->actingAs($withPassword->fresh() ?? $withPassword)->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('accountDeletion.proof', 'password')
        ->where('accountDeletion.confirmed_until', null));
});

test('a password set during the proof ends it: the callback refuses', function () {
    $this->actingAs($this->member);
    $start = freshStartProof();
    $this->member->forceFill(['password' => 'a new password'])->save();

    freshAssertRefused(freshCallback($start), $this->member, 'has_password', 'This account has a password: confirm the deletion with your password. Your account was not changed.');
});

test('an account without a password and without a connected account gets the refusal that names the operator', function () {
    $alone = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $alone->forceFill(['password' => null])->save();
    $alone = $alone->fresh() ?? $alone;

    $this->actingAs($alone)->get('/user/profile')->assertInertia(fn ($page) => $page->where('accountDeletion.proof', 'unavailable'));
    $this->post('/auth/oidc/confirm-deletion')->assertRedirect(AccountDeletion::pageUrl())->assertSessionHas('account_deletion_error', AccountDeletion::UNAVAILABLE_MESSAGE);
    $this->delete('/user', ['password' => 'password'])->assertSessionHasErrors(['sign_in' => AccountDeletion::UNAVAILABLE_MESSAGE]);
    Sanctum::actingAs($alone, ['account:delete']);
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('message', AccountDeletion::UNAVAILABLE_MESSAGE);

    expect(AccountDeletion::UNAVAILABLE_MESSAGE)->toContain('write to the operator of this site')
        ->and(freshAccountExists($alone))->toBeTrue();
    Http::assertNothingSent();
});

test('when the provider is not configured any more, a recorded proof does not help: the refusal of case 3', function () {
    $this->member->forceFill(['deletion_signed_in_at' => now()->subSeconds(5)])->save();
    config(['services.oidc.client_secret' => null]);

    $this->actingAs($this->member->fresh() ?? $this->member)->get('/user/profile')->assertInertia(fn ($page) => $page
        ->where('accountDeletion.proof', 'unavailable')
        ->where('accountDeletion.label', null));
    $this->delete('/user')->assertSessionHasErrors(['sign_in' => AccountDeletion::UNAVAILABLE_MESSAGE]);
    Sanctum::actingAs($this->member->fresh() ?? $this->member, ['account:delete']);
    $this->deleteJson(ApiRoutes::path(ApiRoutes::USER))->assertStatus(422)->assertJsonPath('message', AccountDeletion::UNAVAILABLE_MESSAGE);
    expect(fn () => app(DeletesAccounts::class)->deleteAfterSignIn($this->member->fresh() ?? $this->member))->toThrow(BusinessException::class);

    expect(freshAccountExists($this->member))->toBeTrue();
});

// ---------------------------------------------------------------------------------------------------------------------
// The sign-in and the connection of an account still work, and record no proof
// ---------------------------------------------------------------------------------------------------------------------

test('a normal sign-in with a fresh auth_time signs in and records no proof', function () {
    $response = $this->get('/auth/oidc/redirect');
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    test()->freshNonce = $query['nonce'];

    freshCallback($query)->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->member);
    expect(freshProofOf($this->member))->toBeNull();
});

test('connecting an account with a fresh auth_time connects it and records no proof', function () {
    $other = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'other@example.com']);
    $other->forceFill(['password' => null])->save();
    $this->actingAs($other->fresh() ?? $other);
    $response = $this->post('/auth/oidc/connect');
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    test()->freshNonce = $query['nonce'];

    freshCallback($query, ['sub' => 'subject-of-the-other'])->assertRedirect(route('profile.show'))->assertSessionHas('oidc_status');

    expect(UserIdentity::query()->where('user_id', $other->id)->where('subject', 'subject-of-the-other')->exists())->toBeTrue()
        ->and(freshProofOf($other))->toBeNull()
        ->and(freshAccountExists($other))->toBeTrue();
});
