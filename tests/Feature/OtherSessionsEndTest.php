<?php

use App\ApiRoutes;
use App\Contracts\Auth\EndsOtherSessions;
use App\Exceptions\AccountChangedException;
use App\Http\Middleware\EndOutdatedSessions;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

/*
 * The version of the sessions ends the other sessions of an account (ADR 0059, ADR 0066). A change or a reset of the
 * password adds 1 to the version of the account, in the write of the new password: every other session of the account ends
 * at its next request, on every route and whatever the session driver, and the session that made the change goes on, with
 * a new session id. "Log out other browser sessions" does the same. The second factor does the same when it is confirmed,
 * turned off, or when its recovery codes are made again, and the remember token of the account is written again. A change
 * whose account changed elsewhere while its request ran writes nothing, and says so. The tests use real cookies, each
 * browser with its own.
 */

const OTHER_SESSIONS_NEW_PASSWORD = 'A-new-password-of-2026!';

const OTHER_SESSIONS_PASSWORD_FROM_ELSEWHERE = 'The-password-from-elsewhere-1!';

const OTHER_SESSIONS_CHANGED_ELSEWHERE = 'Your account changed on another device. Sign in again.';

function otherSessionsMember(): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com'])->fresh();
}

/**
 * One request of the browser that holds these cookies
 *
 * @param  array<string, string>  $cookies
 */
function otherSessionsRequest(string $endpoint, array $cookies, User $user): TestResponse
{
    return match ($endpoint) {
        'page' => browserWith($cookies)->get('/dashboard'),
        'fortify' => browserWith($cookies)->getJson('/user/confirmed-password-status'),
        'api' => browserWith($cookies)->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])->getJson(ApiRoutes::path(ApiRoutes::USER)),
        'channel' => withCheckingBroadcaster(fn (): TestResponse => browserWith($cookies)->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.$user->cuid])),
    };
}

/**
 * The password of the account changes in another browser: with the form of the profile (a session of the account), or with
 * a reset by mail (no session). Returns the cookies of the session that made the change, if any
 *
 * @return array<string, string>
 */
