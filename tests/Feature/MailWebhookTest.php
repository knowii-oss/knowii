<?php

use App\Enums\KnowiiEmailSuppressionReason;
use App\Enums\KnowiiInvitationBounceType;
use App\Enums\KnowiiMailDeliveryStatus;
use App\Enums\KnowiiMailKind;
use App\Jobs\HandleMailWebhookEvent;
use App\Jobs\Middleware\HideMailErrorText;
use App\Models\CommunityInvitation;
use App\Models\EmailSuppression;
use App\Models\MailDelivery;
use App\Models\MailWebhookEvent;
use App\Models\User;
use App\Support\MailEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * The webhook of the mail provider (#1478, ADR 0054): bounces, complaints, the list of the provider and the deliveries. The
 * test signs each call with a secret that it builds: never a real secret, and never the network.
 */

const MW_PATH = '/webhooks/resend';

function mwSecret(): string
{
    return 'whsec_'.base64_encode('a key of the test webhook, 32 b.');
}

/**
 * The signature of a call, as the provider writes it: `v1,` and the base64 of an HMAC-SHA256 of "id.timestamp.body"
 */
function mwSign(string $id, int|string $timestamp, string $body, ?string $secret = null): string
{
    $key = base64_decode(substr($secret ?? mwSecret(), strlen('whsec_')), true);

    return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));
}

/**
 * @param  array<string, mixed>  $data
 */
function mwBody(string $type, array $data = [], ?string $createdAt = null): string
{
    return (string) json_encode([
        'type' => $type,
        'created_at' => $createdAt ?? now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        'data' => array_merge([
            'email_id' => 'em-test-1',
            'from' => 'Knowii <hello@example.com>',
            'to' => ['ada@example.com'],
            'subject' => 'A subject',
        ], $data),
    ]);
}

/**
 * @param  array<string, string|null>  $headers  The svix headers to send: a null value leaves the header out
 */
function mwCall(string $body, array $headers): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    foreach ($headers as $name => $value) {
        if ($value !== null) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
    }

    return test()->call('POST', MW_PATH, [], [], [], $server, $body);
}

/**
 * A valid call: a fresh timestamp, signed with the secret of the test
 */
function mwPost(string $body, string $id = 'msg_test_1', ?int $timestamp = null): TestResponse
{
    $timestamp ??= now()->getTimestamp();

    return mwCall($body, ['svix-id' => $id, 'svix-timestamp' => (string) $timestamp, 'svix-signature' => mwSign($id, $timestamp, $body)]);
}

function mwDelivery(KnowiiMailKind $kind, ?User $user = null, ?CommunityInvitation $invitation = null, string $emailId = 'em-test-1'): MailDelivery
{
    return MailDelivery::query()->create([
        'kind' => $kind,
        'user_id' => $user?->id,
        'community_invitation_id' => $invitation?->id,
        'channel' => MailDelivery::CHANNEL_EMAIL,
        'status' => KnowiiMailDeliveryStatus::Sent,
        'provider_email_id' => $emailId,
    ]);
}

beforeEach(function () {
    config(['services.resend.webhook_secret' => mwSecret(), 'mail.default' => 'array', 'mail.from.address' => 'hello@example.com']);
    $this->ada = User::factory()->create(['email' => 'ada@example.com']);
});

// The route

