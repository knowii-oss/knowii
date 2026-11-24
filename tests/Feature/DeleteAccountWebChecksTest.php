<?php

use App\Constants;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\User;
use App\Support\AccountDeletion;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

/*
 * The web deletion of an account with a password asks for the same proof as the API: the password, with a few tries a
 * minute, and the code of two-factor authentication when it is on.
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
});

/**
 * @return string The secret of the authenticator app
 */
function webChecksTurnOnTwoFactor(User $user): string
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

test('with two-factor authentication, the web deletion asks for the code after the password', function () {
    $user = User::factory()->withUserProfile()->create();
    webChecksTurnOnTwoFactor($user);

    $this->actingAs($user)->delete('/user', ['password' => 'password'])
        ->assertSessionHasErrors(['code' => 'Enter the code of your authenticator app or a recovery code.']);
    expect($user->fresh())->not->toBeNull();

    $this->actingAs($user)->delete('/user', ['password' => 'password', 'code' => '000000'])
        ->assertSessionHasErrors(['code' => 'The provided two factor authentication code was invalid.']);
    expect($user->fresh())->not->toBeNull();
});

test('with two-factor authentication, the password and a valid code delete the account', function () {
    $user = User::factory()->withUserProfile()->create();
    $secret = webChecksTurnOnTwoFactor($user);

    $this->actingAs($user)->delete('/user', ['password' => 'password', 'code' => app(Google2FA::class)->getCurrentOtp($secret)]);

    expect($user->fresh())->toBeNull();
});

test('a recovery code works and is used up', function () {
    $user = User::factory()->withUserProfile()->create();
    webChecksTurnOnTwoFactor($user);

    $this->actingAs($user)->delete('/user', ['password' => 'password', 'code' => 'recovery-one']);

    expect($user->fresh())->toBeNull();
});

test('a wrong password never reaches the code, and the code is not asked without two-factor authentication', function () {
    $user = User::factory()->withUserProfile()->create();
    webChecksTurnOnTwoFactor($user);

    $this->actingAs($user)->delete('/user', ['password' => 'wrong', 'code' => 'recovery-one'])
        ->assertSessionHasErrors(['password' => 'The password is incorrect.'])
        ->assertSessionDoesntHaveErrors('code');
    expect($user->fresh())->not->toBeNull();

    $plain = User::factory()->withUserProfile()->create();
    $this->actingAs($plain)->delete('/user', ['password' => 'password']);
    expect($plain->fresh())->toBeNull();
});

test('the fifth wrong password of the web deletion in 15 minutes ends the session', function () {
    $user = User::factory()->withUserProfile()->create();
    $cookies = signInWithForm($user)['session'];

    foreach (range(1, 4) as $try) {
        browserWith($cookies)->delete('/user', ['password' => 'wrong-'.$try])->assertSessionHasErrors(['password' => 'The password is incorrect.']);
    }

    // The fifth one ends the session (App\Support\PasswordTries): the sign-in page, also with the right password after it
    browserWith($cookies)->delete('/user', ['password' => 'wrong-5'])->assertRedirect(route('login'));
    browserWith($cookies)->delete('/user', ['password' => 'password'])->assertRedirect(route('login'));
    expect($user->fresh())->not->toBeNull();

    // After a new sign-in the right password works
    browserWith(signInWithForm($user)['session'])->delete('/user', ['password' => 'password']);
    expect($user->fresh())->toBeNull();
});

test('wrong codes of the deletion that are sent at the same time give at most 3 checks of a code a minute', function (int $counted) {
    $user = User::factory()->withUserProfile()->create();
    webChecksTurnOnTwoFactor($user);
    $user = $user->fresh();
    expect(Constants::ACCOUNT_DELETIONS_PER_MINUTE)->toBe(3);

    // The wrong codes that the minute already counted
    foreach (range(1, $counted) as $try) {
        if ($counted > 0) {
            expect(fn () => AccountDeletion::checkSecondFactor($user, '00000'.$try))->toThrow(ValidationException::class, 'The provided two factor authentication code was invalid.');
        }
    }

    // 8 wrong codes: each one starts while the check of the one before still runs
    $real = app(TwoFactorAuthenticationProvider::class);
    $checks = 0;
    $started = 1;
    $answers = [];
    app()->instance(TwoFactorAuthenticationProvider::class, new class($real, $checks, $started, $answers, $user) implements TwoFactorAuthenticationProvider
    {
        /**
         * @param  array<int, string>  $answers
         */
        public function __construct(private readonly TwoFactorAuthenticationProvider $real, private int &$checks, private int &$started, private array &$answers, private readonly User $user) {}

        public function generateSecretKey()
        {
            return $this->real->generateSecretKey();
        }

        public function qrCodeUrl($companyName, $companyEmail, $secret)
        {
            return $this->real->qrCodeUrl($companyName, $companyEmail, $secret);
        }

        public function verify($secret, $code)
        {
            $this->checks++;
            if ($this->started < 8) {
                $this->started++;
                try {
                    AccountDeletion::checkSecondFactor($this->user, '999999');
                } catch (ValidationException $e) {
                    $this->answers[] = $e->getMessage();
                }
            }

            return false;
        }
    });

    try {
        AccountDeletion::checkSecondFactor($user, '999999');
    } catch (ValidationException $e) {
        $answers[] = $e->getMessage();
    }

    // A check for each place that the minute has left, and the next code is refused before its check
    $left = Constants::ACCOUNT_DELETIONS_PER_MINUTE - $counted;
    expect($checks)->toBe($left)
        ->and($started)->toBe($left + 1)
        ->and(array_count_values($answers))->toBe([
            'Too many tries. Please wait a minute, then try again.' => 1,
            'The provided two factor authentication code was invalid.' => $left,
        ]);

    // A right code is refused too, until the minute is over: then it passes
    expect(fn () => AccountDeletion::checkSecondFactor($user, 'recovery-one'))->toThrow(ValidationException::class, 'Too many tries. Please wait a minute, then try again.');
    $this->travel(61)->seconds();
    AccountDeletion::checkSecondFactor($user, 'recovery-one');
})->with([
    'no wrong code before' => [0],
    'one wrong code before' => [1],
    'two wrong codes before' => [2],
]);

test('a right code of the deletion uses no try, and a request without a code neither', function () {
    $user = User::factory()->withUserProfile()->create();
    $secret = webChecksTurnOnTwoFactor($user);
    $user = $user->fresh();

    // A right code, then more requests without a code than the limit of a minute
    AccountDeletion::checkSecondFactor($user, app(Google2FA::class)->getCurrentOtp($secret));
    foreach ([null, '', null, '', null] as $none) {
        expect(fn () => AccountDeletion::checkSecondFactor($user, $none))->toThrow(ValidationException::class, 'Enter the code of your authenticator app or a recovery code.');
    }

    // The three tries of the minute are all there
    foreach (range(1, 3) as $try) {
        expect(fn () => AccountDeletion::checkSecondFactor($user, '00000'.$try))->toThrow(ValidationException::class, 'The provided two factor authentication code was invalid.');
    }
    expect(fn () => AccountDeletion::checkSecondFactor($user, '000004'))->toThrow(ValidationException::class, 'Too many tries. Please wait a minute, then try again.');
});
