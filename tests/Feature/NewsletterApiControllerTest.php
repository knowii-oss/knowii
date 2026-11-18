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

// The cases below test the code that the newsletter shares with the former waitlist (AddsEmailOctopusContact, #1574)
test('an address already on the newsletter list gets the same answer as a new one', function () {
    Http::fake([
        NEWSLETTER_TURNSTILE_URL => Http::response(['success' => true]),
        NEWSLETTER_CONTACTS_URL => Http::response(['title' => 'Conflict', 'detail' => 'Resource already exists.', 'status' => 409], 409),
    ]);

    subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertOk();
});

test('an invalid email address or a missing token is refused before any external call', function () {
    Http::fake();

    $response = subscribeToNewsletter(['email' => 'not-an-email', 'turnstileToken' => 'token-1']);
    $response->assertBadRequest();
    expect($response->json('type'))->toBe('validationIssue');
    expect($response->json('errors'))->toHaveKey('email');

    subscribeToNewsletter(['email' => 'ada@example.com'])->assertBadRequest()->assertJsonStructure(['errors' => ['turnstileToken']]);

    Http::assertNothingSent();
});

test('the newsletter refuses subscriptions when the bot check or EmailOctopus is not configured', function () {
    Http::fake();

    config(['services.turnstile.secret_key' => null]);
    subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertServerError();

    config(['services.turnstile.secret_key' => 'turnstile-secret', 'services.emailoctopus.api_key' => null]);
    subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1'])->assertServerError();

    Http::assertNothingSent();
});

test('an address refused by EmailOctopus is a validation issue, another failure a technical issue without details', function () {
    Http::fake([
        NEWSLETTER_TURNSTILE_URL => Http::response(['success' => true]),
        NEWSLETTER_CONTACTS_URL => Http::sequence()
            ->push(['errors' => [['detail' => 'This value is not a valid email address.', 'pointer' => '/email_address']]], 422)
            ->push(['title' => 'Unauthorized', 'detail' => 'Bad key', 'status' => 401], 401),
    ]);

    $refused = subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-1']);
    $refused->assertBadRequest();
    expect($refused->json('errors'))->toHaveKey('email');

    $failed = subscribeToNewsletter(['email' => 'ada@example.com', 'turnstileToken' => 'token-2']);
    $failed->assertServerError();
    expect($failed->json('type'))->toBe('internalError');
    expect((string) $failed->getContent())->not->toContain('Bad key');
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