test('the webhook is public: only POST, no session, no CSRF token, no token ability, no verified address (#1478)', function () {
    $route = Route::getRoutes()->getByName('mail.webhook');
    expect($route)->not->toBeNull();
    $middleware = app('router')->gatherRouteMiddleware($route);

    expect($route->methods())->toBe(['POST'])
        ->and($route->uri())->toBe('webhooks/resend')
        ->and($route->gatherMiddleware())->toContain('throttle:mail-webhook')
        ->and($middleware)->not->toContain(PreventRequestForgery::class)
        ->and($middleware)->not->toContain(StartSession::class);
    foreach ($middleware as $name) {
        expect(is_string($name) ? $name : '')->not->toStartWith('auth')->not->toStartWith('ability')->not->toStartWith('verified');
    }

    // A signed-in browser session does not change the answer
    $this->actingAs($this->ada);
    mwCall('{}', ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v1,AAAA'])->assertStatus(401);
    mwPost(mwBody('email.delivered'))->assertStatus(202);
});

test('a valid call answers 202 with an empty body, and sets no cookie (#1478)', function () {
    $response = mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent', 'subType' => 'General', 'message' => 'x']]));

    $response->assertStatus(202);
    expect($response->getContent())->toBe('')
        ->and($response->headers->getCookies())->toBe([]);
});

// The secret

test('without a usable secret the route answers 404 to every call and checks nothing (#1478)', function (?string $secret) {
    config(['services.resend.webhook_secret' => $secret]);
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]);
    $timestamp = now()->getTimestamp();

    // Signed with the bytes that a lax decoding of the secret would give, and with an empty key
    foreach (['', (string) base64_decode(substr((string) $secret, 6)), (string) base64_decode((string) $secret)] as $key) {
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', "msg_1.{$timestamp}.{$body}", $key, true));
        mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) $timestamp, 'svix-signature' => $signature])->assertStatus(404);
    }
    mwPost($body)->assertStatus(404);

    expect(MailWebhookEvent::query()->count())->toBe(0)
        ->and(EmailSuppression::query()->count())->toBe(0);
})->with([
    'empty' => [''],
    'not set' => [null],
    'without whsec_' => [base64_encode('a key of the test webhook, 32 b.')],
    'whsec_ and nothing' => ['whsec_'],
    'not strict base64' => ['whsec_a-key*of#the!test'],
    'base64 with a space' => ['whsec_YWJj ZGVm'],
    'base64 without its padding' => ['whsec_YWJjZA'],
    'base64 of an empty value' => ['whsec_='],
    'another prefix' => ['wh_'.base64_encode('a key of the test webhook, 32 b.')],
]);

test('a secret that cannot be used writes one warning a day (#1478)', function () {
    config(['services.resend.webhook_secret' => 'whsec_not*base64']);
    Log::spy();

    mwPost('{}')->assertStatus(404);
    mwPost('{}')->assertStatus(404);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'webhook secret'))->once();

    $this->travel(25)->hours();
    mwPost('{}')->assertStatus(404);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'webhook secret'))->twice();
});

test('an empty secret writes no warning: the instance has no webhook (#1478)', function () {
    config(['services.resend.webhook_secret' => '']);
    Log::spy();

    mwPost('{}')->assertStatus(404);
    Log::shouldNotHaveReceived('warning');
});

// The signature