function otherSessionsChangePassword(User $user, string $way): array
{
    if ($way === 'form') {
        $cookies = signInWithForm($user)['session'];
        // The session that changes the password gets a new session id: the browser goes on with the cookie of the answer
        $cookies = sessionCookieOf(browserWith($cookies)->put('/user/password', [
            'current_password' => 'password',
            'password' => OTHER_SESSIONS_NEW_PASSWORD,
            'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD,
        ])->assertSessionHasNoErrors());
    } else {
        $cookies = [];
        newBrowser();
        Notification::fake();
        test()->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $token = '';
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        test()->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));
    }

    expect(Hash::check(OTHER_SESSIONS_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();

    return $cookies;
}

/**
 * The second factor of the account, turned on in the database
 */
function otherSessionsSecondFactor(User $user): string
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

dataset('session drivers', [
    'the array driver' => ['array'],
    'the database driver' => ['database'],
]);

dataset('routes of a session', [
    'a page' => ['page'],
    'a route of Fortify' => ['fortify'],
    'the API of the session' => ['api'],
    'the authorization of a live channel' => ['channel'],
]);

// ---------------------------------------------------------------------------------------------------------------------
// The password
// ---------------------------------------------------------------------------------------------------------------------

test('a change or a reset of the password ends every other session of the account, on every route', function (string $driver, string $way, bool $openedAPage, string $endpoint) {
    useSessionDriver($driver);
    $user = otherSessionsMember();

    // The other browser signs in with the form. It opens a page before the change, or it opens none
    $other = signInWithForm($user)['session'];
    if ($openedAPage) {
        browserWith($other)->get('/dashboard')->assertOk();
    }
    // A route of Fortify is no page: the session is signed in, and still opened no page
    otherSessionsRequest('fortify', $other, $user)->assertOk();

    otherSessionsChangePassword($user, $way);

    $after = otherSessionsRequest($endpoint, $other, $user);
    $endpoint === 'page' ? $after->assertRedirect(route('login')) : $after->assertUnauthorized();
    $this->assertGuest('web');

    // The session is gone: the next request of the same browser is the one of a visitor
    otherSessionsRequest('fortify', $other, $user)->assertUnauthorized();
    expect($user->fresh()?->session_version)->toBe(1);
})->with('session drivers')->with([
    'the form of the profile' => ['form'],
    'a reset by mail' => ['reset'],
])->with([
    'a session that opened a page' => [true],
    'a session that never opened a page' => [false],
])->with('routes of a session');

test('the session that changed the password goes on, on every route', function (string $driver) {
    useSessionDriver($driver);
    $user = otherSessionsMember();
    $before = signInWithForm($user)['session'];
    browserWith($before)->get('/dashboard')->assertOk();
    // The session holds the password of the account for the check of the API too
    otherSessionsRequest('api', $before, $user)->assertOk();

    $present = sessionCookieOf(browserWith($before)->put('/user/password', [
        'current_password' => 'password',
        'password' => OTHER_SESSIONS_NEW_PASSWORD,
        'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD,
    ])->assertSessionHasNoErrors());

    foreach (['api', 'page', 'fortify', 'channel', 'page'] as $endpoint) {
        otherSessionsRequest($endpoint, $present, $user)->assertOk();
        $this->assertAuthenticatedAs($user);
    }
    expect($user->fresh()?->session_version)->toBe(1);
})->with('session drivers');

test('the session that changes the password gets a new session id, as after a sign-in', function (string $driver) {
    useSessionDriver($driver);
    $user = otherSessionsMember();
    $before = signInWithForm($user)['session'];
    browserWith($before)->get('/dashboard')->assertOk();

    $present = sessionCookieOf(browserWith($before)->put('/user/password', [
        'current_password' => 'password',
        'password' => OTHER_SESSIONS_NEW_PASSWORD,
        'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD,
    ])->assertSessionHasNoErrors());
    expect(array_values($present)[0])->not->toBe(array_values($before)[0]);

    // The session id of before signs nobody in any more
    otherSessionsRequest('page', $before, $user)->assertRedirect(route('login'));
    otherSessionsRequest('fortify', $before, $user)->assertUnauthorized();
    // The session goes on under its new id
    otherSessionsRequest('page', $present, $user)->assertOk();
    otherSessionsRequest('fortify', $present, $user)->assertOk();
})->with('session drivers');

test('a wrong current password gives no new session id', function () {
    $user = otherSessionsMember();
    $before = signInWithForm($user)['session'];

    $after = sessionCookieOf(browserWith($before)->put('/user/password', [
        'current_password' => 'not-the-password',
        'password' => OTHER_SESSIONS_NEW_PASSWORD,
        'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD,
    ])->assertSessionHasErrorsIn('updatePassword', ['current_password']));

    expect($after)->toBe($before);
});

test('after a reset the person signs in with the new password, and that session goes on', function () {
    $user = otherSessionsMember();
    otherSessionsChangePassword($user, 'reset');

    $cookies = signInWithForm($user->fresh(), OTHER_SESSIONS_NEW_PASSWORD)['session'];
    expect(session(EndOutdatedSessions::SESSION_KEY))->toBe(1);
    foreach (['page', 'fortify', 'api'] as $endpoint) {
        otherSessionsRequest($endpoint, $cookies, $user)->assertOk();
    }
});

test('a change of the password that fails ends no session', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    $present = signInWithForm($user)['session'];

    browserWith($present)->put('/user/password', [
        'current_password' => 'not-the-password',
        'password' => OTHER_SESSIONS_NEW_PASSWORD,
        'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD,
    ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

    expect($user->fresh()?->session_version)->toBe(0);
    otherSessionsRequest('page', $other, $user)->assertOk();
    otherSessionsRequest('page', $present, $user)->assertOk();
});

test('each change of the password adds 1 to the version, and only the last session goes on', function () {
    $user = otherSessionsMember();
    $first = signInWithForm($user)['session'];
    browserWith($first)->put('/user/password', ['current_password' => 'password', 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD])->assertSessionHasNoErrors();

    $second = signInWithForm($user->fresh(), OTHER_SESSIONS_NEW_PASSWORD)['session'];
    $second = sessionCookieOf(browserWith($second)->put('/user/password', ['current_password' => OTHER_SESSIONS_NEW_PASSWORD, 'password' => 'Another-password-of-2026!', 'password_confirmation' => 'Another-password-of-2026!'])->assertSessionHasNoErrors());

    expect($user->fresh()?->session_version)->toBe(2);
    otherSessionsRequest('page', $first, $user)->assertRedirect(route('login'));
    otherSessionsRequest('page', $second, $user)->assertOk();
});

test('one session makes two changes in a row: each one is written, and the session goes on', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    otherSessionsSecondFactor($user);
    $present = signInWithFormBeforeSecondFactor($user, 'regenerate');

    // Two changes of the password
    $present = sessionCookieOf(browserWith($present)->put('/user/password', ['current_password' => 'password', 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD])->assertSessionHasNoErrors());
    $present = sessionCookieOf(browserWith($present)->put('/user/password', ['current_password' => OTHER_SESSIONS_NEW_PASSWORD, 'password' => 'Another-password-of-2026!', 'password_confirmation' => 'Another-password-of-2026!'])->assertSessionHasNoErrors());
    expect($user->fresh()?->session_version)->toBe(2)
        ->and(Hash::check('Another-password-of-2026!', (string) $user->fresh()?->password))->toBeTrue();

    // Then new recovery codes, two times
    browserWith($present)->post('/user/confirm-password', ['password' => 'Another-password-of-2026!'])->assertSessionHasNoErrors();
    browserWith($present)->post('/user/two-factor-recovery-codes')->assertSessionHasNoErrors();
    $codes = $user->fresh()?->recoveryCodes();
    browserWith($present)->post('/user/two-factor-recovery-codes')->assertSessionHasNoErrors();

    expect($user->fresh()?->session_version)->toBe(4)
        ->and($user->fresh()?->recoveryCodes())->toHaveCount(8)->not->toBe($codes);
    foreach (['page', 'fortify', 'api'] as $endpoint) {
        otherSessionsRequest($endpoint, $present, $user)->assertOk();
    }
    otherSessionsRequest('page', $other, $user)->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------------------------------------------------
// The second factor
// ---------------------------------------------------------------------------------------------------------------------

test('the second factor ends the other sessions and the remember cookie of before, and the present session goes on', function (string $driver, string $action) {
    useSessionDriver($driver);
    $user = otherSessionsMember();

    // The other browser signed in with "Remember me": it holds a session and the remember cookie
    $other = signInWithForm($user, remember: true);
    expect($other['remember'])->not->toBe([]);
    browserWith($other['session'])->get('/dashboard')->assertOk();
    $rememberToken = $user->fresh()?->getRememberToken();

    if ($action !== 'confirm') {
        otherSessionsSecondFactor($user);
    }

    // The present browser signed in before, confirms the password, then acts on the second factor
    $present = signInWithFormBeforeSecondFactor($user, $action);
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
    if ($action === 'confirm') {
        // "Enable" stores a secret and changes nothing for the sessions: the confirmation turns the second factor on
        browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        expect($user->fresh()?->session_version)->toBe(0);
        browserWith($other['session'])->get('/dashboard')->assertOk();

        $secret = decrypt((string) $user->fresh()?->two_factor_secret);
        browserWith($present)->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHasNoErrors();
        expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
    } elseif ($action === 'disable') {
        browserWith($present)->delete('/user/two-factor-authentication')->assertSessionHasNoErrors();
        expect($user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
    } else {
        browserWith($present)->post('/user/two-factor-recovery-codes')->assertSessionHasNoErrors();
        expect($user->fresh()?->recoveryCodes())->toHaveCount(8);
    }

    // The other session is refused, on a page and on a route of Fortify
    otherSessionsRequest('page', $other['session'], $user)->assertRedirect(route('login'));
    $this->assertGuest('web');
    otherSessionsRequest('fortify', $other['session'], $user)->assertUnauthorized();

    // The remember cookie of before signs nobody in: the person signs in again
    otherSessionsRequest('page', $other['remember'], $user)->assertRedirect(route('login'));
    $this->assertGuest('web');
    otherSessionsRequest('fortify', $other['remember'], $user)->assertUnauthorized();

    // The present session goes on
    foreach (['page', 'fortify', 'api', 'page'] as $endpoint) {
        otherSessionsRequest($endpoint, $present, $user)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    expect($user->fresh()?->session_version)->toBe(1)
        ->and($user->fresh()?->getRememberToken())->not->toBe($rememberToken);
})->with('session drivers')->with([
    'when it is confirmed' => ['confirm'],
    'when it is turned off' => ['disable'],
    'when its recovery codes are made again' => ['regenerate'],
]);

/**
 * The present browser of the test above: a sign-in with the form, with the code of the second factor when it is on
 *
 * @return array<string, string>
 */
function signInWithFormBeforeSecondFactor(User $user, string $action): array
{
    if ($action === 'confirm') {
        return signInWithForm($user)['session'];
    }

    newBrowser();
    test()->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $secret = decrypt((string) $user->fresh()?->two_factor_secret);
    $response = test()->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)]);
    $response->assertRedirect();
    test()->assertAuthenticatedAs($user);
    $name = config()->string('session.cookie');

    return [$name => (string) $response->getCookie($name)?->getValue()];
}

test('the "Remember me" cookie of the present browser ends with the others, and its session goes on', function (string $change) {
    $user = otherSessionsMember();
    $signIn = signInWithForm($user, remember: true);
    $present = $signIn['session'];
    expect($signIn['remember'])->not->toBe([]);

    if ($change === 'password') {
        $present = sessionCookieOf(browserWith($present)->put('/user/password', ['current_password' => 'password', 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD])->assertSessionHasNoErrors());
    } else {
        browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $secret = decrypt((string) $user->fresh()?->two_factor_secret);
        browserWith($present)->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHasNoErrors();
    }

    otherSessionsRequest('page', $present, $user)->assertOk();
    // The cookie alone, as after the end of the session: the person signs in again
    otherSessionsRequest('page', $signIn['remember'], $user)->assertRedirect(route('login'));
    $this->assertGuest('web');
})->with([
    'after a change of the password' => ['password'],
    'after a change of the second factor' => ['second factor'],
]);

test('a set-up of the second factor that is cancelled, and new codes before the confirmation, end no session', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    $present = signInWithForm($user)['session'];
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();

    browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    browserWith($present)->post('/user/two-factor-recovery-codes')->assertSessionHasNoErrors();
    browserWith($present)->delete('/user/two-factor-authentication')->assertSessionHasNoErrors();

    expect($user->fresh()?->session_version)->toBe(0)
        ->and($user->fresh()?->two_factor_secret)->toBeNull();
    otherSessionsRequest('page', $other, $user)->assertOk();
});

test('the confirmation of the password holds for 15 minutes', function () {
    expect(config('auth.password_timeout'))->toBe(900);

    $user = otherSessionsMember();
    $this->actingAs($user)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();

    $this->travel(14)->minutes();
    $this->getJson('/user/confirmed-password-status')->assertOk()->assertJsonPath('confirmed', true);
    $this->travel(2)->minutes();
    $this->getJson('/user/confirmed-password-status')->assertOk()->assertJsonPath('confirmed', false);
});

test('turning the second factor on again never replaces a second factor that is on', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user, remember: true);
    otherSessionsSecondFactor($user);
    $stored = $user->fresh();
    $present = signInWithFormBeforeSecondFactor($user, 'enable again');
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();

    // "Enable" again, with and without `force`: the secret and the recovery codes of the account stay
    browserWith($present)->post('/user/two-factor-authentication', ['force' => 1])->assertSessionHasNoErrors();
    browserWith($present)->postJson('/user/two-factor-authentication', ['force' => true])->assertOk();
    browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();

    $after = $user->fresh();
    expect($after?->two_factor_secret)->toBe($stored?->two_factor_secret)
        ->and($after?->two_factor_recovery_codes)->toBe($stored?->two_factor_recovery_codes)
        ->and($after?->two_factor_confirmed_at)->not->toBeNull()
        ->and($after?->session_version)->toBe(0)
        ->and($after?->getRememberToken())->toBe($stored?->getRememberToken());
    // Nothing changed, so no session ends
    otherSessionsRequest('page', $other['session'], $user)->assertOk();
    otherSessionsRequest('page', $present, $user)->assertOk();
});

