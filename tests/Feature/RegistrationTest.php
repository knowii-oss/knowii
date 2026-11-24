<?php

use App\Mail\FinishSignUp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip(function () {
    return Features::enabled(Features::registration());
}, 'Registration support is enabled.');

test('new users can register', function () {
    Mail::fake();

    // The form makes no account and signs nobody in: the mail has the next step (ADR 0065)
    $this->post('/register', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertRedirect(route('register', absolute: false));
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Mail::assertQueued(FinishSignUp::class, fn (FinishSignUp $mail) => $mail->hasTo('test@example.com'));

    // The link of the mail makes the account, verified, and signs the person in
    $response = $this->post('/register/link/'.signUpLinkToken('test@example.com'), [
        'name' => 'Test User',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect(User::query()->where('email', 'test@example.com')->sole()->hasVerifiedEmail())->toBeTrue();
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
