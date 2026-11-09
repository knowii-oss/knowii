<?php

use App\ApiRoutes;
use App\Constants;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const EMAILOCTOPUS_CONTACTS_URL = 'https://api.emailoctopus.com/lists/list-123/contacts';

beforeEach(function () {
    config([
        'services.emailoctopus.base_url' => 'https://api.emailoctopus.com',
        'services.emailoctopus.api_key' => 'eo-test-key',
        'services.emailoctopus.waitlist_list_id' => 'list-123',
        'services.emailoctopus.waitlist_contact_status' => 'pending',
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'turnstile-secret',
        'services.turnstile.verify_url' => TURNSTILE_URL,
    ]);

    RateLimiter::clear('waitlist');
    Http::preventStrayRequests();
});

function joinWaitlist(array $input): Illuminate\Testing\TestResponse
{
    return test()->json('POST', ApiRoutes::path(ApiRoutes::WAITLIST), $input, [
        'Accept' => 'application/json',
    ]);
}

function fakeTurnstile(bool $success = true): void
{
    Http::fake([
        TURNSTILE_URL => Http::response(['success' => $success, 'error-codes' => $success ? [] : ['invalid-input-response']]),
        EMAILOCTOPUS_CONTACTS_URL => Http::response(['id' => 'contact-1', 'status' => 'pending'], 201),
    ]);
}

test('a visitor can join the waitlist', function () {
    fakeTurnstile();

    $response = joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);

    $response->assertOk();
    expect($response->json('type'))->toBe('success');
    expect($response->json('message'))->toContain('Check your inbox');

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === TURNSTILE_URL
            && $request['secret'] === 'turnstile-secret'
            && $request['response'] === 'token-1';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->url() === EMAILOCTOPUS_CONTACTS_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer eo-test-key')
            && $request['email_address'] === 'ada@example.com'
            && $request['status'] === 'pending';
    });
});

test('an address already on the list gets the same answer as a new one', function () {
    Http::fake([
        TURNSTILE_URL => Http::response(['success' => true]),
        EMAILOCTOPUS_CONTACTS_URL => Http::response(['title' => 'Conflict', 'detail' => 'Resource already exists.', 'status' => 409], 409),
    ]);

    joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertOk();
});

test('an invalid email address is refused before any external call', function () {
    Http::fake();

    $response = joinWaitlist(['email' => 'not-an-email', 'turnstileToken' => 'token-1']);

    $response->assertBadRequest();
    expect($response->json('type'))->toBe('validationIssue');
    expect($response->json('errors'))->toHaveKey('email');
    Http::assertNothingSent();
});

test('the turnstile token is required', function () {
    Http::fake();

    $response = joinWaitlist(['email' => 'ada@example.com']);

    $response->assertBadRequest();
    expect($response->json('errors'))->toHaveKey('turnstileToken');
    Http::assertNothingSent();
});

test('a failed bot check is refused and the address is not sent to EmailOctopus', function () {
    fakeTurnstile(success: false);

    $response = joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'bad-token']);

    $response->assertBadRequest();
    expect($response->json('errors'))->toHaveKey('turnstileToken');
    Http::assertNotSent(fn (HttpRequest $request) => $request->url() === EMAILOCTOPUS_CONTACTS_URL);
});

test('a filled honeypot field is answered as a success but nothing is sent', function () {
    Http::fake();

    joinWaitlist([
        'email' => 'bot@example.com',
        'turnstileToken' => 'token-1',
        Constants::WAITLIST_HONEYPOT_FIELD => 'https://spam.example.com',
    ])->assertOk();

    Http::assertNothingSent();
});

test('the waitlist refuses signups when the bot check is not configured', function () {
    config(['services.turnstile.secret_key' => null]);
    Http::fake();

    joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertServerError();

    Http::assertNothingSent();
});

test('the waitlist refuses signups when EmailOctopus is not configured', function () {
    config(['services.emailoctopus.api_key' => null]);
    Http::fake();

    joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertServerError();

    Http::assertNothingSent();
});

test('an address refused by EmailOctopus is reported as a validation issue', function () {
    Http::fake([
        TURNSTILE_URL => Http::response(['success' => true]),
        EMAILOCTOPUS_CONTACTS_URL => Http::response(['errors' => [['detail' => 'This value is not a valid email address.', 'pointer' => '/email_address']]], 422),
    ]);

    $response = joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);

    $response->assertBadRequest();
    expect($response->json('errors'))->toHaveKey('email');
});

test('an EmailOctopus failure is a technical issue without internal details', function () {
    Http::fake([
        TURNSTILE_URL => Http::response(['success' => true]),
        EMAILOCTOPUS_CONTACTS_URL => Http::response(['title' => 'Unauthorized', 'detail' => 'Bad key', 'status' => 401], 401),
    ]);

    $response = joinWaitlist(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);

    $response->assertServerError();
    expect($response->json('type'))->toBe('internalError');
    expect(json_encode($response->json()))->not->toContain('Bad key');
});

test('the waitlist endpoint is rate limited per IP address', function () {
    fakeTurnstile();

    for ($i = 0; $i < Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE; $i++) {
        joinWaitlist(['email' => "user{$i}@example.com", 'turnstileToken' => 'token-1'])->assertOk();
    }

    joinWaitlist(['email' => 'one-more@example.com', 'turnstileToken' => 'token-1'])->assertTooManyRequests();
});

test('the welcome page tells the front-end whether the waitlist is available', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome', false)
            ->where('waitlist.enabled', true)
            ->where('waitlist.turnstileSiteKey', 'site-key'));

    config(['services.emailoctopus.api_key' => null]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('waitlist.enabled', false));
});