test('before the confirmation, "Enable" with force makes a new secret, and ends no session', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    $present = signInWithForm($user)['session'];
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();

    browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    $first = $user->fresh()?->two_factor_secret;
    browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    expect($user->fresh()?->two_factor_secret)->toBe($first);

    browserWith($present)->post('/user/two-factor-authentication', ['force' => 1])->assertSessionHasNoErrors();
    expect($user->fresh()?->two_factor_secret)->not->toBeNull()->not->toBe($first)
        ->and($user->fresh()?->two_factor_confirmed_at)->toBeNull()
        ->and($user->fresh()?->session_version)->toBe(0);
    otherSessionsRequest('page', $other, $user)->assertOk();
});

// ---------------------------------------------------------------------------------------------------------------------
// "Log out other browser sessions"
// ---------------------------------------------------------------------------------------------------------------------

test('"Log out other browser sessions" ends every other session of the account, on every route', function (string $driver, bool $openedAPage, string $endpoint) {
    useSessionDriver($driver);
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    if ($openedAPage) {
        browserWith($other)->get('/dashboard')->assertOk();
    }
    $present = signInWithForm($user)['session'];
    browserWith($present)->get('/user/profile')->assertOk();

    browserWith($present)->delete('/user/other-browser-sessions', ['password' => 'password'])->assertSessionHasNoErrors()->assertStatus(303);

    $after = otherSessionsRequest($endpoint, $other, $user);
    $endpoint === 'page' ? $after->assertRedirect(route('login')) : $after->assertUnauthorized();
    $this->assertGuest('web');
    otherSessionsRequest('fortify', $other, $user)->assertUnauthorized();
    expect($user->fresh()?->session_version)->toBe(1);
})->with('session drivers')->with([
    'a session that opened a page' => [true],
    'a session that never opened a page' => [false],
])->with('routes of a session');

