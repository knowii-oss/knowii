<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

function twoFactorUser(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

test('the sign-in of an account with two-factor authentication shows the challenge page (#690)', function () {
    [$user] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->get('/two-factor-challenge')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));
});

test('the code of the authenticator app completes the sign-in (#690)', function () {
    [$user, $secret] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('a recovery code completes the sign-in once (#690)', function () {
    [$user] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->recoveryCodes())->not->toContain('recovery-one');
});

test('a wrong code keeps the member out (#690)', function () {
    [$user] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the profile page offers two-factor authentication with confirmation (#690)', function () {
    // fresh(): actingAs() keeps the in-memory model, without the columns that have a database default
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create()->fresh();

    $this->actingAs($user)->get('/user/profile')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Profile/Show')
            ->where('confirmsTwoFactorAuthentication', true)
            ->where('jetstream.canManageTwoFactorAuthentication', true));
});
