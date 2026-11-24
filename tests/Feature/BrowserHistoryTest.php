<?php

use App\ApiRoutes;
use App\Mail\MagicLink;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * After the sign-out, the page data of a member cannot be read from the history of the browser, on https. A page tells the
 * browser to encrypt the pages that it keeps in its history (`INERTIA_ENCRYPT_HISTORY`, on by default) when the request
 * came over https or to a loopback name, and the first page after a sign-out, after the deletion of the account, after
 * "Log out other browser sessions", after the end of a session and after a takeover tells it to forget the key: the pages
 * of before cannot be read any more, and "Back" asks the server. The web application forgets the key by itself too
 * (apps/knowii/src/history-account.spec.ts).
 */

function browserHistoryMember(): User
{
    return User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com'])->fresh();
}

/**
 * The page object of an answer: what the browser gets with the page
 *
 * @return array<string, mixed>
 */
function browserHistoryPage(TestResponse $response): array
{
    $response->assertOk();

    /** @var array<string, mixed> */
    return $response->inertiaPage();
}

/**
 * The next page of the same browser tells it to forget the pages of before, once
 */
function expectHistoryIsClearedOnce(string $address): void
{
    app('auth')->forgetGuards();
    expect(browserHistoryPage(test()->get($address)))->toHaveKey('clearHistory', true);
    app('auth')->forgetGuards();
    expect(browserHistoryPage(test()->get($address)))->not->toHaveKey('clearHistory');
}

test('every page tells the browser to encrypt its history, for a member and for a visitor', function () {
    expect(config('inertia.history.encrypt'))->toBeTrue();

    $user = browserHistoryMember();
    foreach (['/dashboard', '/user/profile', '/community/'.$user->ownedCommunities()->firstOrFail()->slug] as $address) {
        expect(browserHistoryPage($this->actingAs($user)->get($address)))->toHaveKey('encryptHistory', true)->not->toHaveKey('clearHistory');
    }

    newBrowser();
    foreach (['/', '/login', '/register'] as $address) {
        expect(browserHistoryPage($this->get($address)))->toHaveKey('encryptHistory', true)->not->toHaveKey('clearHistory');
    }
});

test('an instance can turn the encryption of the history off, everywhere', function () {
    config(['inertia.history.encrypt' => false, 'app.url' => 'https://knowii.example.org']);

    expect(browserHistoryPage($this->actingAs(browserHistoryMember())->get('/dashboard')))->not->toHaveKey('encryptHistory');
    newBrowser();
    expect(browserHistoryPage($this->get('https://knowii.example.org/login')))->not->toHaveKey('encryptHistory');
});

test('the encryption of the history is on only where a browser can do it: over https, or on a loopback name', function (string $address, bool $encrypted) {
    config(['app.url' => 'http://knowii.example.org']);

    // A visitor, then a member: the same answer
    newBrowser();
    $pages = [browserHistoryPage($this->get($address.'/login'))];
    $pages[] = browserHistoryPage($this->actingAs(browserHistoryMember())->get($address.'/dashboard'));

    foreach ($pages as $page) {
        $encrypted ? expect($page)->toHaveKey('encryptHistory', true) : expect($page)->not->toHaveKey('encryptHistory');
    }
})->with([
    'https, the name of the instance' => ['https://knowii.example.org', true],
    'plain http, localhost' => ['http://localhost', true],
    'plain http, 127.0.0.1' => ['http://127.0.0.1', true],
    'plain http, [::1]' => ['http://[::1]', true],
    'plain http, the name of the instance' => ['http://knowii.example.org', false],
]);

test('on plain http the page after the sign-out still tells the browser to forget the pages of before', function () {
    config(['app.url' => 'http://knowii.example.org']);
    $user = browserHistoryMember();
    $this->actingAs($user)->get('http://knowii.example.org/dashboard')->assertOk();

    $this->post('http://knowii.example.org/logout')->assertRedirect();
    app('auth')->forgetGuards();

    expect(browserHistoryPage($this->get('http://knowii.example.org/login')))->toHaveKey('clearHistory', true)->not->toHaveKey('encryptHistory');
});