test('the session that pressed "Log out other browser sessions" goes on: the pages, the routes of Fortify and its next API call', function (string $driver, string $first) {
    useSessionDriver($driver);
    $user = otherSessionsMember();
    $other = signInWithForm($user, remember: true);
    $signIn = signInWithForm($user, remember: true);
    $present = $signIn['session'];
    browserWith($present)->get('/user/profile')->assertOk();
    // The session holds the password of the account for the check of the pages and for the check of the API
    otherSessionsRequest('api', $present, $user)->assertOk();

    $response = browserWith([...$present, ...$signIn['remember']])->delete('/user/other-browser-sessions', ['password' => 'password']);
    $response->assertSessionHasNoErrors()->assertStatus(303);

    foreach ([$first, 'api', 'page', 'fortify', 'channel', 'api', 'page'] as $endpoint) {
        otherSessionsRequest($endpoint, $present, $user)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    // "Remember me" of this browser goes on with the cookie of the answer; the cookie of the other browser signs nobody in
    $rememberName = auth()->guard('web')->getRecallerName();
    $remember = $response->getCookie($rememberName)?->getValue();
    expect($remember)->toBeString()->not->toBe('')->not->toBe($signIn['remember'][$rememberName]);
    otherSessionsRequest('page', [$rememberName => (string) $remember], $user)->assertOk();
    otherSessionsRequest('page', $other['remember'], $user)->assertRedirect(route('login'));
})->with('session drivers')->with([
    'a page first' => ['page'],
    'a route of Fortify first' => ['fortify'],
    'an API call first' => ['api'],
]);

test('a wrong password on "Log out other browser sessions" ends no session', function () {
    $user = otherSessionsMember();
    $other = signInWithForm($user)['session'];
    $present = signInWithForm($user)['session'];
    $hash = $user->fresh()?->password;

    browserWith($present)->delete('/user/other-browser-sessions', ['password' => 'not-the-password'])->assertSessionHasErrors(['password']);

    expect($user->fresh()?->session_version)->toBe(0)
        ->and($user->fresh()?->password)->toBe($hash);
    otherSessionsRequest('page', $other, $user)->assertOk();
    otherSessionsRequest('page', $present, $user)->assertOk();
});

// ---------------------------------------------------------------------------------------------------------------------
// A change of an account that changed elsewhere while the request ran
// ---------------------------------------------------------------------------------------------------------------------

/**
 * Another change of the account is written right after the next request reads the account, as when the member changes the
 * password on another device while the request runs: the request holds the account as it was before that change.
 * Returns the hash of the password that the other change wrote
 */
function otherSessionsChangeElsewhereDuringTheNextRequest(User $user): string
{
    $hash = Hash::make(OTHER_SESSIONS_PASSWORD_FROM_ELSEWHERE);
    $done = false;
    User::retrieved(function (User $model) use (&$done, $user, $hash): void {
        if (! $done && $model->getKey() === $user->getKey()) {
            $done = true;
            DB::table('users')->where('id', $user->id)->update(['password' => $hash, 'session_version' => DB::raw('session_version + 1')]);
        }
    });

    return $hash;
}

/**
 * The session of a change that was not written is over: the browser is on the sign-in page, which says why, and its
 * requests are the ones of a visitor
 *
 * @param  array<string, string>  $before  The cookie that the browser held when it sent the change
 */
function expectTheSessionEndedWithWords(TestResponse $response, array $before, User $user): void
{
    $response->assertRedirect(route('login'));
    $after = sessionCookieOf($response);
    expect(array_values($after)[0])->not->toBe(array_values($before)[0]);

    $page = browserWith($after)->get('/login')->assertOk()->inertiaPage();
    // The words are a warning of the sign-in page, never its status, which the page shows as a success. They are an error
    // of the page too: the form that was sent sees no success
    expect($page['props']['warning'] ?? null)->toBe(OTHER_SESSIONS_CHANGED_ELSEWHERE)
        ->and($page['props']['status'] ?? null)->toBeNull()
        ->and($page['props']['errors']['session'] ?? null)->toBe(OTHER_SESSIONS_CHANGED_ELSEWHERE)
        ->and($page['clearHistory'] ?? null)->toBeTrue();

    foreach ([$before, $after] as $cookies) {
        otherSessionsRequest('fortify', $cookies, $user)->assertUnauthorized();
        otherSessionsRequest('api', $cookies, $user)->assertUnauthorized();
        otherSessionsRequest('page', $cookies, $user)->assertRedirect(route('login'));
    }
}

test('a change of the password whose account changed elsewhere meanwhile writes nothing, and its session ends', function (string $driver) {
    useSessionDriver($driver);
    $user = otherSessionsMember();
    $present = signInWithForm($user)['session'];
    browserWith($present)->get('/dashboard')->assertOk();
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();

    $elsewhere = otherSessionsChangeElsewhereDuringTheNextRequest($user);
    $response = browserWith($present)->put('/user/password', ['current_password' => 'password', 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD]);

    // The account holds the change from elsewhere only
    expect($user->fresh()?->password)->toBe($elsewhere)
        ->and($user->fresh()?->session_version)->toBe(1);
    expectTheSessionEndedWithWords($response, $present, $user);

    // The session that lost can change nothing after it: not the name, not the second factor
    browserWith($present)->put('/user/profile-information', ['name' => 'Changed After The Lost Write', 'email' => $user->email]);
    browserWith($present)->postJson('/user/two-factor-authentication')->assertUnauthorized();
    expect($user->fresh()?->name)->toBe($user->name)
        ->and($user->fresh()?->two_factor_secret)->toBeNull()
        ->and($user->fresh()?->session_version)->toBe(1);
})->with('session drivers');

test('a request that expects JSON gets a 401 that says why, when its change was not written', function () {
    $user = otherSessionsMember();
    $present = signInWithForm($user)['session'];

    $elsewhere = otherSessionsChangeElsewhereDuringTheNextRequest($user);
    browserWith($present)->putJson('/user/password', ['current_password' => 'password', 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD])
        ->assertUnauthorized()
        ->assertJsonPath('message', OTHER_SESSIONS_CHANGED_ELSEWHERE);

    expect($user->fresh()?->password)->toBe($elsewhere)
        ->and($user->fresh()?->session_version)->toBe(1);
    otherSessionsRequest('fortify', $present, $user)->assertUnauthorized();
});

test('a reset of the password whose account changed elsewhere meanwhile writes nothing, says so, and its link still works', function () {
    $user = otherSessionsMember();
    newBrowser();
    Notification::fake();
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    $token = '';
    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });
    $reset = ['token' => $token, 'email' => $user->email, 'password' => OTHER_SESSIONS_NEW_PASSWORD, 'password_confirmation' => OTHER_SESSIONS_NEW_PASSWORD];

    $elsewhere = otherSessionsChangeElsewhereDuringTheNextRequest($user);
    $this->post('/reset-password', $reset)->assertSessionHasErrors(['email' => 'Your account changed during this request. Nothing was saved. Please try again.']);

    expect($user->fresh()?->password)->toBe($elsewhere)
        ->and($user->fresh()?->session_version)->toBe(1);
    $this->assertGuest('web');

    // The same link, again: the reset is written
    $this->post('/reset-password', $reset)->assertSessionHasNoErrors()->assertRedirect(route('login'));
    expect(Hash::check(OTHER_SESSIONS_NEW_PASSWORD, (string) $user->fresh()?->password))->toBeTrue()
        ->and($user->fresh()?->session_version)->toBe(2);
});