test('a call that is not signed right answers 401 with an empty body, and writes nothing (#1478)', function (Closure $call) {
    $response = $call(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]));

    $response->assertStatus(401);
    expect($response->getContent())->toBe('')
        ->and(MailWebhookEvent::query()->count())->toBe(0)
        ->and(EmailSuppression::query()->count())->toBe(0);
})->with([
    'a wrong secret' => [fn (string $body) => mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_1', now()->getTimestamp(), $body, 'whsec_'.base64_encode('another key of 32 bytes, for me.'))])],
    'one changed byte of the body' => [fn (string $body) => mwCall(str_replace('Permanent', 'Permanenu', $body), ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_1', now()->getTimestamp(), $body)])],
    'the same JSON with other white space' => [fn (string $body) => mwCall((string) json_encode(json_decode($body), JSON_PRETTY_PRINT), ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_1', now()->getTimestamp(), $body)])],
    'a signature of another id' => [fn (string $body) => mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_2', now()->getTimestamp(), $body)])],
    'a signature of another time' => [fn (string $body) => mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_1', now()->getTimestamp() - 1, $body)])],
    'only v2 items' => [fn (string $body) => mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v2,'.substr(mwSign('msg_1', now()->getTimestamp(), $body), 3)])],
    'a v1 item with a signature that is not base64' => [fn (string $body) => mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v1,not*base64'])],
]);

test('a call with a missing or a malformed header answers 401 with an empty body, never 5xx, and writes nothing (#1478)', function (Closure $headers) {
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]);

    $response = mwCall($body, $headers($body));

    $response->assertStatus(401);
    expect($response->getContent())->toBe('')
        ->and(MailWebhookEvent::query()->count())->toBe(0);
})->with([
    'no svix-id' => [fn (string $body) => ['svix-id' => null, 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg_1', now()->getTimestamp(), $body)]],
    'no svix-timestamp' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => null, 'svix-signature' => mwSign('msg_1', now()->getTimestamp(), $body)]],
    'no svix-signature' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => null]],
    'an empty svix-id' => [fn (string $body) => ['svix-id' => '', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('', now()->getTimestamp(), $body)]],
    'an empty svix-signature' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => '']],
    'an empty svix-timestamp' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => '', 'svix-signature' => mwSign('msg_1', '', $body)]],
    'a timestamp that is not a number' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => 'yesterday', 'svix-signature' => mwSign('msg_1', 'yesterday', $body)]],
    'a timestamp with a fraction' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => now()->getTimestamp().'.5', 'svix-signature' => mwSign('msg_1', now()->getTimestamp().'.5', $body)]],
    'a timestamp in another notation' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => '1.7e9', 'svix-signature' => mwSign('msg_1', '1.7e9', $body)]],
    'a header item without a comma' => [fn (string $body) => ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v1']],
    'an id that is too long' => [fn (string $body) => ['svix-id' => str_repeat('m', 256), 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign(str_repeat('m', 256), now()->getTimestamp(), $body)]],
    'an id with a space' => [fn (string $body) => ['svix-id' => 'msg 1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => mwSign('msg 1', now()->getTimestamp(), $body)]],
]);

test('a header with two items, one of them valid, answers 202 (#1478)', function () {
    $body = mwBody('email.delivered');
    $timestamp = now()->getTimestamp();
    $valid = mwSign('msg_1', $timestamp, $body);

    mwCall($body, ['svix-id' => 'msg_1', 'svix-timestamp' => (string) $timestamp, 'svix-signature' => 'v1,'.base64_encode(str_repeat('x', 32)).' '.$valid])->assertStatus(202);
    mwCall($body, ['svix-id' => 'msg_2', 'svix-timestamp' => (string) $timestamp, 'svix-signature' => mwSign('msg_2', $timestamp, $body).' v2,abc'])->assertStatus(202);
});

test('the replay window is 300 seconds, in the past and in the future (#1478)', function (int $shift, int $status) {
    mwPost(mwBody('email.delivered'), 'msg_window', now()->getTimestamp() + $shift)->assertStatus($status);
})->with([
    '301 seconds in the past' => [-301, 401],
    '301 seconds in the future' => [301, 401],
    '299 seconds in the past' => [-299, 202],
    '299 seconds in the future' => [299, 202],
]);

// Idempotency

test('the same event twice, each time with a fresh time, counts once (#1478)', function () {
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Temporary']]);

    mwPost($body, 'msg_same')->assertStatus(202);
    $this->travel(10)->minutes();
    mwPost($body, 'msg_same')->assertStatus(202);

    expect(EmailSuppression::query()->sole()->soft_bounce_count)->toBe(1)
        ->and(MailWebhookEvent::query()->where('provider_event_id', 'msg_same')->count())->toBe(1);
});

test('a refused call never uses up the id of a real event (#1478)', function () {
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Temporary']]);
    $timestamp = now()->getTimestamp();

    mwCall($body, ['svix-id' => 'msg_real', 'svix-timestamp' => (string) $timestamp, 'svix-signature' => 'v1,'.base64_encode(str_repeat('f', 32))])->assertStatus(401);
    mwPost($body, 'msg_real')->assertStatus(202);

    expect(EmailSuppression::query()->sole()->soft_bounce_count)->toBe(1);
});

test('the work runs in a queued job on default; the log holds no address, no header, no secret and no body (#1478)', function () {
    Queue::fake();
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]);

    mwPost($body)->assertStatus(202);

    Queue::assertPushedOn('default', HandleMailWebhookEvent::class);
    expect(EmailSuppression::query()->count())->toBe(0);
});

test('the log of an event names its type and the cuids, never an address, a header, the secret or the body (#1478)', function () {
    $delivery = mwDelivery(KnowiiMailKind::RepliesAndMentions, $this->ada);
    $lines = [];
    Log::listen(function ($message) use (&$lines) {
        $lines[] = $message->message.' '.json_encode($message->context);
    });
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Permanent', 'message' => 'The mailbox of ada@example.com does not exist']]);

    mwPost($body, 'msg_logged')->assertStatus(202);

    $all = implode("\n", $lines);
    expect($all)->toContain('email.bounced')
        ->and($all)->toContain($delivery->cuid)
        ->and($all)->toContain($this->ada->cuid)
        ->and($all)->not->toContain('ada@example.com')
        ->and($all)->not->toContain('msg_logged')
        ->and($all)->not->toContain(substr(mwSecret(), 6))
        ->and($all)->not->toContain('does not exist');
});

