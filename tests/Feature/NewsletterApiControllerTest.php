<?php

use App\ApiRoutes;
use App\Constants;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

const NEWSLETTER_TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const NEWSLETTER_CONTACTS_URL = 'https://api.emailoctopus.com/lists/newsletter-456/contacts';

beforeEach(function () {
    config([
        'services.emailoctopus.base_url' => 'https://api.emailoctopus.com',
        'services.emailoctopus.api_key' => 'eo-test-key',
        'services.emailoctopus.newsletter_list_id' => 'newsletter-456',
        'services.emailoctopus.newsletter_contact_status' => 'pending',
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'turnstile-secret',
        'services.turnstile.verify_url' => NEWSLETTER_TURNSTILE_URL,
    ]);

    RateLimiter::clear('newsletter');
    Http::preventStrayRequests();
});

function subscribeToNewsletter(array $input): TestResponse
{
    return test()->json('POST', ApiRoutes::path(ApiRoutes::NEWSLETTER), $input, ['Accept' => 'application/json']);
}

function fakeNewsletterServices(bool $turnstileSuccess = true): void
{
    Http::fake([
        NEWSLETTER_TURNSTILE_URL => Http::response(['success' => $turnstileSuccess, 'error-codes' => []]),
        NEWSLETTER_CONTACTS_URL => Http::response(['id' => 'contact-1', 'status' => 'pending'], 201),
    ]);
}

test('a visitor can subscribe to the newsletter list, not to the waitlist (#938)', function () {
    config(['services.emailoctopus.waitlist_list_id' => 'list-123']);
    fakeNewsletterServices();

    $response = subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);

    $response->assertOk();
    expect($response->json('message'))->toContain('Check your inbox');
    Http::assertSent(fn (HttpRequest $request) => $request->url() === NEWSLETTER_CONTACTS_URL
        && $request['email_address'] === 'ada@example.com'
        && $request['status'] === 'pending');
});

test('a failed bot check sends nothing to EmailOctopus', function () {
    fakeNewsletterServices(turnstileSuccess: false);

    subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'bad'])->assertBadRequest();

    Http::assertNotSent(fn (HttpRequest $request) => $request->url() === NEWSLETTER_CONTACTS_URL);
});

test('a filled honeypot field is answered as a success but nothing is sent', function () {
    Http::fake();

    subscribeToNewsletter([
        'email' => 'bot@example.com',
        'turnstileToken' => 'token-1',
        Constants::WAITLIST_HONEYPOT_FIELD => 'https://spam.example.com',
    ])->assertOk();

    Http::assertNothingSent();
});

test('the endpoint refuses subscriptions while no newsletter list is configured', function () {
    config(['services.emailoctopus.newsletter_list_id' => null]);
    Http::fake();

    $response = subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);

    $response->assertServerError();
    expect($response->json('message'))->toContain('newsletter is not available');
    Http::assertNothingSent();
});

test('the newsletter endpoint is rate limited per IP address', function () {
    fakeNewsletterServices();

    for ($i = 0; $i < Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE; $i++) {
        subscribeToNewsletter(['email' => "user{$i}@example.com", 'turnstileToken' => 'token-1'])->assertOk();
    }

    subscribeToNewsletter(['email' => 'one-more@example.com', 'turnstileToken' => 'token-1'])->assertTooManyRequests();
});

test('every page tells the footer whether the newsletter form shows', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('newsletter.enabled', true)
            ->where('newsletter.turnstileSiteKey', 'site-key'));

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('newsletter.enabled', true));

    config(['services.emailoctopus.newsletter_list_id' => null]);

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('newsletter.enabled', false)
            ->where('newsletter.turnstileSiteKey', null));
});
