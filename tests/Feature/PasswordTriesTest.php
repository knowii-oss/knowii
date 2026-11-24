<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityMemberRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Community;
use App\Models\User;
use App\Support\PasswordTries;
use App\Support\RequestCredential;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * The places that ask for the current password count the wrong passwords (App\Support\PasswordTries). A session that a
 * sign-in made has its own count. The sessions that a "Remember me" cookie made share one count per account, and so do the
 * API tokens of an account. Never one count for the account as a whole: the tries of the tokens, or of the sessions of the
 * cookie, change nothing for a session that signed in. A session that reaches 5 wrong tries in 15 minutes ends, and its
 * browser gets the sign-in page with "Too many wrong passwords. Sign in again."; the tokens are refused for the rest of the
 * 15 minutes (429). A try is counted before the password is checked. The tests use real cookies, each browser with its own.
 */

const PASSWORD_TRIES_NEW_PASSWORD = 'A-new-password-of-2026!';

const PASSWORD_TRIES_SIGN_IN_AGAIN = 'Too many wrong passwords. Sign in again.';

/**
 * A member who owns a community with an admin, for the transfer of the ownership
 *
 * @return array{0: User, 1: Community, 2: User}
 */
function passwordTriesMember(): array
{
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();
    $owned = Community::factory()->create(['owner_id' => $user->id]);
    $admin = User::factory()->withUserProfile()->create();
    $owned->users()->attach($admin, ['role' => KnowiiCommunityMemberRole::Admin]);

    return [$user, $owned, $admin];
}

/**
 * The address and the body of a place that asks for the current password
 *
 * @return array{0: string, 1: string, 2: array<string, mixed>, 3: bool} The method, the address, the body, and whether the place is on the API
 */
function passwordTriesPlace(string $place, string $password, User $user, Community $owned, User $admin): array
{
    return match ($place) {
        'confirm' => ['POST', '/user/confirm-password', ['password' => $password], false],
        'change' => ['PUT', '/user/password', ['current_password' => $password, 'password' => PASSWORD_TRIES_NEW_PASSWORD, 'password_confirmation' => PASSWORD_TRIES_NEW_PASSWORD], false],
        'other sessions' => ['DELETE', '/user/other-browser-sessions', ['password' => $password], false],
        'deletion' => ['DELETE', '/user', ['password' => $password], false],
        'api deletion' => ['DELETE', ApiRoutes::path(ApiRoutes::USER), ['password' => $password], true],
        'profile' => ['PUT', '/user/profile-information', ['name' => $user->name, 'email' => 'a-new-address@example.org', 'current_password' => $password], false],
        'transfer' => ['POST', ApiRoutes::path(ApiRoutes::COMMUNITY_OWNERSHIP, [ApiRoutes::PARAM_COMMUNITY => $owned->cuid]), ['member' => $admin->cuid, 'password' => $password], true],
    };
}

/**
 * One try on a place, by the browser that holds these cookies: the form of the page, or the call of the web application
 * to the API
 *
 * @param  array<string, string>  $cookies
 * @param  array{0: User, 1: Community, 2: User}  $member
 */
function passwordTry(string $place, array $cookies, string $password, array $member, bool $json = false): TestResponse
{
    [$method, $address, $body, $api] = passwordTriesPlace($place, $password, ...$member);
    $browser = browserWith($cookies);

    if ($api) {
        return $browser->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])->json($method, $address, $body);
    }

    return $json ? $browser->json($method, $address, $body) : passwordTriesForm($browser, $method, $address, $body);
}

/**
 * The form of a page, sent by the browser with its cookies
 *
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function passwordTriesForm(TestCase $browser, string $method, string $address, array $body, array $headers = []): TestResponse
{
    return match ($method) {
        'POST' => $browser->post($address, $body, $headers),
        'PUT' => $browser->put($address, $body, $headers),
        'DELETE' => $browser->delete($address, $body, $headers),
    };
}

/**
 * One try on a place of the API, with an API token
 *
 * @param  array{0: User, 1: Community, 2: User}  $member
 */