test('the job of an event keeps no text of an error: the text of a database error can hold the address (#1478)', function () {
    $middleware = (new HandleMailWebhookEvent(new MailEvent('delivered', 'email.delivered', 'eventcuid', 'em-1', 'ada@example.com', CarbonImmutable::now())))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(HideMailErrorText::class);
});

test('a body over 256 KB answers 413 with an empty body, before any check (#1478)', function () {
    $body = mwBody('email.delivered', ['padding' => str_repeat('x', 262145)]);

    $response = mwPost($body);

    $response->assertStatus(413);
    expect($response->getContent())->toBe('')
        ->and(MailWebhookEvent::query()->count())->toBe(0);

    // A body just under the limit is checked as usual
    mwPost(mwBody('email.delivered', ['padding' => str_repeat('x', 200000)]), 'msg_under')->assertStatus(202);
});

test('when the push of the job fails, the id of the event is not kept: the retry of the provider acts (#1478)', function () {
    $real = app(Dispatcher::class);
    $failing = new class(app()) extends Illuminate\Bus\Dispatcher
    {
        public function dispatch($command)
        {
            throw new RuntimeException('The queue is not reachable');
        }
    };
    app()->instance(Dispatcher::class, $failing);
    Bus::clearResolvedInstances();
    $body = mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]);

    mwPost($body, 'msg_lost')->assertStatus(500);
    expect(MailWebhookEvent::query()->count())->toBe(0);

    app()->instance(Dispatcher::class, $real);
    Bus::clearResolvedInstances();
    mwPost($body, 'msg_lost')->assertStatus(202);

    expect(MailWebhookEvent::query()->count())->toBe(1)
        ->and(EmailSuppression::query()->sole()->reason)->toBe(KnowiiEmailSuppressionReason::HardBounce);
});

test('an address with a display name in data.to finds its account (#1478)', function () {
    mwDelivery(KnowiiMailKind::Account, $this->ada);

    mwPost(mwBody('email.bounced', ['to' => ['Ada Lovelace <Ada@Example.com>'], 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->sole()->email)->toBe('ada@example.com');
});

test('without a row, a report about a mail of another sender of the team changes nothing (#1478)', function () {
    Log::spy();

    mwPost(mwBody('email.complained', ['email_id' => 'em-other', 'from' => 'Another product <news@example.org>']), 'msg_other')->assertStatus(202);
    mwPost(mwBody('email.bounced', ['email_id' => 'em-none', 'from' => null, 'bounce' => ['type' => 'Permanent']]), 'msg_nofrom')->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'another sender'))->twice();

    // The sender address of this instance, with a display name and in another case
    mwPost(mwBody('email.complained', ['email_id' => 'em-own', 'from' => 'Knowii <Hello@Example.com>']), 'msg_own')->assertStatus(202);
    expect(EmailSuppression::query()->sole()->reason)->toBe(KnowiiEmailSuppressionReason::Complaint);
});

// The limiter

test('the webhook is limited per IP address: the 601st call in a minute gives 429 (#1478)', function () {
    Queue::fake();
    for ($i = 0; $i < 600; $i++) {
        mwCall('{}', ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v1,AAAA']);
    }

    mwCall('{}', ['svix-id' => 'msg_1', 'svix-timestamp' => (string) now()->getTimestamp(), 'svix-signature' => 'v1,AAAA'])->assertStatus(429);
    mwPost(mwBody('email.delivered'))->assertStatus(429);
});

// The events

test('a Permanent bounce is a hard bounce (#1478)', function () {
    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    $row = EmailSuppression::query()->sole();
    expect($row->reason)->toBe(KnowiiEmailSuppressionReason::HardBounce)
        ->and($row->suppressed_at)->not->toBeNull()
        ->and($row->email)->toBe('ada@example.com')
        ->and($row->user_id)->toBe($this->ada->id);
});

test('a Temporary bounce, or any other or missing type, is a soft bounce; a type that is not Temporary writes a log line (#1478)', function (?array $bounce, bool $logged) {
    Log::spy();

    mwPost(mwBody('email.bounced', $bounce === null ? [] : ['bounce' => $bounce]))->assertStatus(202);

    $row = EmailSuppression::query()->sole();
    expect($row->soft_bounce_count)->toBe(1)
        ->and($row->suppressed_at)->toBeNull()
        ->and($row->reason)->toBeNull();
    if ($logged) {
        Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'bounce type'))->once();
    } else {
        Log::shouldNotHaveReceived('info', [Mockery::on(fn ($message) => str_contains((string) $message, 'bounce type')), Mockery::any()]);
    }
})->with([
    'Temporary' => [['type' => 'Temporary'], false],
    'Transient' => [['type' => 'Transient'], false],
    'Undetermined' => [['type' => 'Undetermined'], false],
    'another value' => [['type' => 'Sometimes'], true],
    'no type' => [['subType' => 'General'], true],
    'no bounce' => [null, true],
    'a number' => [['type' => 5], true],
]);