test('a change of the second factor whose account changed elsewhere meanwhile writes nothing, and its session ends', function (string $action) {
    $user = otherSessionsMember();
    if ($action !== 'confirm') {
        otherSessionsSecondFactor($user);
    }
    $present = signInWithFormBeforeSecondFactor($user, $action);
    browserWith($present)->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
    if ($action === 'confirm') {
        browserWith($present)->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    }
    $stored = $user->fresh();
    $code = (new Google2FA)->getCurrentOtp(decrypt((string) $stored?->two_factor_secret));

    otherSessionsChangeElsewhereDuringTheNextRequest($user);
    $response = match ($action) {
        'confirm' => browserWith($present)->post('/user/confirmed-two-factor-authentication', ['code' => $code]),
        'disable' => browserWith($present)->delete('/user/two-factor-authentication'),
        'regenerate' => browserWith($present)->post('/user/two-factor-recovery-codes'),
    };

    // The second factor and the remember token of the account are the ones of before
    $after = $user->fresh();
    expect($after?->two_factor_secret)->toBe($stored?->two_factor_secret)
        ->and($after?->two_factor_recovery_codes)->toBe($stored?->two_factor_recovery_codes)
        ->and($after?->getRawOriginal('two_factor_confirmed_at'))->toBe($stored?->getRawOriginal('two_factor_confirmed_at'))
        ->and($after?->getRememberToken())->toBe($stored?->getRememberToken())
        ->and($after?->session_version)->toBe(1);
    expectTheSessionEndedWithWords($response, $present, $user);
})->with([
    'when it is confirmed' => ['confirm'],
    'when it is turned off' => ['disable'],
    'when its recovery codes are made again' => ['regenerate'],
]);

