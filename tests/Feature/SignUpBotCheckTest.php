<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const SIGNUP_TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

function signUp(array $extra = []): TestResponse
{
    return test()->post('/register', [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'A-strong-password-2026!',
        'password_confirmation' => 'A-strong-password-2026!',
        'terms' => true,
        ...$extra,
    ]);
}

function turnOnTurnstile(): void
{
    config([
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'turnstile-secret',
        'services.turnstile.verify_url' => SIGNUP_TURNSTILE_URL,
    ]);
    Http::preventStrayRequests();
}

test('with Turnstile keys, the sign-up page shows the widget (#737)', function () {
    turnOnTurnstile();

    $this->get('/register')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Register')->where('turnstileSiteKey', 'site-key'));
});

test('without Turnstile keys, the sign-up has no bot check (#737)', function () {
    $this->get('/register')->assertInertia(fn ($page) => $page->where('turnstileSiteKey', null));

    signUp()->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeTrue();
});

test('with Turnstile keys, a sign-up without a token is refused (#737)', function () {
    turnOnTurnstile();

    signUp()->assertSessionHasErrors('turnstile_token');
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});

test('a token that Cloudflare refuses stops the sign-up (#737)', function () {
    turnOnTurnstile();
    Http::fake([SIGNUP_TURNSTILE_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    signUp(['turnstile_token' => 'bad'])->assertSessionHasErrors('turnstile_token');
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});

test('a valid token lets the sign-up through (#737)', function () {
    turnOnTurnstile();
    Http::fake([SIGNUP_TURNSTILE_URL => Http::response(['success' => true])]);

    signUp(['turnstile_token' => 'good'])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeTrue();
    Http::assertSent(fn ($request) => $request['secret'] === 'turnstile-secret' && $request['response'] === 'good');
});

test('a filled honeypot stops the sign-up (#737)', function () {
    signUp(['website' => 'https://spam.example'])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});