function passwordTryWithToken(string $place, string $token, string $password, array $member): TestResponse
{
    [$method, $address, $body] = passwordTriesPlace($place, $password, ...$member);
    newBrowser();

    return test()->withHeaders(['Authorization' => 'Bearer '.$token])->json($method, $address, $body);
}

/**
 * The answer of a place to a wrong password, while the credential has tries left
 */
function expectWrongPassword(string $place, TestResponse $response): void
{
    match ($place) {
        'confirm', 'other sessions', 'deletion' => $response->assertSessionHasErrors(['password']),
        'change' => $response->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'The provided password does not match your current password.']),
        'profile' => $response->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => 'The provided password does not match your current password.']),
        'api deletion', 'transfer' => $response->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value),
    };
}

/**
 * The place did its work with the right password
 *
 * @param  array{0: User, 1: Community, 2: User}  $member
 */
function expectThePlaceWorked(string $place, TestResponse $response, array $member): void
{
    [$user, $owned, $admin] = $member;
    expect($response->getStatusCode())->toBeLessThan(400);
    if (! in_array($place, ['api deletion', 'transfer'], true)) {
        $response->assertSessionHasNoErrors();
    }

    match ($place) {
        'confirm', 'other sessions' => expect($user->fresh())->not->toBeNull(),
        'change' => expect(Hash::check(PASSWORD_TRIES_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue(),
        'deletion', 'api deletion' => expect($user->fresh())->toBeNull(),
        'profile' => expect($user->fresh()?->pending_email)->toBe('a-new-address@example.org'),
        'transfer' => expect($owned->fresh()?->owner_id)->toBe($admin->id),
    };
}

/**
 * Nothing of the account changed
 *
 * @param  array{0: User, 1: Community, 2: User}  $member
 */
function expectNothingChanged(array $member): void
{
    [$user, $owned] = $member;
    $fresh = $user->fresh();
    expect($fresh)->not->toBeNull()
        ->and(Hash::check('password', (string) $fresh?->password))->toBeTrue()
        ->and($fresh?->pending_email)->toBeNull()
        ->and($fresh?->session_version)->toBe(0)
        ->and($owned->fresh()?->owner_id)->toBe($user->id);
}

dataset('places that ask for the current password', [
    'the confirmation of the password' => ['confirm'],
    'the change of the password' => ['change'],
    '"Log out other browser sessions"' => ['other sessions'],
    'the deletion of the account, on the page' => ['deletion'],
    'the deletion of the account, on the API' => ['api deletion'],
    'the profile form, for a new email address' => ['profile'],
    'the transfer of the ownership of a community' => ['transfer'],
]);

test('a session has 5 tries in 15 minutes', function () {
    expect(Constants::PASSWORD_TRIES)->toBe(5)
        ->and(Constants::PASSWORD_TRIES_MINUTES)->toBe(15);
});

test('the fifth wrong password of a session ends that session, and the session of the member goes on with the right password', function (string $place) {
    $member = passwordTriesMember();
    [$user] = $member;
    $another = signInWithForm($user)['session'];
    $own = signInWithForm($user)['session'];
    $api = in_array($place, ['api deletion', 'transfer'], true);

    // Four wrong passwords: the answer of a wrong password, and the session goes on
    foreach (range(1, 4) as $try) {
        expectWrongPassword($place, passwordTry($place, $another, 'wrong-'.$try, $member));
        browserWith($another)->getJson('/user/confirmed-password-status')->assertOk();
        // The limit of a route counts per minute: the tries are spread over more than one
        $this->travel(21)->seconds();
    }

    // The fifth one ends the session: the sign-in page, which says why, or a 401 for a call of the API
    $last = passwordTry($place, $another, 'wrong-5', $member);
    if ($api) {
        $last->assertUnauthorized()->assertJsonPath('type', KnowiiApiResponseType::AuthenticationIssue->value)->assertJsonPath('message', PASSWORD_TRIES_SIGN_IN_AGAIN);
    } else {
        $last->assertRedirect(route('login'));
    }
    $this->assertGuest('web');
    $after = sessionCookieOf($last);
    expect(array_values($after)[0])->not->toBe(array_values($another)[0]);
    $page = browserWith($after)->get('/login')->assertOk()->inertiaPage();
    // The words are a warning of the sign-in page, never its status, which the page shows as a success. They are an error
    // of the page too: the form that was sent sees no success
    expect($page['props']['warning'] ?? null)->toBe(PASSWORD_TRIES_SIGN_IN_AGAIN)
        ->and($page['props']['status'] ?? null)->toBeNull()
        ->and($page['props']['errors']['session'] ?? null)->toBe(PASSWORD_TRIES_SIGN_IN_AGAIN)
        ->and($page['clearHistory'] ?? null)->toBeTrue();

    // That session is over, also with the right password
    foreach ([$another, $after] as $cookies) {
        browserWith($cookies)->getJson('/user/confirmed-password-status')->assertUnauthorized();
        expect(passwordTry($place, $cookies, 'password', $member, json: true)->getStatusCode())->toBe(401);
    }
    expectNothingChanged($member);

    // The member, in the session of the member, with the right password: the place works
    expectThePlaceWorked($place, passwordTry($place, $own, 'password', $member), $member);
})->with('places that ask for the current password');

test('30 wrong passwords in 10 minutes in one session never close a form of the member in another session', function () {
    $member = passwordTriesMember();
    [$user] = $member;
    $another = signInWithForm($user)['session'];
    $own = signInWithForm($user)['session'];

    $answers = [];
    $passed = 0;
    foreach (range(0, 9) as $minute) {
        // Three wrong passwords when the minute starts
        foreach (range(1, 3) as $try) {
            $answers[] = passwordTry('confirm', $another, 'wrong-'.$minute.'-'.$try, $member, json: true)->getStatusCode();
        }
        // The member, with the right password, three times through the minute
        foreach (range(1, 3) as $try) {
            $this->travel(15)->seconds();
            $passed += passwordTry('confirm', $own, 'password', $member, json: true)->getStatusCode() === 201 ? 1 : 0;
        }
        $this->travel(15)->seconds();
    }

    // The session with the wrong passwords got 4 answers of a wrong password, then it was over
    expect($answers)->toBe([400, 400, 400, 400, ...array_fill(0, 26, 401)])
        ->and($passed)->toBe(30);
    expectNothingChanged($member);

    // Then each form works in the session of the member
    foreach (['other sessions', 'profile', 'transfer', 'change'] as $place) {
        $response = passwordTry($place, $own, 'password', $member);
        expectThePlaceWorked($place, $response, $member);
        $own = $place === 'change' ? sessionCookieOf($response) : $own;
    }
    expectThePlaceWorked('deletion', passwordTry('deletion', $own, PASSWORD_TRIES_NEW_PASSWORD, $member), $member);
});

test('an Inertia form gets the redirect to the sign-in page at the fifth wrong password', function () {
    $member = passwordTriesMember();
    $another = signInWithForm($member[0])['session'];
    foreach (range(1, 4) as $try) {
        expectWrongPassword('change', passwordTry('change', $another, 'wrong-'.$try, $member));
    }

    [$method, $address, $body] = passwordTriesPlace('change', 'wrong-5', ...$member);
    // The form of the page names its error bag, and the browser sends the name again when it follows the redirect
    $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Error-Bag' => 'updatePassword', 'Referer' => url('/user/profile')];
    $response = passwordTriesForm(browserWith($another), $method, $address, $body, $inertia);

    $response->assertStatus(303)->assertRedirect(route('login'));
    $this->assertGuest('web');

    // The sign-in page gets the words as a warning, whatever the error bag of the form
    $page = browserWith(sessionCookieOf($response))->get('/login', [...$inertia, 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())])->assertOk()->json();
    expect($page['component'] ?? null)->toBe('Auth/Login')
        ->and($page['props']['warning'] ?? null)->toBe(PASSWORD_TRIES_SIGN_IN_AGAIN)
        ->and($page['props']['status'] ?? null)->toBeNull()
        ->and($page['props']['errors']['updatePassword']['session'] ?? null)->toBe(PASSWORD_TRIES_SIGN_IN_AGAIN);

    // The warning shows once: the next sign-in page has none
    expect(browserWith(sessionCookieOf($response))->get('/login')->assertOk()->inertiaPage()['props']['warning'] ?? null)->toBeNull();
});

test('the sign-in page of a visitor has no warning', function () {
    newBrowser();
    $page = $this->get('/login')->assertOk()->inertiaPage();

    expect(array_key_exists('warning', $page['props']))->toBeTrue()
        ->and($page['props']['warning'])->toBeNull()
        ->and($page['props']['status'] ?? null)->toBeNull();
});

test('the fifth wrong password forgets the "Remember me" cookie of that browser', function () {
    $member = passwordTriesMember();
    $signIn = signInWithForm($member[0], remember: true);
    $cookies = [...$signIn['session'], ...$signIn['remember']];
    foreach (range(1, 4) as $try) {
        expectWrongPassword('confirm', passwordTry('confirm', $cookies, 'wrong-'.$try, $member));
    }

    $last = passwordTry('confirm', $cookies, 'wrong-5', $member);

    $last->assertRedirect(route('login'));
    $rememberName = auth()->guard('web')->getRecallerName();
    $last->assertCookieExpired($rememberName);
});

test('only a wrong password counts, and a right password gives no tries back', function () {
    $member = passwordTriesMember();
    $cookies = signInWithForm($member[0])['session'];

    // More right passwords than the limit: none counts
    foreach (range(1, 8) as $try) {
        passwordTry('confirm', $cookies, 'password', $member, json: true)->assertStatus(201);
    }
    // A request without a password, and a right password with a new password that is not valid: no try
    foreach (range(1, 6) as $try) {
        browserWith($cookies)->put('/user/password', ['password' => PASSWORD_TRIES_NEW_PASSWORD, 'password_confirmation' => PASSWORD_TRIES_NEW_PASSWORD])->assertSessionHasErrorsIn('updatePassword', ['current_password']);
        browserWith($cookies)->put('/user/password', ['current_password' => 'password', 'password' => 'x', 'password_confirmation' => 'y'])->assertSessionHasErrorsIn('updatePassword', ['password']);
        browserWith($cookies)->delete('/user', [])->assertSessionHasErrors(['password']);
    }

    // Two wrong, a right one, two wrong, a right one: 4 wrong passwords, and the session goes on
    foreach (['wrong-1', 'wrong-2', 'password', 'wrong-3', 'wrong-4', 'password'] as $password) {
        $response = passwordTry('confirm', $cookies, $password, $member, json: true);
        $response->assertStatus($password === 'password' ? 201 : 400);
    }
    browserWith($cookies)->getJson('/user/confirmed-password-status')->assertOk();

    // The fifth wrong one, on another form: the tries of a session count together
    passwordTry('other sessions', $cookies, 'wrong-5', $member)->assertRedirect(route('login'));
    browserWith($cookies)->getJson('/user/confirmed-password-status')->assertUnauthorized();
});

test('the tries of a session are forgotten 15 minutes after the first one', function () {
    $member = passwordTriesMember();
    $cookies = signInWithForm($member[0])['session'];

    foreach (range(1, 4) as $try) {
        expectWrongPassword('confirm', passwordTry('confirm', $cookies, 'wrong-'.$try, $member));
    }
    $this->travel(15 * 60 + 1)->seconds();
    foreach (range(1, 4) as $try) {
        expectWrongPassword('confirm', passwordTry('confirm', $cookies, 'wrong-again-'.$try, $member));
    }
    browserWith($cookies)->getJson('/user/confirmed-password-status')->assertOk();

    passwordTry('confirm', $cookies, 'wrong-again-5', $member)->assertRedirect(route('login'));
});

test('the tries of one session do not count for another session of the account', function () {
    $member = passwordTriesMember();
    $first = signInWithForm($member[0])['session'];
    $second = signInWithForm($member[0])['session'];

    foreach (range(1, 4) as $try) {
        expectWrongPassword('confirm', passwordTry('confirm', $first, 'wrong-'.$try, $member));
        expectWrongPassword('change', passwordTry('change', $second, 'wrong-'.$try, $member));
    }

    passwordTry('confirm', $first, 'wrong-5', $member)->assertRedirect(route('login'));
    browserWith($second)->getJson('/user/confirmed-password-status')->assertOk();
    passwordTry('confirm', $second, 'password', $member, json: true)->assertStatus(201);
});

test('a token that makes 5 wrong tries in 15 minutes is refused for the rest of the 15 minutes, and is not deleted', function (string $place) {
    $member = passwordTriesMember();
    [$user] = $member;
    $token = $user->createToken('one', ['account:delete', 'communities:write', 'user:read']);
    $plain = $token->plainTextToken;
    $own = signInWithForm($user)['session'];

    foreach (range(1, 5) as $try) {
        expectWrongPassword($place, passwordTryWithToken($place, $plain, 'wrong-'.$try, $member));
        $this->travel(21)->seconds();
    }

    // The next try is refused before the password is checked, also with the right password, on each place of the token
    foreach (['api deletion', 'transfer'] as $next) {
        $refused = passwordTryWithToken($next, $plain, 'password', $member);
        $refused->assertStatus(429)->assertJsonPath('type', KnowiiApiResponseType::TooManyRequests->value);
        // The wait is the rest of the 15 minutes, not the minute of the limit of the route
        expect((int) $refused->headers->get('Retry-After'))->toBeGreaterThan(60)->toBeLessThanOrEqual(15 * 60);
    }
    expectNothingChanged($member);

    // The token is not deleted: it still reads the API
    expect($token->accessToken->fresh())->not->toBeNull();
    newBrowser();
    $this->withHeaders(['Authorization' => 'Bearer '.$plain])->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();

    // The session of the member is not slowed down
    passwordTry('confirm', $own, 'password', $member, json: true)->assertStatus(201);
    // The tokens of an account count together: another token of the account waits too, also a new one
    $other = $user->createToken('two', ['communities:write'])->plainTextToken;
    passwordTryWithToken('transfer', $other, 'password', $member)->assertStatus(429);
    expectNothingChanged($member);

    // 15 minutes after the first try, the token works again
    $this->travel(15)->minutes();
    expectThePlaceWorked($place, passwordTryWithToken($place, $plain, 'password', $member), $member);
})->with([
    'the deletion of the account' => ['api deletion'],
    'the transfer of the ownership of a community' => ['transfer'],
]);

test('six wrong tries of one credential at the same time give at most five checks of the password', function (string $credential) {
    [$user] = passwordTriesMember();
    $request = Request::create('/user/confirm-password', 'POST');
    if ($credential === 'session') {
        $session = app('session.store');
        $session->start();
        $request->setLaravelSession($session);
        Auth::guard('web')->setUser($user);
    } elseif ($credential === 'sessions of the cookie') {
        // Each try comes from another session that the cookie made
        Auth::guard('web')->setUser($user);
    } else {
        $user->withAccessToken($user->createToken('one', ['account:delete'])->accessToken);
    }

    // Each try starts while the check of the one before still runs
    $checks = 0;
    $started = 0;
    $try = function () use (&$try, &$checks, &$started, $user, $request, $credential): bool {
        $started++;
        if ($credential === 'sessions of the cookie') {
            $session = new Store('knowii-test', new ArraySessionHandler(10));
            $session->start();
            $session->put(RequestCredential::RECALLED_SESSION_KEY, true);
            $request = Request::create('/user/confirm-password', 'POST');
            $request->setLaravelSession($session);
        } elseif ($credential === 'tokens') {
            // Each try comes from another token of the account
            $user->withAccessToken($user->createToken('token-'.$started, ['account:delete'])->accessToken);
        }

        return PasswordTries::check($user, function () use (&$try, &$checks, &$started): bool {
            $checks++;
            if ($started < 6) {
                $try();
            }

            return false;
        }, $request);
    };

    expect($try)->toThrow(in_array($credential, ['token', 'tokens'], true) ? ThrottleRequestsException::class : AuthenticationException::class);
    expect($started)->toBe(6)
        ->and($checks)->toBe(5);
})->with([
    'a session' => ['session'],
    'the sessions that a "Remember me" cookie made' => ['sessions of the cookie'],
    'a token' => ['token'],
    'the tokens of an account' => ['tokens'],
]);

test('the API tokens of an account share one count: 12 tokens give at most 5 checks of the password together', function () {
    $member = passwordTriesMember();
    [$user] = $member;
    $session = signInWithForm($user)['session'];
    $own = signInWithForm($user)['session'];

    $answers = [];
    foreach (range(1, 12) as $n) {
        // The API tokens page makes a token for a session, without the password
        browserWith($session)->post('/user/api-tokens', ['name' => 'token-'.$n, 'permissions' => ['account:delete', 'communities:write']])->assertSessionHasNoErrors();
        $plain = session('flash')['token'] ?? null;
        expect($plain)->toBeString();

        foreach (['api deletion', 'api deletion', 'api deletion', 'transfer', 'transfer', 'transfer'] as $try => $place) {
            $answers[] = passwordTryWithToken($place, $plain, 'wrong-'.$n.'-'.$try, $member)->getStatusCode();
        }

        // The member, in a session that signed in, with the right password: never slowed down
        passwordTry('confirm', $own, 'password', $member, json: true)->assertStatus(201);
    }

    // 5 answers of a wrong password for all the tokens together, then each token waits
    expect($user->tokens()->count())->toBe(12)
        ->and($answers)->toBe([...array_fill(0, 5, 400), ...array_fill(0, 67, 429)]);
    expectNothingChanged($member);

    // The sessions of the member keep their own count: a wrong password is a wrong password, and the right one passes
    foreach ([$session, $own] as $cookies) {
        passwordTry('confirm', $cookies, 'not-the-password', $member, json: true)->assertStatus(400);
        passwordTry('confirm', $cookies, 'password', $member, json: true)->assertStatus(201);
    }

    // 15 minutes after the first try, a token of the account works again
    $this->travel(15)->minutes();
    $last = $user->createToken('last', ['communities:write'])->plainTextToken;
    expectThePlaceWorked('transfer', passwordTryWithToken('transfer', $last, 'password', $member), $member);
});

test('the sessions that a "Remember me" cookie made share one count: 30 requests with only that cookie give at most 5 checks of the password', function () {
    $member = passwordTriesMember();
    [$user] = $member;
    $own = signInWithForm($user)['session'];
    $signIn = signInWithForm($user, remember: true);
    $rememberToken = $user->fresh()?->getRememberToken();

    $answers = [];
    $ids = [];
    foreach (range(1, 30) as $try) {
        // No cookie of a session: the "Remember me" cookie makes a new session for each request
        $response = browserWith($signIn['remember'])->postJson('/user/confirm-password', ['password' => 'wrong-'.$try]);
        $answers[] = $response->getStatusCode();
        $ids[] = array_values(sessionCookieOf($response))[0];
        if ($try === 5) {
            $response->assertJsonPath('message', PASSWORD_TRIES_SIGN_IN_AGAIN);
        }

        // The member, in a session that signed in with the form, with the right password: never slowed down
        passwordTry('confirm', $own, 'password', $member, json: true)->assertStatus(201);
    }

    // 4 answers of a wrong password, the fifth wrong one ends the session, and the cookie signs nobody in after it
    expect(count(array_unique($ids)))->toBe(30)
        ->and($answers)->toBe([400, 400, 400, 400, ...array_fill(0, 26, 401)])
        ->and($user->fresh()?->getRememberToken())->not->toBe($rememberToken);
    expectNothingChanged($member);
    browserWith($signIn['remember'])->get('/dashboard')->assertRedirect(route('login'));

    // The session of the form sign-in of that browser has its own count, and goes on
    passwordTry('confirm', $signIn['session'], 'not-the-password', $member, json: true)->assertStatus(400);
    passwordTry('confirm', $signIn['session'], 'password', $member, json: true)->assertStatus(201);
});

test('a session of a sign-in keeps its own count, and each session that the "Remember me" cookie made counts with the others', function () {
    $member = passwordTriesMember();
    [$user] = $member;
    $signIn = signInWithForm($user, remember: true);
    $signedIn = $signIn['session'];
    // A session that the cookie made earlier: the browser of the member after its session expired
    $earlier = sessionCookieOf(browserWith($signIn['remember'])->get('/dashboard')->assertOk());

    // 4 wrong passwords in the session of the sign-in: its own count
    foreach (range(1, 4) as $try) {
        passwordTry('confirm', $signedIn, 'wrong-'.$try, $member, json: true)->assertStatus(400);
    }

    // Two new sessions from the cookie, 2 wrong passwords each: one count for them
    foreach (range(1, 2) as $n) {
        $recalled = sessionCookieOf(browserWith($signIn['remember'])->get('/dashboard')->assertOk());
        expect(array_values($recalled)[0])->not->toBe(array_values($earlier)[0]);
        foreach (range(1, 2) as $try) {
            passwordTry('confirm', $recalled, 'wrong-'.$n.'-'.$try, $member, json: true)->assertStatus(400);
        }
    }

    // The fifth wrong password of these sessions together, in a third one: that session ends
    $third = sessionCookieOf(browserWith($signIn['remember'])->get('/dashboard')->assertOk());
    passwordTry('confirm', $third, 'wrong-5', $member, json: true)->assertUnauthorized()->assertJsonPath('message', PASSWORD_TRIES_SIGN_IN_AGAIN);
    browserWith($third)->getJson('/user/confirmed-password-status')->assertUnauthorized();

    // The earlier session of the cookie is at the limit too: its next try ends it, also with the right password
    browserWith($earlier)->getJson('/user/confirmed-password-status')->assertOk();
    $ended = passwordTry('confirm', $earlier, 'password', $member);
    $ended->assertRedirect(route('login'));
    expect(browserWith(sessionCookieOf($ended))->get('/login')->assertOk()->inertiaPage()['props']['warning'] ?? null)->toBe(PASSWORD_TRIES_SIGN_IN_AGAIN);
    browserWith($earlier)->getJson('/user/confirmed-password-status')->assertUnauthorized();

    // The session of the sign-in is at 4 of its own: the right password passes
    passwordTry('confirm', $signedIn, 'password', $member, json: true)->assertStatus(201);

    // The person signs in again, and that session starts its own count
    $again = signInWithForm($user)['session'];
    passwordTry('confirm', $again, 'not-the-password', $member, json: true)->assertStatus(400);
    passwordTry('confirm', $again, 'password', $member, json: true)->assertStatus(201);
    expectNothingChanged($member);
});

test('a sign-in with the password in a browser that the "Remember me" cookie signed in: the session has its own count', function (bool $withTheSession) {
    $member = passwordTriesMember();
    [$user] = $member;
    $signIn = signInWithForm($user, remember: true);
    $stateful = ['Referer' => config('app.url'), 'Origin' => config('app.url')];

    // 4 wrong passwords of the sessions of the cookie
    $recalled = sessionCookieOf(browserWith($signIn['remember'])->get('/dashboard')->assertOk());
    foreach (range(1, 4) as $try) {
        passwordTry('confirm', $recalled, 'wrong-'.$try, $member, json: true)->assertStatus(400);
    }

    // The person signs in with the password: in the session that the cookie made, or in a request that holds only the
    // cookie, which signs the request in before the password does
    $browser = browserWith($withTheSession ? $recalled : $signIn['remember'])->withHeaders($stateful);
    $signedIn = sessionCookieOf($browser->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), ['email' => $user->email, 'password' => 'password'])->assertOk());
    expect(array_values($signedIn)[0])->not->toBe(array_values($recalled)[0]);

    // Its own count: 4 answers of a wrong password, and the right one passes
    foreach (range(1, 4) as $try) {
        passwordTry('confirm', $signedIn, 'wrong-again-'.$try, $member, json: true)->assertStatus(400);
    }
    passwordTry('confirm', $signedIn, 'password', $member, json: true)->assertStatus(201);

    // The sessions of the cookie still count together: their fifth wrong password ends the session that makes it
    $next = sessionCookieOf(browserWith($signIn['remember'])->get('/dashboard')->assertOk());
    passwordTry('confirm', $next, 'wrong-5', $member, json: true)->assertUnauthorized()->assertJsonPath('message', PASSWORD_TRIES_SIGN_IN_AGAIN);
})->with([
    'in the session that the cookie made' => [true],
    'in a request with only the cookie' => [false],
]);