test('a complaint suppresses with the reason complaint (#1478)', function () {
    mwPost(mwBody('email.complained'))->assertStatus(202);

    expect(EmailSuppression::query()->sole()->reason)->toBe(KnowiiEmailSuppressionReason::Complaint);
});

test('email.suppressed is handled as a hard bounce with the reason provider_list (#1478)', function () {
    mwPost(mwBody('email.suppressed', ['suppressed' => ['type' => 'OnAccountSuppressionList', 'message' => 'x']]))->assertStatus(202);

    $row = EmailSuppression::query()->sole();
    expect($row->reason)->toBe(KnowiiEmailSuppressionReason::ProviderList)
        ->and($row->suppressed_at)->not->toBeNull();
});

test('another type, no email id, or not exactly one address: 202 and nothing else (#1478)', function (string $body) {
    mwPost($body)->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
})->with([
    'email.opened' => [mwBody('email.opened')],
    'email.sent' => [mwBody('email.sent')],
    'no type' => [(string) json_encode(['data' => ['email_id' => 'em-test-1', 'to' => ['ada@example.com']]])],
    'no email id' => [mwBody('email.bounced', ['email_id' => null, 'bounce' => ['type' => 'Permanent']])],
    'an empty email id' => [mwBody('email.bounced', ['email_id' => '', 'bounce' => ['type' => 'Permanent']])],
    'two addresses' => [mwBody('email.bounced', ['to' => ['ada@example.com', 'bob@example.com'], 'bounce' => ['type' => 'Permanent']])],
    'no address' => [mwBody('email.bounced', ['to' => [], 'bounce' => ['type' => 'Permanent']])],
    'an address that is a string' => [mwBody('email.bounced', ['to' => 'ada@example.com', 'bounce' => ['type' => 'Permanent']])],
    'not JSON' => ['not json'],
    'a JSON list' => ['[1, 2]'],
]);

test('a person that the platform does not know gives the same 202, and nothing changes (#1478)', function () {
    mwPost(mwBody('email.bounced', ['to' => ['nobody@example.com'], 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
});

// Linking a report to a mail

test('a report on an invitation mail changes the invitation, never the account with the same address (#1478)', function () {
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'ada@example.com', 'role' => 'member']);
    mwDelivery(KnowiiMailKind::Invitation, invitation: $invitation);

    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    $invitation->refresh();
    expect($invitation->bounce_type)->toBe(KnowiiInvitationBounceType::Hard)
        ->and($invitation->bounced_at)->not->toBeNull()
        ->and(EmailSuppression::query()->count())->toBe(0);
});

test('the bounce type of an invitation: hard for a hard bounce, a complaint and the list of the provider, soft for a soft bounce (#1478)', function (string $body, KnowiiInvitationBounceType $type) {
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => 'member']);
    mwDelivery(KnowiiMailKind::Invitation, invitation: $invitation);

    mwPost($body)->assertStatus(202);

    expect($invitation->refresh()->bounce_type)->toBe($type);
})->with([
    'hard bounce' => [mwBody('email.bounced', ['to' => ['grace@example.com'], 'bounce' => ['type' => 'Permanent']]), KnowiiInvitationBounceType::Hard],
    'complaint' => [mwBody('email.complained', ['to' => ['grace@example.com']]), KnowiiInvitationBounceType::Hard],
    'list of the provider' => [mwBody('email.suppressed', ['to' => ['grace@example.com']]), KnowiiInvitationBounceType::Hard],
    'soft bounce' => [mwBody('email.bounced', ['to' => ['grace@example.com'], 'bounce' => ['type' => 'Temporary']]), KnowiiInvitationBounceType::Soft],
]);

