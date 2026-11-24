<?php

use App\Actions\Mail\RemoveResendSuppression;
use App\ApiRoutes;
use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\Mail\RemovesProviderSuppressions;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEmailSuppressionReason;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Mail\EmailAddressChanged;
use App\Mail\MailBudgetAlert;
use App\Models\EmailSuppression as EmailSuppressionRow;
use App\Models\MailDelivery;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use App\Support\EmailSuppression;
use App\Support\ProviderRemovals;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Mail\TestNotificationMail;

/*
 * The suppression of the notification email of an address (#1478, ADR 0054): the reports of the mail provider, the lift by
 * a delivery, by a change of the address and by the member, and what a suppressed address means for each kind of mail.
 * The provider is a fake: the tests never call the network.
 */

/**
 * A removal from the list of the provider that counts its calls
 */
final class FakeProviderRemovals implements RemovesProviderSuppressions
{
    /** @var list<string> */
    public array $removed = [];

    public function __construct(public bool $works = true) {}

    public function remove(string $address): bool
    {
        $this->removed[] = $address;
        if (! $this->works) {
            Log::warning('An address could not be taken off the list of the mail provider');
        }

        return $this->works;
    }
}

// The secret of the test webhook, built at run time so that no secret scanner reads a literal as a real secret
function esSecret(): string
{
    return 'whsec_'.base64_encode('a key of the test webhook, 32 b.');
}

function esReport(string $type, array $data = [], ?string $createdAt = null, ?string $id = null): void
{
    static $count = 0;
    $count++;
    $id ??= 'msg_es_'.$count;
    $body = (string) json_encode(['type' => $type, 'created_at' => $createdAt ?? now()->utc()->format('Y-m-d\TH:i:s.v\Z'), 'data' => array_merge(['email_id' => 'em-none', 'from' => 'Knowii <hello@example.com>', 'to' => ['ada@example.com']], $data)]);
    $timestamp = now()->getTimestamp();
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) base64_decode(substr(esSecret(), 6), true), true));

    test()->call('POST', '/webhooks/resend', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SVIX_ID' => $id, 'HTTP_SVIX_TIMESTAMP' => (string) $timestamp, 'HTTP_SVIX_SIGNATURE' => $signature], $body)->assertStatus(202);
}

function esSoft(): void
{
    esReport('email.bounced', ['bounce' => ['type' => 'Temporary']]);
}

function esSuppress(User $user, KnowiiEmailSuppressionReason $reason, ?string $email = null): EmailSuppressionRow
{
    return EmailSuppressionRow::query()->create(['user_id' => $user->id, 'email' => $email ?? mb_strtolower($user->email), 'reason' => $reason, 'suppressed_at' => now(), 'last_event_at' => now()]);
}

/**
 * The mailer is Resend, with an array transport in place of the network
 */
function esUseResend(): ArrayTransport
{
    $transport = new ArrayTransport;
    Mail::extend('resend', static fn () => $transport);
    config(['mail.default' => 'resend', 'mail.mailers.resend' => ['transport' => 'resend'], 'services.resend.key' => 're_test_key']);
    Mail::purge('resend');

    return $transport;
}

function esRemovals(bool $works = true): FakeProviderRemovals
{
    $fake = new FakeProviderRemovals($works);
    app()->instance(RemovesProviderSuppressions::class, $fake);

    return $fake;
}

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    config(['services.resend.webhook_secret' => esSecret(), 'mail.default' => 'array', 'mail.from.address' => 'hello@example.com']);
    $this->ada = User::factory()->withUserProfile()->create(['email' => 'ada@example.com']);
});

afterEach(function () {
    Carbon::setTestNow();
});

// The reader

test('a row applies only while it is suppressed and its address is the address of the account, without the case (#1478)', function () {
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse();

    $row = EmailSuppressionRow::query()->create(['user_id' => $this->ada->id, 'email' => 'ada@example.com', 'soft_bounce_count' => 2, 'last_event_at' => now()]);
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse();

    $row->forceFill(['reason' => KnowiiEmailSuppressionReason::SoftBounces, 'suppressed_at' => now()])->save();
    expect(EmailSuppression::appliesTo($this->ada))->toBeTrue();

    $this->ada->forceFill(['email' => 'ADA@example.com'])->saveQuietly();
    expect(EmailSuppression::appliesTo($this->ada->refresh()))->toBeTrue();

    $row->forceFill(['email' => 'ada.before@example.com'])->save();
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse();
});