test('after a session ended for wrong passwords, a "Remember me" cookie of before gets the sign-in page', function () {
    $member = passwordTriesMember();
    [$user] = $member;
    $own = signInWithForm($user)['session'];
    $signIn = signInWithForm($user, remember: true);
    $rememberToken = $user->fresh()?->getRememberToken();

    foreach (range(1, 4) as $try) {
        expectWrongPassword('confirm', passwordTry('confirm', $signIn['session'], 'wrong-'.$try, $member));
    }
    // While no session ended, the cookie signs in
    browserWith($signIn['remember'])->get('/dashboard')->assertOk();

    passwordTry('confirm', $signIn['session'], 'wrong-5', $member)->assertRedirect(route('login'));

    expect($user->fresh()?->getRememberToken())->not->toBe($rememberToken);
    browserWith($signIn['remember'])->get('/dashboard')->assertRedirect(route('login'));
    browserWith($signIn['remember'])->getJson('/user/confirmed-password-status')->assertUnauthorized();
    expectNothingChanged($member);

    // The session of the member goes on
    browserWith($own)->get('/dashboard')->assertOk();
    passwordTry('confirm', $own, 'password', $member, json: true)->assertStatus(201);
});

test('the limits of the routes of the API count per credential: the requests of a token do not close the route for the session of the member', function (string $place, int $limit) {
    $member = passwordTriesMember();
    [$user] = $member;
    $plain = $user->createToken('one', ['account:delete', 'communities:write'])->plainTextToken;
    $own = signInWithForm($user)['session'];

    // Requests without a password: no try of the password, and the limit of the route for this token
    foreach (range(1, $limit) as $request) {
        [$method, $address] = passwordTriesPlace($place, '', ...$member);
        newBrowser();
        $this->withHeaders(['Authorization' => 'Bearer '.$plain])->json($method, $address, [])->assertStatus(400);
    }
    passwordTryWithToken($place, $plain, 'password', $member)->assertStatus(429);
    expectNothingChanged($member);

    expectThePlaceWorked($place, passwordTry($place, $own, 'password', $member), $member);
})->with([
    'the deletion of the account' => ['api deletion', Constants::ACCOUNT_DELETIONS_PER_MINUTE],
    'the transfer of the ownership of a community' => ['transfer', Constants::OWNERSHIP_TRANSFERS_PER_MINUTE],
]);