test('a soft bounce never turns a hard bounce of an invitation into a soft one (#1478)', function () {
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => 'member']);
    mwDelivery(KnowiiMailKind::Invitation, invitation: $invitation);

    mwPost(mwBody('email.bounced', ['to' => ['grace@example.com'], 'bounce' => ['type' => 'Permanent']]), 'msg_a')->assertStatus(202);
    mwPost(mwBody('email.bounced', ['to' => ['grace@example.com'], 'bounce' => ['type' => 'Temporary']]), 'msg_b')->assertStatus(202);

    expect($invitation->refresh()->bounce_type)->toBe(KnowiiInvitationBounceType::Hard);
});

test('a late report on a deleted invitation changes no account (#1478)', function () {
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'ada@example.com', 'role' => 'member']);
    $delivery = mwDelivery(KnowiiMailKind::Invitation, invitation: $invitation);
    $invitation->delete();

    expect($delivery->refresh()->community_invitation_id)->toBeNull()
        ->and($delivery->kind)->toBe(KnowiiMailKind::Invitation);

    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
});

test('a report on the alert of the operator changes nothing (#1478)', function () {
    mwDelivery(KnowiiMailKind::Operator);

    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
});

test('a report on a mail of an account changes that account (#1478)', function () {
    $other = User::factory()->create(['email' => 'Grace@Example.com']);
    mwDelivery(KnowiiMailKind::AskedFor, $other);

    mwPost(mwBody('email.bounced', ['to' => ['grace@example.com'], 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    $row = EmailSuppression::query()->sole();
    expect($row->user_id)->toBe($other->id)
        ->and($row->email)->toBe('grace@example.com');
});

test('a report on a mail of an account to another address than the address of the account changes nothing (#1478)', function () {
    // The mail that confirms a new address, or the notice to the address of before
    mwDelivery(KnowiiMailKind::AskedFor, $this->ada);

    mwPost(mwBody('email.bounced', ['to' => ['ada.new@example.com'], 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
});

test('without a row, the address finds the account, compared without the case (#1478)', function () {
    mwPost(mwBody('email.bounced', ['email_id' => 'em-unknown', 'to' => ['ADA@example.com'], 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->sole()->user_id)->toBe($this->ada->id);
});

test('without a row, an address that an invitation holds too changes nothing, and a log line says so (#1478)', function () {
    $c = communityWithMembers();
    $c['community']->communityInvitations()->create(['email' => 'ada@example.com', 'role' => 'member']);
    Log::spy();

    mwPost(mwBody('email.bounced', ['email_id' => 'em-unknown', 'bounce' => ['type' => 'Permanent']]))->assertStatus(202);

    expect(EmailSuppression::query()->count())->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'invitation holds the same address'))->once();
});

// An instance without the provider

test('with the mailer log or smtp and no secret, the webhook is 404 and nothing calls the provider (#1478)', function (string $mailer) {
    config(['mail.default' => $mailer, 'services.resend.webhook_secret' => '']);

    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']]))->assertStatus(404);
    expect(MailWebhookEvent::query()->count())->toBe(0);
})->with(['log', 'smtp']);

test('the platform has its own verifier: no code of the application calls the verifier of the provider library (#1478)', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    $callers = [];
    foreach ($files as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php') && preg_match('/(?<![A-Za-z])WebhookSignature::verify|Resend\\\\WebhookSignature/', (string) file_get_contents($file->getPathname())) === 1) {
            $callers[] = $file->getFilename();
        }
    }

    expect($callers)->toBe([]);
});

test('the time of a report is its created_at, else the time of the receipt (#1478)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));

    mwPost(mwBody('email.bounced', ['bounce' => ['type' => 'Permanent']], '2026-11-23T09:59:00.000Z'))->assertStatus(202);
    expect(EmailSuppression::query()->sole()->last_event_at?->toIso8601ZuluString())->toBe('2026-11-23T09:59:00Z');
});