test('a hard bounce, a complaint and the list of the provider replace a row of soft bounces; a second hard bounce changes nothing (#1478)', function () {
    esSoft();
    esSoft();
    expect(EmailSuppressionRow::query()->sole()->soft_bounce_count)->toBe(2);

    esReport('email.bounced', ['bounce' => ['type' => 'Permanent']]);
    $row = EmailSuppressionRow::query()->sole();
    expect($row->reason)->toBe(KnowiiEmailSuppressionReason::HardBounce)
        ->and($row->soft_bounce_count)->toBe(0);
    $since = $row->suppressed_at?->toIso8601String();

    Carbon::setTestNow(now()->addHour());
    esReport('email.bounced', ['bounce' => ['type' => 'Permanent']]);
    esReport('email.suppressed');
    $row->refresh();
    expect($row->reason)->toBe(KnowiiEmailSuppressionReason::HardBounce)
        ->and($row->suppressed_at?->toIso8601String())->toBe($since);

    // A complaint is lifted only by the member: it replaces the reason
    esReport('email.complained');
    expect($row->refresh()->reason)->toBe(KnowiiEmailSuppressionReason::Complaint);
});

test('soft bounces in a row: 2 soft, 1 delivered, 2 soft is not suppressed; the third soft bounce is; a late delivery from before changes nothing (#1478)', function () {
    esSoft();
    esSoft();
    Carbon::setTestNow(now()->addMinute());
    esReport('email.delivered');
    expect(EmailSuppressionRow::query()->sole()->soft_bounce_count)->toBe(0);

    Carbon::setTestNow(now()->addMinute());
    esSoft();
    esSoft();
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse();

    Carbon::setTestNow(now()->addMinute());
    esSoft();
    $row = EmailSuppressionRow::query()->sole();
    expect(EmailSuppression::appliesTo($this->ada))->toBeTrue()
        ->and($row->reason)->toBe(KnowiiEmailSuppressionReason::SoftBounces);

    // A delivery of a mail that the provider reports late, from before the last bounce
    esReport('email.delivered', [], now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z'));
    expect(EmailSuppression::appliesTo($this->ada))->toBeTrue();
});

test('a delivery lifts a hard bounce, soft bounces and the list of the provider, never a complaint (#1478)', function (KnowiiEmailSuppressionReason $reason, bool $lifted) {
    esSuppress($this->ada, $reason);
    Carbon::setTestNow(now()->addMinute());

    esReport('email.delivered');

    expect(EmailSuppression::appliesTo($this->ada))->toBe(! $lifted)
        ->and(EmailSuppressionRow::query()->exists())->toBe(! $lifted);
})->with([
    'hard bounce' => [KnowiiEmailSuppressionReason::HardBounce, true],
    'soft bounces' => [KnowiiEmailSuppressionReason::SoftBounces, true],
    'list of the provider' => [KnowiiEmailSuppressionReason::ProviderList, true],
    'complaint' => [KnowiiEmailSuppressionReason::Complaint, false],
]);

// A change of the address

test('a change of the address lifts the suppression at once; a bounce on the new address makes a new row; the address of before does not come back (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);

    $this->ada->forceFill(['email' => 'ada.new@example.com'])->save();
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse()
        ->and(EmailSuppressionRow::query()->count())->toBe(0);

    esReport('email.bounced', ['to' => ['ada.new@example.com'], 'bounce' => ['type' => 'Permanent']]);
    $row = EmailSuppressionRow::query()->sole();
    expect($row->email)->toBe('ada.new@example.com')
        ->and(EmailSuppression::appliesTo($this->ada->refresh()))->toBeTrue();

    $this->ada->forceFill(['email' => 'ada@example.com'])->save();
    expect(EmailSuppression::appliesTo($this->ada))->toBeFalse();
});

test('a change of the address through its confirmation lifts it too (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::Complaint);
    $transport = esUseResend();
    esRemovals();

    app(ChangesEmailAddresses::class)->request($this->ada, 'ada.new@example.com');
    preg_match('#/user/email-change/[^/]+/([a-f0-9]+)#', (string) $transport->messages()->first()?->getOriginalMessage()->getHtmlBody(), $matches);
    app(ChangesEmailAddresses::class)->confirm($this->ada->refresh(), $matches[1] ?? '');

    expect($this->ada->refresh()->email)->toBe('ada.new@example.com')
        ->and(EmailSuppression::appliesTo($this->ada))->toBeFalse();
});

// What a suppressed address means for each kind of mail

