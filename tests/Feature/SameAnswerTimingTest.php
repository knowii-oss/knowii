<?php

use App\ApiRoutes;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * The forms that give the same answer for every address take the same time for an address with and without an account
 * (documentation/architecture/backend.md). These measures are slow and depend on the machine: they run only with the
 * environment variable RUN_TIMING_TESTS=1, never in the full suite. Each row times pairs of requests, interleaved, in a
 * random order inside each pair, with a new account and a new IP address for each request, the database queue, and the
 * hash cost of production. Both cases of a row make the same accounts before the request, used or not.
 */

const TIMING_PAIRS = 60;

const TIMING_WARM_UP = 5;

const TIMING_PASSWORD = 'The-password-of-2026!';

/**
 * The hashing of production: bcrypt with cost 12, for the accounts and for the checks of the requests
 */
function timingProductionHashing(): void
{
    config(['hashing.bcrypt.rounds' => 12]);
    app()->forgetInstance('hash');
    app()->forgetInstance('auth.password');
    app()->forgetInstance('auth.password.broker');
    Hash::clearResolvedInstances();
    Password::clearResolvedInstances();
    app('auth')->forgetGuards();
}

/**
 * The median, the minimum and the 90th percentile of the times, in milliseconds
 *
 * @param  list<float>  $times
 * @return array{median: float, min: float, p90: float}
 */
function timingStats(array $times): array
{
    sort($times);
    $count = count($times);
    $median = $count % 2 === 1 ? $times[intdiv($count, 2)] : ($times[$count / 2 - 1] + $times[$count / 2]) / 2;

    return ['median' => $median, 'min' => $times[0], 'p90' => $times[(int) floor(0.9 * ($count - 1))]];
}

/**
 * A new account for one request: a fresh address and the given password hash (null: no password)
 */
function timingAccount(?string $passwordHash, array $attributes = []): User
{
    return User::factory()->withUserProfile()->create(['email' => 'acct-'.Str::lower(Str::random(12)).'@example.com', 'password' => $passwordHash, ...$attributes]);
}

/**
 * Times the pairs of a row: each case gives a closure that prepares a request (outside the time) and returns the request
 *
 * @param  array<string, Closure(int): Closure(): TestResponse>  $cases
 * @return array<string, list<float>>
 */
function timingRun(array $cases): array
{
    $names = array_keys($cases);
    $times = array_fill_keys($names, []);
    $ip = 0;
    for ($i = -TIMING_WARM_UP; $i < TIMING_PAIRS; $i++) {
        $order = $names;
        shuffle($order);
        foreach ($order as $name) {
            $ip++;
            test()->withServerVariables(['REMOTE_ADDR' => sprintf('10.%d.%d.%d', intdiv($ip, 65536) % 256, intdiv($ip, 256) % 256, $ip % 256 + 1)]);
            app('auth')->forgetGuards();
            test()->flushSession();
            $request = $cases[$name]($i);

            $started = hrtime(true);
            $request();
            $elapsed = (hrtime(true) - $started) / 1_000_000;

            if ($i >= 0) {
                $times[$name][] = $elapsed;
            }
        }
    }

    return $times;
}

/**
 * Prints the numbers of a row and checks them: the medians differ by at most the larger of 1.5 ms and 5 % of the larger
 * median, and the sets overlap (the minimum of each one is at most the 90th percentile of the other)
 *
 * @param  array<string, list<float>>  $times
 */
function timingCheck(string $row, array $times): void
{
    [$a, $b] = array_keys($times);
    $sa = timingStats($times[$a]);
    $sb = timingStats($times[$b]);
    fwrite(STDERR, sprintf(
        "\nTIMING %s | %s: median %.2f min %.2f p90 %.2f | %s: median %.2f min %.2f p90 %.2f | n=%d\n",
        $row, $a, $sa['median'], $sa['min'], $sa['p90'], $b, $sb['median'], $sb['min'], $sb['p90'], count($times[$a]),
    ));

    $allowed = max(1.5, 0.05 * max($sa['median'], $sb['median']));
    expect(abs($sa['median'] - $sb['median']))->toBeLessThanOrEqual($allowed)
        ->and($sa['min'])->toBeLessThanOrEqual($sb['p90'])
        ->and($sb['min'])->toBeLessThanOrEqual($sa['p90']);
}

