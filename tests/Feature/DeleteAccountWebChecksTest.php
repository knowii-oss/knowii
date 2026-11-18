<?php

use App\Events\Spaces\SpaceTreeChanged;
use App\Models\User;
use Illuminate\Support\Facades\Event;
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

test('the web deletion allows a few password tries a minute', function () {
    $user = User::factory()->withUserProfile()->create();

    foreach (range(1, 3) as $try) {
        $this->actingAs($user)->delete('/user', ['password' => 'wrong-'.$try])->assertSessionHasErrors(['password' => 'The password is incorrect.']);
    }

    // The fourth try is refused before the password is checked, also with the right one
    $this->actingAs($user)->delete('/user', ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'Too many tries. Please wait a minute, then try again.']);
    expect($user->fresh())->not->toBeNull();

    // A minute later the right password works
    $this->travel(61)->seconds();
    $this->actingAs($user)->delete('/user', ['password' => 'password']);
    expect($user->fresh())->toBeNull();
});