test('a mail that a person asks for is always handed to the mailer, after one removal at the provider; the row stays (#1478)', function (KnowiiEmailSuppressionReason $reason, Closure $ask) {
    esSuppress($this->ada, $reason);
    $transport = esUseResend();
    $removals = esRemovals();

    $ask($this->ada);

    expect($removals->removed)->toBe(['ada@example.com'])
        ->and($transport->messages())->toHaveCount(1)
        ->and(EmailSuppression::appliesTo($this->ada))->toBeTrue();
})->with([
    'hard bounce' => KnowiiEmailSuppressionReason::HardBounce,
    'complaint' => KnowiiEmailSuppressionReason::Complaint,
    'list of the provider' => KnowiiEmailSuppressionReason::ProviderList,
])->with([
    'the sign-in link' => [fn (User $user) => app(SendsMagicLinks::class)->send(['email' => $user->email])],
    'the password reset' => [fn (User $user) => Password::broker()->sendResetLink(['email' => $user->email])],
    'the address check' => [fn (User $user) => $user->sendEmailVerificationNotification()],
]);

test('the confirmation of a new address removes the new address when it is listed (#1478)', function () {
    $other = User::factory()->create(['email' => 'grace@example.com']);
    esSuppress($other, KnowiiEmailSuppressionReason::HardBounce);
    $other->forceFill(['email' => 'grace.new@example.com'])->saveQuietly();
    $transport = esUseResend();
    $removals = esRemovals();

    app(ChangesEmailAddresses::class)->request($this->ada, 'grace@example.com');

    expect($removals->removed)->toBe(['grace@example.com'])
        ->and($transport->messages())->toHaveCount(1);
});

test('soft bounces put no address on the list of the provider: no removal (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::SoftBounces);
    $transport = esUseResend();
    $removals = esRemovals();

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    expect($removals->removed)->toBe([])
        ->and($transport->messages())->toHaveCount(1);
});

test('the cap: 1 removal an hour and 3 in 24 hours per address; the mail goes every time (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    $transport = esUseResend();
    $removals = esRemovals();
    Log::spy();

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    // At most one sign-in link a minute per account
    Carbon::setTestNow(now()->addSeconds(61));
    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    expect($removals->removed)->toHaveCount(1)
        ->and($transport->messages())->toHaveCount(2);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'cap of the removals'))->once();

    foreach (range(1, 3) as $hour) {
        Carbon::setTestNow(now()->addMinutes(61));
        app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    }

    expect($removals->removed)->toHaveCount(3)
        ->and($transport->messages())->toHaveCount(5);
});

test('a removal that fails only logs: the mail still goes (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    $transport = esUseResend();
    esRemovals(works: false);
    Log::spy();

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    expect($transport->messages())->toHaveCount(1);
    Log::shouldHaveReceived('warning')->once();
});

test('the other account mail, the alert and an invitation go as today, with no removal (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    $transport = esUseResend();
    $removals = esRemovals();
    $c = communityWithMembers();

    Mail::to('ada@example.com')->send(new EmailAddressChanged('a***@example.com'));
    Mail::to('ada@example.com')->send(new MailBudgetAlert(CarbonImmutable::parse('2026-11-01'), 80, 100, 80, 'count', 95, null));
    app(InvitesCommunityMembers::class)->invite($c['owner'], $c['community'], ['email' => 'ada@example.com', 'role' => KnowiiCommunityMemberRole::Member->value]);

    expect($removals->removed)->toBe([])
        ->and($transport->messages())->toHaveCount(3);
});

test('a notification mail to a suppressed address: no removal, and not sent (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    $transport = esUseResend();
    $removals = esRemovals();
    config(['queue.default' => 'sync']);

    Mail::to($this->ada)->queue(new TestNotificationMail('Hello', $this->ada));

    expect($removals->removed)->toBe([])
        ->and($transport->messages())->toHaveCount(0);
});

test('with the mailer smtp or log, nothing calls the provider (#1478)', function (string $mailer) {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    $removals = esRemovals();
    config(['mail.default' => $mailer, 'mail.mailers.smtp.transport' => 'array']);
    Mail::purge($mailer);

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    expect($removals->removed)->toBe([]);
})->with(['log', 'smtp']);

test('the answer of the sign-in link form is the same, byte for byte, for an unknown, a healthy and a suppressed address (#1478)', function () {
    User::factory()->create(['email' => 'grace@example.com']);
    esSuppress($this->ada, KnowiiEmailSuppressionReason::Complaint);
    esUseResend();
    esRemovals();

    $answers = [];
    foreach (['nobody@example.com', 'grace@example.com', 'ada@example.com'] as $address) {
        $response = $this->post('/login/link', ['email' => $address]);
        $answers[] = [$response->getStatusCode(), $response->headers->get('Location'), $response->getContent(), session('status')];
    }

    expect($answers[1])->toBe($answers[0])
        ->and($answers[2])->toBe($answers[0]);
});