test('the forms take the same time for an address with and without an account', function (string $row) {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    timingProductionHashing();
    // One hash of cost 12 for all the accounts: a hash of each would make the run very long
    $hash = Hash::make(TIMING_PASSWORD);

    $times = match ($row) {
        'the sign-up form' => timingRun([
            'an account' => function () use ($hash) {
                $address = timingAccount($hash)->email;

                return fn () => test()->post('/register', ['name' => 'Grace Hopper', 'email' => $address])->assertRedirect(route('register'));
            },
            'no account' => function () use ($hash) {
                // An account that the request does not use: both cases are prepared the same way
                timingAccount($hash);
                $address = 'none-'.Str::lower(Str::random(12)).'@example.com';

                return fn () => test()->post('/register', ['name' => 'Grace Hopper', 'email' => $address])->assertRedirect(route('register'));
            },
        ]),
        'the sign-in link form' => timingRun([
            'an account' => function () use ($hash) {
                $address = timingAccount($hash)->email;

                return fn () => test()->post('/login/link', ['email' => $address])->assertRedirect(route('login'));
            },
            'no account' => function () use ($hash) {
                // An account that the request does not use: both cases are prepared the same way
                timingAccount($hash);
                $address = 'none-'.Str::lower(Str::random(12)).'@example.com';

                return fn () => test()->post('/login/link', ['email' => $address])->assertRedirect(route('login'));
            },
        ]),
        'the web sign-in, no account', 'the web sign-in, no password', 'the API sign-in, no account', 'the API sign-in, no password' => timingRun([
            'a wrong password' => function () use ($hash, $row) {
                $address = timingAccount($hash)->email;

                return fn () => timingSignIn($row, $address);
            },
            str_ends_with($row, 'no account') ? 'no account' : 'no password' => function () use ($row, $hash) {
                // One account made in both cases: the one of the request, or one that the request does not use
                $account = str_ends_with($row, 'no account') ? timingAccount($hash) : timingAccount(null);
                $address = str_ends_with($row, 'no account') ? 'none-'.Str::lower(Str::random(12)).'@example.com' : $account->email;

                return fn () => timingSignIn($row, $address);
            },
        ]),
        'the reset page' => timingRun([
            'an account with a link' => function () use ($hash) {
                $account = timingAccount($hash);
                Password::broker()->createToken($account);

                return fn () => test()->postJson('/reset-password', ['token' => Str::random(64), 'email' => $account->email, 'password' => TIMING_PASSWORD, 'password_confirmation' => TIMING_PASSWORD])->assertStatus(400);
            },
            'no account' => function () use ($hash) {
                // An account and a link that the request does not use: both cases are prepared the same way
                Password::broker()->createToken(timingAccount($hash));
                $address = 'none-'.Str::lower(Str::random(12)).'@example.com';

                return fn () => test()->postJson('/reset-password', ['token' => Str::random(64), 'email' => $address, 'password' => TIMING_PASSWORD, 'password_confirmation' => TIMING_PASSWORD])->assertStatus(400);
            },
        ]),
        'the profile form' => timingRun([
            'a free address' => function () use ($hash) {
                $account = timingAccount($hash);
                // An account that the request does not use: both cases make two accounts
                timingAccount($hash);
                $address = 'free-'.Str::lower(Str::random(12)).'@example.com';

                return fn () => test()->actingAs($account)->put('/user/profile-information', ['name' => $account->name, 'email' => $address, 'current_password' => TIMING_PASSWORD])->assertSessionHasNoErrors();
            },
            'a taken address' => function () use ($hash) {
                $account = timingAccount($hash);
                $address = timingAccount($hash)->email;

                return fn () => test()->actingAs($account)->put('/user/profile-information', ['name' => $account->name, 'email' => $address, 'current_password' => TIMING_PASSWORD])->assertSessionHasNoErrors();
            },
        ]),
    };

    timingCheck($row, $times);
})->with([
    'the sign-up form',
    'the sign-in link form',
    'the web sign-in, no account',
    'the web sign-in, no password',
    'the API sign-in, no account',
    'the API sign-in, no password',
    'the reset page',
    'the profile form',
])->skip(fn () => getenv('RUN_TIMING_TESTS') !== '1', 'Timing measures: set RUN_TIMING_TESTS=1');

/**
 * A sign-in with a wrong password, on the web or in the API: the answer of a failed sign-in
 */
function timingSignIn(string $row, string $address): TestResponse
{
    return str_starts_with($row, 'the web')
        ? test()->post('/login', ['email' => $address, 'password' => 'not-the-password'])->assertSessionHasErrors('email')
        : test()->postJson(ApiRoutes::path(ApiRoutes::AUTH_LOGIN), ['email' => $address, 'password' => 'not-the-password'])->assertStatus(401);
}