test('"Log out other browser sessions" whose account changed elsewhere meanwhile writes nothing, and its session ends', function () {
    $user = otherSessionsMember();
    $present = signInWithForm($user)['session'];
    browserWith($present)->get('/user/profile')->assertOk();

    // The other change keeps the password, so that the password of the request is still the right one
    $done = false;
    User::retrieved(function (User $model) use (&$done, $user): void {
        if (! $done && $model->getKey() === $user->getKey()) {
            $done = true;
            DB::table('users')->where('id', $user->id)->update(['session_version' => DB::raw('session_version + 1')]);
        }
    });
    $hash = $user->password;
    $response = browserWith($present)->delete('/user/other-browser-sessions', ['password' => 'password']);

    expect($user->fresh()?->password)->toBe($hash)
        ->and($user->fresh()?->session_version)->toBe(1);
    expectTheSessionEndedWithWords($response, $present, $user);
});

test('the action that ends the other sessions writes nothing for an account that changed since it was read', function () {
    $user = otherSessionsMember();
    $stale = User::query()->findOrFail($user->id);
    User::query()->findOrFail($user->id)->forceFill(['session_version' => 1])->save();
    $ran = false;

    expect(function () use ($stale, &$ran): void {
        app(EndsOtherSessions::class)->endFor($stale, ['password' => Hash::make(OTHER_SESSIONS_NEW_PASSWORD)], newRememberToken: true, write: function () use (&$ran): void {
            $ran = true;
        });
    })->toThrow(AccountChangedException::class);

    $fresh = $user->fresh();
    expect($ran)->toBeFalse()
        ->and(Hash::check('password', (string) $fresh?->password))->toBeTrue()
        ->and($fresh?->getRememberToken())->toBe($user->getRememberToken())
        ->and($fresh?->session_version)->toBe(1)
        ->and($stale->sessionVersion())->toBe(0);

    // The same for an account that was deleted meanwhile
    $gone = User::factory()->withUserProfile()->create();
    $held = User::query()->findOrFail($gone->id);
    DB::table('users')->where('id', $gone->id)->delete();
    expect(fn () => app(EndsOtherSessions::class)->endFor($held))->toThrow(AccountChangedException::class);
});