// The removal at the provider

test('the removal asks the provider with a timeout of 10 seconds, and a failure gives false and a warning without the address (#1478)', function () {
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['object' => 'suppression', 'id' => 'sup_1', 'deleted' => true])),
        new ConnectException('down', new Request('DELETE', 'x')),
    ]));
    $stack->push(Middleware::history($history));
    $removal = new RemoveResendSuppression($stack);
    Log::spy();

    expect($removal->remove('ada@example.com'))->toBeTrue()
        ->and($history[0]['request']->getMethod())->toBe('DELETE')
        ->and(urldecode($history[0]['request']->getUri()->getPath()))->toBe('/suppressions/ada@example.com')
        ->and($history[0]['options']['timeout'])->toBe(10)
        ->and($removal->remove('ada@example.com'))->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => ! str_contains((string) json_encode($context), 'ada@example.com'))->once();

    config(['mail.default' => 'smtp']);
    expect((new RemoveResendSuppression($stack))->remove('ada@example.com'))->toBeFalse()
        ->and($history)->toHaveCount(2);
});

test('the address is encoded in the path of the call: no character of it changes the path or the query (#1478)', function () {
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['object' => 'suppression', 'id' => 'sup_1', 'deleted' => true]))]));
    $stack->push(Middleware::history($history));
    $address = 'a?b/c#d%2F@example.com';

    expect((new RemoveResendSuppression($stack))->remove($address))->toBeTrue();

    $uri = $history[0]['request']->getUri();
    expect($uri->getPath())->toBe('/suppressions/a%3Fb%2Fc%23d%252F%40example.com')
        ->and($uri->getQuery())->toBe('')
        ->and($uri->getFragment())->toBe('')
        ->and(rawurldecode(substr($uri->getPath(), strlen('/suppressions/'))))->toBe($address);
});

test('the check and the count of the cap are one step: while another request holds the lock of the address, no removal (#1478)', function () {
    $lock = Cache::lock(ProviderRemovals::LIMITER.':lock:'.hash('sha256', 'ada@example.com'), 10);
    expect($lock->get())->toBeTrue();

    expect(ProviderRemovals::attempt('ada@example.com'))->toBeFalse();

    $lock->release();
    expect(ProviderRemovals::attempt('ada@example.com'))->toBeTrue()
        ->and(ProviderRemovals::attempt('ada@example.com'))->toBeFalse();
});

// The API

test('the email status of the member: suppressed, reason, since, the address and the groups (#1478)', function () {
    NotificationEmailChoice::query()->create(['user_id' => $this->ada->id, 'group' => KnowiiNotificationEmailGroup::Events, 'state' => NotificationEmailChoice::STATE_OFF]);
    Sanctum::actingAs($this->ada, ['notifications:read']);

    $this->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_STATUS))
        ->assertOk()
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.reason', null)
        ->assertJsonPath('data.since', null)
        ->assertJsonPath('data.address_hint', 'ada@example.com')
        ->assertJsonPath('data.email_choices', [
            ['group' => 'replies_and_mentions', 'state' => 'default'],
            ['group' => 'direct_messages', 'state' => 'default'],
            ['group' => 'important_news', 'state' => 'default'],
            ['group' => 'weekly_summary', 'state' => 'default'],
            ['group' => 'events', 'state' => 'off'],
        ]);

    esSuppress($this->ada, KnowiiEmailSuppressionReason::Complaint);
    $this->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_STATUS))
        ->assertOk()
        ->assertJsonPath('data.suppressed', true)
        ->assertJsonPath('data.reason', 'complaint')
        ->assertJsonPath('data.since', '2026-11-23T10:00:00.000000Z');
});

test('1 or 2 soft bounces show as not suppressed (#1478)', function () {
    esSoft();
    esSoft();
    Sanctum::actingAs($this->ada, ['notifications:read']);

    $this->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_STATUS))
        ->assertOk()
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.reason', null);
});

test('a member never reads the state of another account (#1478)', function () {
    $other = User::factory()->create(['email' => 'grace@example.com']);
    esSuppress($other, KnowiiEmailSuppressionReason::HardBounce);
    Sanctum::actingAs($this->ada, ['notifications:read']);

    $this->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_STATUS).'?user='.$other->cuid)
        ->assertOk()
        ->assertJsonPath('data.suppressed', false)
        ->assertJsonPath('data.address_hint', 'ada@example.com');
});

