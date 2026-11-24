<?php

use App\ApiRoutes;
use App\Mail\SecondFactorLocked;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\EndedSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

/*
 * The tries of the code of two-factor authentication count per account, on every path that checks a code: the challenge of
 * the web sign-in, the API sign-in, the deletion of the account and the transfer of a community. 5 wrong codes a minute
 * and 10 wrong codes an hour; only a wrong code counts. A challenge of the web sign-in ends after 10 minutes.
 */

/**
 * An account with confirmed two-factor authentication and two recovery codes
 *
 * @return array{0: User, 1: string}
 */
function triesTwoFactorUser(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user->fresh(), $secret];
}

/**
 * The API sign-in from a new client at this IP address
 *
 * @param  array<string, mixed>  $body
 */
function triesApiLogin(string $ip, array $body): TestResponse
{
    newBrowser();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), $body);
}

/**
 * A new browser at this IP address signs in with the password, and lands on the challenge
 */
function triesStartChallenge(User $user, string $ip): void
{
    newBrowser();
    test()->withServerVariables(['REMOTE_ADDR' => $ip])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
}

/**
 * A code on the challenge of the browser, from this IP address
 *
 * @param  array<string, string>  $body
 */
function triesChallenge(string $ip, array $body): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->from('/two-factor-challenge')->post('/two-factor-challenge', $body);
}

function triesOtp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));
});