test('the action that ends the other sessions writes the version and the given values in one write', function () {
    $user = otherSessionsMember();
    $token = $user->getRememberToken();
    $hash = Hash::make(OTHER_SESSIONS_NEW_PASSWORD);
    $ran = 0;

    app(EndsOtherSessions::class)->endFor($user, ['password' => $hash], newRememberToken: true, write: function () use (&$ran, $user): void {
        $ran++;
        $user->forceFill(['name' => 'Written In The Same Transaction'])->save();
    });

    $fresh = $user->fresh();
    expect($ran)->toBe(1)
        ->and($fresh?->password)->toBe($hash)
        ->and($fresh?->name)->toBe('Written In The Same Transaction')
        ->and($fresh?->session_version)->toBe(1)
        ->and($fresh?->getRememberToken())->not->toBe($token)
        // The model holds what was written: its next write finds the row
        ->and($user->sessionVersion())->toBe(1)
        ->and($user->isDirty())->toBeFalse();
    $user->forceFill(['name' => 'Written After'])->save();
    expect($user->fresh()?->name)->toBe('Written After');
});

test('an empty or a wrong AUTH_PASSWORD_TIMEOUT keeps the 15 minutes, never the 3 hours of the framework', function (?string $value, int $seconds) {
    $before = [$_ENV['AUTH_PASSWORD_TIMEOUT'] ?? null, $_SERVER['AUTH_PASSWORD_TIMEOUT'] ?? null];
    if ($value === null) {
        unset($_ENV['AUTH_PASSWORD_TIMEOUT'], $_SERVER['AUTH_PASSWORD_TIMEOUT']);
    } else {
        $_ENV['AUTH_PASSWORD_TIMEOUT'] = $value;
        $_SERVER['AUTH_PASSWORD_TIMEOUT'] = $value;
    }

    try {
        expect((require config_path('auth.php'))['password_timeout'])->toBe($seconds);
    } finally {
        foreach (['_ENV' => $before[0], '_SERVER' => $before[1]] as $where => $stored) {
            if ($where === '_ENV') {
                if ($stored === null) {
                    unset($_ENV['AUTH_PASSWORD_TIMEOUT']);
                } else {
                    $_ENV['AUTH_PASSWORD_TIMEOUT'] = $stored;
                }
            } elseif ($stored === null) {
                unset($_SERVER['AUTH_PASSWORD_TIMEOUT']);
            } else {
                $_SERVER['AUTH_PASSWORD_TIMEOUT'] = $stored;
            }
        }
    }
})->with([
    'no value' => [null, 900],
    'an empty value' => ['', 900],
    'zero' => ['0', 900],
    'a negative number' => ['-60', 900],
    'a text' => ['soon', 900],
    'a number of seconds' => ['600', 600],
]);