test('the page after the sign-out tells the browser to forget the pages of the member', function () {
    $user = browserHistoryMember();
    signInWithForm($user);
    app('auth')->forgetGuards();
    $this->get('/dashboard')->assertOk();

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest('web');

    expectHistoryIsClearedOnce('/');
});

test('the page after the deletion of the account tells the browser to forget the pages of the member', function (string $way) {
    $user = browserHistoryMember();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    if ($way === 'page') {
        $this->delete('/user', ['password' => 'password'])->assertSessionHasNoErrors();
    } else {
        // The API with the session of the web app
        $this->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])->deleteJson(ApiRoutes::path(ApiRoutes::USER), ['password' => 'password'])->assertNoContent();
        $this->flushHeaders();
    }
    expect($user->fresh())->toBeNull();

    expectHistoryIsClearedOnce('/');
})->with([
    'on the page' => ['page'],
    'on the API, with the session' => ['api'],
]);

test('the page after "Log out other browser sessions" tells the browser to forget the pages of before', function () {
    $user = browserHistoryMember();
    signInWithForm($user);
    app('auth')->forgetGuards();
    $this->get('/user/profile')->assertOk();

    app('auth')->forgetGuards();
    $this->delete('/user/other-browser-sessions', ['password' => 'password'])->assertSessionHasNoErrors()->assertStatus(303);

    expectHistoryIsClearedOnce('/user/profile');
});

test('a wrong password on "Log out other browser sessions" changes nothing for the history', function () {
    $user = browserHistoryMember();
    $this->actingAs($user)->delete('/user/other-browser-sessions', ['password' => 'not-the-password'])->assertSessionHasErrors(['password']);

    expect(browserHistoryPage($this->get('/user/profile')))->not->toHaveKey('clearHistory');
});

test('the sign-in page after the end of a session tells the browser to forget the pages of the member', function () {
    $user = browserHistoryMember();
    $other = signInWithForm($user)['session'];
    browserWith($other)->get('/dashboard')->assertOk();

    // The password changes in another browser: the session of this browser ends at its next request
    $present = signInWithForm($user)['session'];
    $changed = sessionCookieOf(browserWith($present)->put('/user/password', ['current_password' => 'password', 'password' => 'A-new-password-of-2026!', 'password_confirmation' => 'A-new-password-of-2026!'])->assertSessionHasNoErrors());

    $ended = browserWith($other)->get('/dashboard');
    $ended->assertRedirect(route('login'));
    $name = config()->string('session.cookie');
    $next = [$name => (string) $ended->getCookie($name)?->getValue()];

    expect(browserHistoryPage(browserWith($next)->get('/login')))->toHaveKey('clearHistory', true);
    // The session that changed the password keeps its pages
    expect(browserHistoryPage(browserWith($changed)->get('/dashboard')))->not->toHaveKey('clearHistory');
});

test('the first page after a takeover of the account tells the browser to forget the pages of before', function (string $way) {
    // An account whose address was never verified: the first proof of the address takes it over (ADR 0059)
    $user = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);

    newBrowser();
    if ($way === 'link') {
        Mail::fake();
        $this->post('/login/link', ['email' => $user->email])->assertRedirect(route('login'));
        $token = '';
        Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });
        $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        expectHistoryIsClearedOnce('/dashboard');
    } else {
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $token = '';
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-new-password-of-2026!', 'password_confirmation' => 'A-new-password-of-2026!'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        expectHistoryIsClearedOnce('/login');
    }

    expect($user->fresh()?->session_version)->toBe(1);
})->with([
    'with a sign-in link' => ['link'],
    'with a password reset' => ['reset'],
]);

test('a sign-in link of a verified account takes nothing over, and changes nothing for the history', function () {
    $user = browserHistoryMember();

    newBrowser();
    Mail::fake();
    $this->post('/login/link', ['email' => $user->email])->assertRedirect(route('login'));
    $token = '';
    Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));

    app('auth')->forgetGuards();
    expect(browserHistoryPage($this->get('/dashboard')))->toHaveKey('encryptHistory', true)->not->toHaveKey('clearHistory');
});
