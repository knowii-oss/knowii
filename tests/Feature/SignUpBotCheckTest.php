<?php

use App\Actions\Fortify\CreateNewUser;
use App\Jobs\SendSignUpMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const SIGNUP_TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

function signUp(array $extra = []): TestResponse
{
    return test()->post('/register', [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
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

    Queue::fake();
    signUp()->assertSessionHasNoErrors();
    Queue::assertPushed(SendSignUpMail::class, 1);
});

test('with Turnstile keys, a sign-up without a token is refused (#737)', function () {
    turnOnTurnstile();

    Queue::fake();
    signUp()->assertSessionHasErrors('turnstile_token');
    Queue::assertNothingPushed();
});

test('a token that Cloudflare refuses stops the sign-up (#737)', function () {
    turnOnTurnstile();
    Http::fake([SIGNUP_TURNSTILE_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    Queue::fake();
    signUp(['turnstile_token' => 'bad'])->assertSessionHasErrors('turnstile_token');
    Queue::assertNothingPushed();
});

test('a valid token lets the sign-up through (#737)', function () {
    turnOnTurnstile();
    Http::fake([SIGNUP_TURNSTILE_URL => Http::response(['success' => true])]);

    Queue::fake();
    signUp(['turnstile_token' => 'good'])->assertSessionHasNoErrors();

    Queue::assertPushed(SendSignUpMail::class, 1);
    Http::assertSent(fn ($request) => $request['secret'] === 'turnstile-secret' && $request['response'] === 'good');
});

test('a filled honeypot stops the sign-up (#737)', function () {
    Queue::fake();
    signUp(['website' => 'https://spam.example'])->assertSessionHasErrors(['email' => CreateNewUser::NOT_CREATED_MESSAGE]);

    Queue::assertNothingPushed();
    expect(User::query()->where('email', 'grace@example.com')->exists())->toBeFalse();
});