// ---------------------------------------------------------------------------------------------------------------------
// Where the version is checked
// ---------------------------------------------------------------------------------------------------------------------

test('every route with a sign-in check ends a session of an earlier version before that check', function () {
    $checked = 0;
    $wrong = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
        $signIn = null;
        foreach ($middleware as $index => $entry) {
            if (is_a(explode(':', $entry, 2)[0], AuthenticatesRequests::class, true)) {
                $signIn = $index;
                break;
            }
        }
        if ($signIn === null) {
            continue;
        }

        $checked++;
        $ours = array_search(EndOutdatedSessions::class, $middleware, true);
        if (! is_int($ours) || $ours > $signIn) {
            $wrong[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }

    // The pages, the routes of Fortify and of Jetstream, the authorization of a live channel, and the API
    expect($wrong)->toBe([])
        ->and($checked)->toBeGreaterThan(200);
    foreach ([['GET', 'dashboard'], ['PUT', 'user/password'], ['GET', 'user/confirmed-password-status'], ['POST', 'broadcasting/auth'], ['GET', 'api/v1/user']] as [$method, $uri]) {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true));
        expect($route)->not->toBeNull()
            ->and(app('router')->gatherRouteMiddleware($route))->toContain(EndOutdatedSessions::class);
    }
});