test('a token without the ability gets 403 (#1478)', function () {
    Sanctum::actingAs($this->ada, ['user:read']);
    $this->getJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_STATUS))->assertForbidden();
    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertForbidden();

    Sanctum::actingAs(User::factory()->create(), ['notifications:read']);
    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertForbidden();
});

test('"Try this address again" deletes the row, asks the provider to remove the address, and the second call within the hour gives 429 (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::Complaint);
    esUseResend();
    $removals = esRemovals();
    Sanctum::actingAs($this->ada, ['notifications:write']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))
        ->assertOk()
        ->assertJsonPath('data.suppressed', false);

    expect(EmailSuppressionRow::query()->count())->toBe(0)
        ->and($removals->removed)->toBe(['ada@example.com']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertStatus(429);

    Carbon::setTestNow(now()->addMinutes(61));
    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertOk();
});

test('a failed removal at the provider only logs; without the provider nothing is called (#1478)', function () {
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    esUseResend();
    esRemovals(works: false);
    Sanctum::actingAs($this->ada, ['notifications:write']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertOk();
    expect(EmailSuppressionRow::query()->count())->toBe(0);

    $other = User::factory()->create(['email' => 'grace@example.com']);
    esSuppress($other, KnowiiEmailSuppressionReason::HardBounce);
    config(['mail.default' => 'smtp']);
    $removals = esRemovals();
    Sanctum::actingAs($other, ['notifications:write']);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::NOTIFICATIONS_EMAIL_SUPPRESSION))->assertOk();
    expect($removals->removed)->toBe([]);
});

// The export and the deletion

test('the member export holds the suppression and the email choices; an account deletion removes them (#1478)', function () {
    Storage::fake('local');
    esSuppress($this->ada, KnowiiEmailSuppressionReason::HardBounce);
    NotificationEmailChoice::query()->create(['user_id' => $this->ada->id, 'group' => KnowiiNotificationEmailGroup::WeeklySummary, 'state' => NotificationEmailChoice::STATE_OFF]);

    $status = Artisan::call('users:export', ['email' => 'ada@example.com']);
    expect($status)->toBe(0);
    $zip = new ZipArchive;
    expect($zip->open(trim(Artisan::output())))->toBeTrue();
    $account = json_decode((string) $zip->getFromName('account.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();

    expect($account['email_delivery'])->toBe([
        'suppressed' => true,
        'reason' => 'hard_bounce',
        'since' => '2026-11-23T10:00:00+00:00',
        'address' => 'ada@example.com',
        'soft_bounces' => 0,
    ])
        ->and($account['email_choices'])->toBe([['group' => 'weekly_summary', 'state' => 'off']])
        ->and($readme)->toContain('notification emails');

    // The delivery log of the account and the report on an invitation to the address
    MailDelivery::query()->forceCreate(['cuid' => 'deliveryrowfortestsaaaaa', 'kind' => 'asked_for', 'user_id' => $this->ada->id, 'status' => 'sent', 'provider_email_id' => 'em-1', 'sent_at' => now()]);
    $c = communityWithMembers();
    $c['community']->communityInvitations()->create(['email' => 'ada@example.com', 'role' => 'member'])->forceFill(['bounced_at' => now(), 'bounce_type' => 'hard'])->save();
    Artisan::call('users:export', ['email' => 'ada@example.com']);
    $zip = new ZipArchive;
    expect($zip->open(trim(Artisan::output())))->toBeTrue();
    $activity = json_decode((string) $zip->getFromName('activity.json'), true);
    $memberships = json_decode((string) $zip->getFromName('memberships.json'), true);
    $readme = (string) $zip->getFromName('README.txt');
    $zip->close();
    expect($activity['mail_deliveries'])->toBe([[
        'cuid' => 'deliveryrowfortestsaaaaa',
        'kind' => 'asked_for',
        'status' => 'sent',
        'provider_email_id' => 'em-1',
        'sent_at' => '2026-11-23T10:00:00+00:00',
        'created_at' => '2026-11-23T10:00:00+00:00',
    ]])
        ->and($memberships['invitations'][0]['bounce_type'])->toBe('hard')
        ->and($memberships['invitations'][0]['bounced_at'])->toBe('2026-11-23T10:00:00+00:00')
        ->and($readme)->toContain('the record of each email that we sent you')
        ->and($readme)->toContain('could not be delivered');

    $this->ada->delete();
    expect(EmailSuppressionRow::query()->count())->toBe(0)
        ->and(NotificationEmailChoice::query()->count())->toBe(0);
});