test('the API sign-in refuses the 6th wrong code of a minute and the 11th of an hour, from any IP address', function () {
    [$user, $secret] = triesTwoFactorUser();
    $wrong = ['email' => $user->email, 'password' => 'password', 'code' => '000000'];

    // Minute 1: 5 wrong codes from 5 addresses, then the 6th from a 6th address is refused
    foreach (range(1, 5) as $i) {
        triesApiLogin("10.0.0.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED)->assertJsonPath('metadata.two_factor_required', true);
    }
    triesApiLogin('10.0.0.6', $wrong)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    // Minute 2: 5 more wrong codes: 10 in the hour
    $this->travel(61)->seconds();
    foreach (range(7, 11) as $i) {
        triesApiLogin("10.0.0.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    // Minute 3: the hour has no try left, also for a right code, and the answer says how long to wait
    $this->travel(61)->seconds();
    $refused = triesApiLogin('10.0.0.12', ['email' => $user->email, 'password' => 'password', 'code' => triesOtp($secret)]);
    $refused->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    expect((int) $refused->headers->get('Retry-After'))->toBeGreaterThan(60);
    $this->assertGuest();

    // After the hour of the first wrong code, a right code passes
    $this->travel(59)->minutes();
    triesApiLogin('10.0.0.13', ['email' => $user->email, 'password' => 'password', 'code' => triesOtp($secret)])->assertOk();
});

test('the web challenge refuses the 6th wrong code of a minute and the 11th of an hour, from any IP address', function () {
    [$user, $secret] = triesTwoFactorUser();

    // Minute 1: 5 wrong codes from 5 addresses, then the 6th is refused
    triesStartChallenge($user, '10.0.1.1');
    foreach (range(1, 5) as $i) {
        triesChallenge("10.0.1.$i", ['code' => '000000'])->assertRedirect('/two-factor-challenge')->assertSessionHasErrors('code');
    }
    triesChallenge('10.0.1.6', ['code' => '000000'])
        ->assertRedirect('/two-factor-challenge')
        ->assertSessionHasErrors(['code' => 'Too many tries. Please wait a minute, then try again.']);

    // Minute 2: 5 more wrong codes, in a new challenge from another address
    $this->travel(61)->seconds();
    triesStartChallenge($user, '10.0.2.1');
    foreach (range(7, 11) as $i) {
        triesChallenge("10.0.2.$i", ['code' => '000000'])->assertRedirect('/two-factor-challenge')->assertSessionHasErrors('code');
    }

    // Minute 3: the hour has no try left, also for a right code
    $this->travel(61)->seconds();
    triesChallenge('10.0.2.12', ['code' => triesOtp($secret)])
        ->assertRedirect('/two-factor-challenge')
        ->assertSessionHasErrors(['code' => 'Too many tries. Please wait 58 minutes, then try again.']);
    $this->assertGuest();

    // After the hour, a new sign-in passes with a right code
    $this->travel(59)->minutes();
    triesStartChallenge($user, '10.0.2.13');
    triesChallenge('10.0.2.13', ['code' => triesOtp($secret)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('a wrong password never counts as a wrong code', function () {
    [$user, $secret] = triesTwoFactorUser();

    foreach (range(1, 12) as $i) {
        triesApiLogin("10.0.3.$i", ['email' => $user->email, 'password' => 'wrong-password', 'code' => '000000'])
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonMissingPath('metadata.two_factor_required');
    }

    // The 5 wrong codes of the minute are all there, and a right code passes after them
    foreach (range(1, 5) as $i) {
        triesApiLogin("10.0.4.$i", ['email' => $user->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    $this->travel(61)->seconds();
    triesApiLogin('10.0.4.6', ['email' => $user->email, 'password' => 'password', 'code' => triesOtp($secret)])->assertOk();
});

test('the wrong codes of one account change nothing for another account', function () {
    [$ada] = triesTwoFactorUser();
    [$bob, $bobSecret] = triesTwoFactorUser();

    foreach (range(1, 5) as $i) {
        triesApiLogin("10.0.5.$i", ['email' => $ada->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    $this->travel(61)->seconds();
    foreach (range(6, 10) as $i) {
        triesApiLogin("10.0.5.$i", ['email' => $ada->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    triesApiLogin('10.0.5.11', ['email' => $ada->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    triesApiLogin('10.0.5.11', ['email' => $bob->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    triesApiLogin('10.0.5.12', ['email' => $bob->email, 'password' => 'password', 'code' => triesOtp($bobSecret)])->assertOk();
});

test('a right code gives back no wrong code of the hour', function () {
    [$user, $secret] = triesTwoFactorUser();
    $wrong = ['email' => $user->email, 'password' => 'password', 'code' => '000000'];

    foreach (range(1, 5) as $i) {
        triesApiLogin("10.0.6.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    $this->travel(61)->seconds();
    foreach (range(6, 9) as $i) {
        triesApiLogin("10.0.6.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    // A right code: the sign-in passes
    triesApiLogin('10.0.6.10', ['email' => $user->email, 'password' => 'password', 'recovery_code' => 'recovery-one'])->assertOk();

    // The 10th wrong code of the hour, then nothing more
    triesApiLogin('10.0.6.11', $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
    $this->travel(61)->seconds();
    triesApiLogin('10.0.6.12', ['email' => $user->email, 'password' => 'password', 'code' => triesOtp($secret)])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('the wrong codes of the web challenge, the API sign-in and the deletion count together', function () {
    [$user, $secret] = triesTwoFactorUser();

    foreach (range(1, 3) as $i) {
        triesApiLogin("10.0.7.$i", ['email' => $user->email, 'password' => 'password', 'code' => '000000'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
    triesStartChallenge($user, '10.0.7.4');
    triesChallenge('10.0.7.4', ['code' => '000000'])->assertSessionHasErrors('code');
    expect(fn () => AccountDeletion::checkSecondFactor($user, '000000'))->toThrow(ValidationException::class, 'The provided two factor authentication code was invalid.');

    // The minute has no try left on any path
    triesApiLogin('10.0.7.5', ['email' => $user->email, 'password' => 'password', 'code' => triesOtp($secret)])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    expect(fn () => AccountDeletion::checkSecondFactor($user, 'recovery-one'))->toThrow(ValidationException::class, 'Too many tries. Please wait a minute, then try again.');
});

test('a challenge of the web sign-in ends after 10 minutes: the person signs in again', function () {
    [$user, $secret] = triesTwoFactorUser();

    triesStartChallenge($user, '10.0.8.1');
    $this->travel(9)->minutes();
    $this->get('/two-factor-challenge')->assertOk();

    $this->travel(61)->seconds();
    triesChallenge('10.0.8.1', ['code' => triesOtp($secret)])
        ->assertRedirect(route('login'))
        ->assertSessionHas(EndedSession::WARNING_KEY, 'The sign-in took too long. Sign in again.');
    $this->assertGuest();
    expect(RateLimiter::attempts('second-factor-tries:minute:'.$user->cuid))->toBe(0);
    app('auth')->forgetGuards();
    $this->get('/two-factor-challenge')->assertRedirect(route('login'));

    // A new sign-in opens a new challenge
    triesStartChallenge($user, '10.0.8.1');
    triesChallenge('10.0.8.1', ['code' => triesOtp($secret)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('the web challenge has no other limit than the tries of the account: a request without a code is a form error, and locks nothing', function () {
    [$user, $secret] = triesTwoFactorUser();

    // Another browser with the password: 6 posts without a code, each a form error, and no try counted
    triesStartChallenge($user, '10.0.9.1');
    foreach (range(1, 6) as $i) {
        triesChallenge('10.0.9.1', [])
            ->assertRedirect('/two-factor-challenge')
            ->assertSessionHasErrors(['code' => 'Enter the code of your authenticator app or a recovery code.']);
    }
    expect(RateLimiter::attempts('second-factor-tries:minute:'.$user->cuid))->toBe(0)
        ->and(RateLimiter::attempts('second-factor-tries:hour:'.$user->cuid))->toBe(0);

    // The member, in the same minute: the right code passes
    triesStartChallenge($user, '192.0.2.90');
    triesChallenge('192.0.2.90', ['code' => triesOtp($secret)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('six right sign-ins of one account through the web challenge in one minute all pass', function () {
    [$user] = triesTwoFactorUser();
    $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(['r1', 'r2', 'r3', 'r4', 'r5', 'r6']))])->save();

    foreach (range(1, 6) as $i) {
        triesStartChallenge($user, "192.0.2.9$i");
        triesChallenge("192.0.2.9$i", ['recovery_code' => "r$i"])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
});

test('the 10th wrong code of an hour queues one mail to the member; the later ones in the same hour queue none; the next lock queues one again', function () {
    Mail::fake();
    [$user] = triesTwoFactorUser();
    $wrong = ['email' => $user->email, 'password' => 'password', 'code' => '000000'];
    $tenWrongCodes = function (string $net, int $mailsBefore) use ($wrong): void {
        foreach (range(1, 5) as $i) {
            triesApiLogin("$net.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
        }
        test()->travel(61)->seconds();
        foreach (range(6, 9) as $i) {
            triesApiLogin("$net.$i", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
        }
        Mail::assertQueued(SecondFactorLocked::class, $mailsBefore);
        triesApiLogin("$net.10", $wrong)->assertStatus(Response::HTTP_UNAUTHORIZED);
    };

    // The hour began at 10:00:00: the 10th wrong code at 10:01:01 closes the second step until 11:00
    $tenWrongCodes('10.0.10', 0);
    Mail::assertQueued(SecondFactorLocked::class, 1);
    Mail::assertQueued(SecondFactorLocked::class, fn (SecondFactorLocked $mail): bool => $mail->hasTo($user->email)
        && $mail->until->equalTo(Carbon::parse('2026-11-24 11:00:00')) && $mail->timeZone === 'UTC');

    // The 11th and later in the same hour, on every path: no more mail
    $this->travel(2)->minutes();
    triesApiLogin('10.0.10.11', $wrong)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    triesStartChallenge($user, '10.0.10.12');
    triesChallenge('10.0.10.12', ['code' => '000000'])->assertSessionHasErrors('code');
    expect(fn () => AccountDeletion::checkSecondFactor($user, '000000'))->toThrow(ValidationException::class);
    $this->travel(30)->minutes();
    triesApiLogin('10.0.10.13', $wrong)->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    Mail::assertQueued(SecondFactorLocked::class, 1);

    // A new hour, and a new lock: one more mail
    $this->travelTo(Carbon::parse('2026-11-24 11:00:05'));
    $tenWrongCodes('10.0.11', 1);
    Mail::assertQueued(SecondFactorLocked::class, 2);
});

test('the mail of a lock says until when, and links to the password reset; it holds no IP address', function () {
    [$user] = triesTwoFactorUser();
    $mail = (new SecondFactorLocked(Carbon::parse('2026-11-24 11:00:00')->toImmutable(), 'UTC'))->to($user->email);

    $html = $mail->render();
    expect($html)->toContain('The right password of your account on Knowii (or a sign-in link sent to this address) was used, then wrong codes of two-factor authentication were entered 10 times.')
        ->toContain('2026-11-24 11:00 (UTC)')
        ->toContain(route('password.request'))
        ->not->toContain('10.0.')
        ->and($mail->build()->subject)->toBe('Wrong two-factor codes on your account on Knowii');
});

test('the mail of a lock shows the time in the time zone of the member', function () {
    Mail::fake();
    [$user] = triesTwoFactorUser();
    $user->forceFill(['time_zone' => 'Europe/Brussels'])->save();

    foreach (range(1, 5) as $i) {
        triesApiLogin("10.0.12.$i", ['email' => $user->email, 'password' => 'password', 'code' => '000000']);
    }
    $this->travel(61)->seconds();
    foreach (range(6, 10) as $i) {
        triesApiLogin("10.0.12.$i", ['email' => $user->email, 'password' => 'password', 'code' => '000000']);
    }

    Mail::assertQueued(SecondFactorLocked::class, fn (SecondFactorLocked $mail): bool => $mail->timeZone === 'Europe/Brussels'
        && str_contains($mail->render(), '2026-11-24 12:00 (Europe/Brussels)'));
});
