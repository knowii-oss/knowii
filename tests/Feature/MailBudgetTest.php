<?php

use App\Actions\Mail\ResendMailUsage;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Mail\ReadsMailUsage;
use App\Mail\MailBudgetAlert;
use App\Mail\NotificationMail;
use App\Support\MailBudgetCheck;
use App\Support\MailSettings;
use App\Support\MailUsage;
use App\Support\MailUsagePeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/*
 * The mail budget (#1477, ADR 0054): mail:check-budget compares the mail of the month with the quota, alerts the
 * operator at 80% and holds the notification mail at the hold percent. The usage of the provider is a fake: the tests
 * never call the network.
 */

/**
 * A reader of the usage of the provider that gives a fixed answer and counts its calls
 */
final class FakeMailUsageReader implements ReadsMailUsage
{
    public int $calls = 0;

    public function __construct(public ?MailUsage $usage) {}

    public function read(): ?MailUsage
    {
        $this->calls++;

        return $this->usage;
    }
}

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    config([
        'mail.default' => 'array',
        'services.resend.key' => '',
        'mail.budget.monthly_quota' => 1000,
        'mail.budget.hold_percent' => 95,
        'mail.budget.alert_to' => ['ops@example.com', 'team@example.com'],
    ]);
    Mail::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function mailBudgetReader(?MailUsage $usage): FakeMailUsageReader
{
    $reader = new FakeMailUsageReader($usage);
    app()->instance(ReadsMailUsage::class, $reader);
    app()->forgetInstance(ChecksMailBudgets::class);

    return $reader;
}

/**
 * The mailer is the provider, with a key, so the check asks the reader
 */
function mailBudgetUseProvider(?MailUsage $usage): FakeMailUsageReader
{
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);

    return mailBudgetReader($usage);
}

function mailBudgetUsage(int $used, ?int $limit, ?string $resetsAt = '2026-12-01T00:00:00Z', ?MailUsagePeriod $daily = null): MailUsage
{
    return new MailUsage(new MailUsagePeriod($used, $limit, $resetsAt === null ? null : CarbonImmutable::parse($resetsAt)), $daily);
}

function mailBudgetSent(int $count, string $month = '2026-11-01'): void
{
    DB::table('mail_usage_months')->updateOrInsert(['month' => $month], ['cuid' => Str::lower(Str::random(24)), 'sent_count' => $count, 'created_at' => now(), 'updated_at' => now()]);
}

/**
 * @return object{sent_count: int, alert_80_sent_at: ?string, hold_notified_at: ?string, held_until: ?string}|null
 */
function mailBudgetRow(string $month = '2026-11-01'): ?object
{
    return DB::table('mail_usage_months')->where('month', $month)->first();
}

function mailBudgetCheck(): MailBudgetCheck
{
    return app(ChecksMailBudgets::class)->check();
}

test('without a quota there is no alert, no hold and no call to the provider (#1477)', function () {
    config(['mail.budget.monthly_quota' => 0]);
    $reader = mailBudgetReader(mailBudgetUsage(5000, null));
    mailBudgetSent(5000);
    Log::spy();

    $result = mailBudgetCheck();

    expect($result->quota)->toBeNull()
        ->and($result->percent)->toBeNull()
        ->and($result->held())->toBeFalse()
        ->and($reader->calls)->toBe(0)
        ->and(mailBudgetRow()->alert_80_sent_at)->toBeNull()
        ->and(mailBudgetRow()->held_until)->toBeNull();
    Mail::assertNothingQueued();
    Log::shouldNotHaveReceived('warning');
});

test('at 79% nothing happens (#1477)', function () {
    mailBudgetSent(799);
    Log::spy();

    $result = mailBudgetCheck();

    expect($result->percent)->toBe(79)
        ->and($result->source)->toBe('local')
        ->and(mailBudgetRow()->alert_80_sent_at)->toBeNull();
    Mail::assertNothingQueued();
    Log::shouldNotHaveReceived('warning');
});

test('at 80% one warning and one alert per address, on the queue default, once a month (#1477)', function () {
    mailBudgetSent(800);
    Log::spy();

    $result = mailBudgetCheck();

    expect($result->percent)->toBe(80)
        ->and($result->held())->toBeFalse()
        ->and(mailBudgetRow()->alert_80_sent_at)->not->toBeNull()
        ->and(mailBudgetRow()->held_until)->toBeNull();
    Mail::assertQueuedCount(2);
    foreach (['ops@example.com', 'team@example.com'] as $address) {
        Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->hasTo($address)
            && $mail->queue === null
            && ! $mail instanceof NotificationMail
            && $mail->heldUntil === null
            && $mail->used === 800 && $mail->quota === 1000 && $mail->percent === 80 && $mail->source === 'local');
    }
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => str_contains($message, '80%') && $context['used'] === 800 && $context['quota'] === 1000)->once();

    // The next hour, the same month: no second alert
    mailBudgetSent(900);
    mailBudgetCheck();
    Mail::assertQueuedCount(2);
});

test('without MAIL_ALERT_TO the log line is the alert (#1477)', function () {
    config(['mail.budget.alert_to' => []]);
    mailBudgetSent(850);
    Log::spy();

    mailBudgetCheck();

    Mail::assertNothingQueued();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, '80%'))->once();
});

test('an invalid address of MAIL_ALERT_TO gets no alert, the others do (#1477)', function () {
    config(['mail.budget.alert_to' => ['not an address', 'ops@example.com']]);
    mailBudgetSent(850);

    mailBudgetCheck();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->hasTo('ops@example.com'));
});

test('MAIL_ALERT_TO is read as a list separated by commas (#1477)', function () {
    config(['mail.budget.alert_to' => ' ops@example.com , ,team@example.com ']);
    mailBudgetSent(850);

    mailBudgetCheck();

    Mail::assertQueuedCount(2);
});

test('at 95% notification mail is held until the next month, with one alert (#1477)', function () {
    config(['mail.budget.alert_to' => ['ops@example.com']]);
    mailBudgetSent(950);

    $result = mailBudgetCheck();

    expect($result->held())->toBeTrue()
        ->and($result->heldUntil?->toIso8601String())->toBe('2026-12-01T00:00:00+00:00')
        ->and(mailBudgetRow()->held_until)->toBe('2026-12-01 00:00:00')
        ->and(mailBudgetRow()->alert_80_sent_at)->not->toBeNull()
        ->and(mailBudgetRow()->hold_notified_at)->not->toBeNull()
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();
    // One mail when both steps come in the same run: the one about the hold
    Mail::assertQueuedCount(1);
    Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->heldUntil?->toIso8601String() === '2026-12-01T00:00:00+00:00');

    mailBudgetSent(990);
    expect(mailBudgetCheck()->held())->toBeTrue();
    Mail::assertQueuedCount(1);
});

test('after the 80% alert, the hold sends its own alert (#1477)', function () {
    config(['mail.budget.alert_to' => ['ops@example.com']]);
    mailBudgetSent(800);
    mailBudgetCheck();
    mailBudgetSent(960);
    mailBudgetCheck();

    Mail::assertQueuedCount(2);
    Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->heldUntil === null);
    Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->heldUntil !== null);
});

test('with the hold percent 100 there is no reserve (#1477)', function () {
    config(['mail.budget.hold_percent' => 100]);
    mailBudgetSent(990);
    expect(mailBudgetCheck()->held())->toBeFalse();

    mailBudgetSent(1000);
    expect(mailBudgetCheck()->held())->toBeTrue();
});

test('the hold percent is kept between 1 and 100 (#1477)', function () {
    config(['mail.budget.hold_percent' => 400]);
    mailBudgetSent(990);
    expect(mailBudgetCheck()->held())->toBeFalse();

    config(['mail.budget.hold_percent' => -5]);
    mailBudgetSent(10);
    expect(mailBudgetCheck()->held())->toBeTrue();
});

test('the next month lifts the hold (#1477)', function () {
    mailBudgetSent(960);
    mailBudgetCheck();
    expect(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();

    Carbon::setTestNow(CarbonImmutable::parse('2026-12-01T00:00:00Z'));

    expect(app(ChecksMailBudgets::class)->isHeld())->toBeFalse();
    $result = mailBudgetCheck();
    expect($result->used)->toBe(0)
        ->and($result->held())->toBeFalse()
        ->and(mailBudgetRow('2026-12-01'))->not->toBeNull();
});

test('a raised quota ends the hold at the next check (#1477)', function () {
    mailBudgetSent(960);
    mailBudgetCheck();
    expect(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();

    config(['mail.budget.monthly_quota' => 2000]);
    $result = mailBudgetCheck();

    expect($result->held())->toBeFalse()
        ->and(mailBudgetRow()->held_until)->toBeNull()
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeFalse();
});

test('a failed read of the provider does not lift the hold that its numbers gave (#1477)', function () {
    // Other products send with the same team: the provider counts 980, this instance only 100
    $reader = mailBudgetUseProvider(mailBudgetUsage(980, 3000));
    mailBudgetSent(100);
    expect(mailBudgetCheck()->held())->toBeTrue();

    // A timeout: the count of this instance is far below the hold percent
    $reader->usage = null;
    $result = mailBudgetCheck();

    expect($result->source)->toBe('local')
        ->and($result->held())->toBeTrue()
        ->and(mailBudgetRow()->held_until)->toBe('2026-12-01 00:00:00')
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();

    // A read that works, with numbers below the hold percent, ends it
    $reader->usage = mailBudgetUsage(200, 3000);
    expect(mailBudgetCheck()->held())->toBeFalse()
        ->and(mailBudgetRow()->held_until)->toBeNull();
});

test('a hold that comes back in the same month is in the log, with no second mail (#1477)', function () {
    config(['mail.budget.alert_to' => ['ops@example.com']]);
    mailBudgetSent(960);
    mailBudgetCheck();
    config(['mail.budget.monthly_quota' => 2000]);
    expect(mailBudgetCheck()->held())->toBeFalse();
    Mail::assertQueuedCount(1);
    Log::spy();

    config(['mail.budget.monthly_quota' => 1000]);
    expect(mailBudgetCheck()->held())->toBeTrue();
    // The hold stays: the next run says nothing more
    mailBudgetCheck();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'again'))->once();
    Mail::assertQueuedCount(1);
});

test('the check ends a hold that a refusal set when the numbers are below the hold percent, and keeps it without a quota (#1477)', function () {
    mailBudgetSent(100);
    app(ChecksMailBudgets::class)->holdUntil(CarbonImmutable::parse('2026-12-01T00:00:00Z'), 'monthly_quota_exceeded');

    // No quota: the check does nothing, the hold of the refusal stays
    config(['mail.budget.monthly_quota' => 0]);
    expect(mailBudgetCheck()->held())->toBeTrue()
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();

    // A quota, and numbers that disagree with the provider: the hold ends (ADR 0054 point 13); the next refusal sets it again
    config(['mail.budget.monthly_quota' => 1000]);
    expect(mailBudgetCheck()->held())->toBeFalse()
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeFalse();
});

test('the count of the provider wins over the count of this instance (#1477)', function () {
    config(['mail.budget.monthly_quota' => 0]);
    $reader = mailBudgetUseProvider(mailBudgetUsage(2400, 3000));
    mailBudgetSent(10);

    $result = mailBudgetCheck();

    expect($reader->calls)->toBe(1)
        ->and($result->used)->toBe(2400)
        ->and($result->quota)->toBe(3000)
        ->and($result->percent)->toBe(80)
        ->and($result->source)->toBe('provider');
    Mail::assertQueued(MailBudgetAlert::class, fn (MailBudgetAlert $mail) => $mail->source === 'provider');
});

test('MAIL_MONTHLY_QUOTA wins over the limit of the provider (#1477)', function () {
    config(['mail.budget.monthly_quota' => 50000]);
    mailBudgetUseProvider(mailBudgetUsage(2900, 3000));

    $result = mailBudgetCheck();

    expect($result->quota)->toBe(50000)
        ->and($result->percent)->toBe(5)
        ->and($result->held())->toBeFalse();
    Mail::assertNothingQueued();
});

test('a hold of the provider lasts until its reset time (#1477)', function () {
    config(['mail.budget.monthly_quota' => 0]);
    mailBudgetUseProvider(mailBudgetUsage(2900, 3000, '2026-11-30T12:00:00Z'));

    expect(mailBudgetCheck()->heldUntil?->toIso8601String())->toBe('2026-11-30T12:00:00+00:00');
});

test('a reader that fails gives the count of this instance (#1477)', function () {
    mailBudgetUseProvider(null);
    mailBudgetSent(850);

    $result = mailBudgetCheck();

    expect($result->used)->toBe(850)
        ->and($result->source)->toBe('local');
    Mail::assertQueuedCount(2);
});

test('the daily limit of the provider holds notification mail until the next day, with a log line and no mail (#1477)', function () {
    config(['mail.budget.monthly_quota' => 0]);
    mailBudgetUseProvider(mailBudgetUsage(500, 3000, '2026-12-01T00:00:00Z', new MailUsagePeriod(96, 100, CarbonImmutable::parse('2026-11-19T00:00:00Z'))));
    Log::spy();

    $result = mailBudgetCheck();

    expect($result->held())->toBeTrue()
        ->and(mailBudgetRow()->held_until)->toBe('2026-11-19 00:00:00')
        ->and(mailBudgetRow()->hold_notified_at)->toBeNull();
    Mail::assertNothingQueued();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'daily limit'))->once();

    // Without a reset time: the next 00:00 UTC
    mailBudgetUseProvider(mailBudgetUsage(500, 3000, '2026-12-01T00:00:00Z', new MailUsagePeriod(96, 100, null)));
    expect(mailBudgetCheck()->heldUntil?->toIso8601String())->toBe('2026-11-19T00:00:00+00:00');

    // Below the hold percent the next day
    mailBudgetUseProvider(mailBudgetUsage(500, 3000, '2026-12-01T00:00:00Z', new MailUsagePeriod(10, 100, null)));
    expect(mailBudgetCheck()->held())->toBeFalse();
});

test('with the log and the array mailers the provider is never asked (#1477)', function (string $mailer) {
    config(['mail.default' => $mailer, 'services.resend.key' => 're_test_key']);
    $reader = mailBudgetReader(mailBudgetUsage(2900, 3000));
    mailBudgetSent(100);

    $result = mailBudgetCheck();

    expect($reader->calls)->toBe(0)
        ->and($result->source)->toBe('local')
        ->and($result->used)->toBe(100);
})->with(['log', 'array']);

test('the reader of the provider needs the mailer resend and a key, and never calls the network without them (#1477)', function () {
    config(['mail.default' => 'log', 'services.resend.key' => 're_test_key']);
    expect((new ResendMailUsage)->read())->toBeNull();

    config(['mail.default' => 'resend', 'services.resend.key' => '']);
    expect((new ResendMailUsage)->read())->toBeNull();
});

/**
 * A reader with an HTTP handler that gives these answers and keeps the requests: no call leaves the tests
 *
 * @param  list<Response|Throwable>  $answers
 * @param  array<int, array{request: RequestInterface, options: array<string, scalar|object|null>}>  $history
 */
function mailBudgetHttpReader(array $answers, array &$history = []): ResendMailUsage
{
    config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
    $stack = HandlerStack::create(new MockHandler($answers));
    $stack->push(Middleware::history($history));

    return new ResendMailUsage($stack);
}

test('the reader asks the provider with a timeout of 10 seconds and reads its answer (#1477)', function () {
    $history = [];
    $body = json_encode(['object' => 'usage', 'emails' => ['monthly' => ['used' => 2400, 'limit' => 3000, 'resets_at' => '2026-12-01T00:00:00.000Z'], 'daily' => ['used' => 40, 'limit' => 100, 'resets_at' => '2026-11-19T00:00:00.000Z']]]);
    $reader = mailBudgetHttpReader([new Response(200, ['Content-Type' => 'application/json'], (string) $body)], $history);

    $usage = $reader->read();

    expect($usage?->monthly->used)->toBe(2400)
        ->and($usage?->monthly->limit)->toBe(3000)
        ->and($usage?->daily?->used)->toBe(40)
        ->and($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/usage')
        ->and($history[0]['options']['timeout'])->toBe(10)
        ->and($history[0]['options']['connect_timeout'])->toBe(10);
});

test('every failure of the read gives null and one warning, with the error type and without the key (#1477)', function (Response|Throwable $answer, ?string $errorType) {
    Log::spy();
    $reader = mailBudgetHttpReader([$answer]);

    expect($reader->read())->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) use ($errorType) {
        return str_contains($message, 'could not be read')
            && ($context['error_type'] ?? null) === $errorType
            && ! str_contains((string) json_encode([$message, $context]), 're_test_key');
    })->once();
})->with([
    'a key without the right to read the usage' => [fn () => new Response(403, ['Content-Type' => 'application/json'], '{"statusCode":403,"name":"restricted_api_key","message":"This API key is restricted to only send emails"}'), 'restricted_api_key'],
    'an error of the provider' => [fn () => new Response(500, ['Content-Type' => 'application/json'], '{"statusCode":500,"name":"internal_server_error","message":"An unexpected error occurred"}'), 'internal_server_error'],
    'an answer that is not JSON' => [fn () => new Response(502, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>'), null],
    'a provider that does not answer in time' => [fn () => new ConnectException('cURL error 28: Operation timed out', new Request('GET', 'https://api.resend.com/usage')), null],
]);

test('an answer of the provider that is not as documented gives null and a warning (#1477)', function () {
    Log::spy();
    $reader = mailBudgetHttpReader([new Response(200, ['Content-Type' => 'application/json'], '{"object":"usage","emails":{"monthly":{"used":"many"}}}')]);

    expect($reader->read())->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'not as documented'))->once();
});

test('an empty or a wrong value of the environment gives the default, never a hold at 1% or no rate limit (#1477)', function (string $value, int $rate, int $holdPercent, ?int $quota) {
    $names = ['MAIL_RATE_LIMIT_PER_SECOND', 'MAIL_NOTIFICATION_HOLD_PERCENT', 'MAIL_MONTHLY_QUOTA'];
    $before = [];
    foreach ($names as $name) {
        $before[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    try {
        $mail = require config_path('mail.php');
        config(['mail' => $mail]);

        expect(MailSettings::ratePerSecond())->toBe($rate)
            ->and(MailSettings::holdPercent())->toBe($holdPercent)
            ->and(MailSettings::monthlyQuota())->toBe($quota);
    } finally {
        foreach ($before as $name => [$env, $server]) {
            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
    }
})->with([
    'an empty value' => ['', 5, 95, null],
    'a word' => ['five', 5, 95, null],
    'a number' => ['7', 7, 7, 7],
    'zero' => ['0', 0, 1, null],
    'below zero' => ['-3', 0, 1, null],
]);

test('the reader narrows the answer of the provider (#1477)', function () {
    $usage = ResendMailUsage::fromArray([
        'object' => 'usage',
        'emails' => [
            'monthly' => ['used' => 2400, 'limit' => 3000, 'sent' => 2390, 'received' => 10, 'resets_at' => '2026-12-01T00:00:00.000Z'],
            'daily' => ['used' => 40, 'limit' => 100, 'sent' => 40, 'received' => 0, 'resets_at' => '2026-11-19T00:00:00.000Z'],
        ],
    ]);

    expect($usage?->monthly->used)->toBe(2400)
        ->and($usage?->monthly->limit)->toBe(3000)
        ->and($usage?->monthly->resetsAt?->toIso8601String())->toBe('2026-12-01T00:00:00+00:00')
        ->and($usage?->daily?->used)->toBe(40)
        ->and($usage?->daily?->limit)->toBe(100);

    // A paid plan: no daily limit
    $paid = ResendMailUsage::fromArray(['emails' => ['monthly' => ['used' => 10, 'limit' => 50000, 'resets_at' => 'not a date'], 'daily' => ['used' => 3, 'limit' => null]]]);
    expect($paid?->monthly->resetsAt)->toBeNull()
        ->and($paid?->daily?->limit)->toBeNull();

    // Not as documented
    expect(ResendMailUsage::fromArray([]))->toBeNull()
        ->and(ResendMailUsage::fromArray(['emails' => ['monthly' => ['used' => '12']]]))->toBeNull()
        ->and(ResendMailUsage::fromArray(['emails' => 'none']))->toBeNull();
});

test('mail:check-budget prints the numbers (#1477)', function () {
    mailBudgetSent(960);
    config(['mail.budget.monthly_quota' => 0]);

    $this->artisan('mail:check-budget')
        ->expectsOutputToContain('Mail of the month: 960, quota: none, used: -, source: local, notification mail: not held')
        ->assertSuccessful();

    config(['mail.budget.monthly_quota' => 1000]);
    $this->artisan('mail:check-budget')
        ->expectsOutputToContain('Mail of the month: 960, quota: 1000, used: 96%, source: local, notification mail: held until 2026-12-01T00:00:00+00:00')
        ->assertSuccessful();
});

test('mail:check-budget runs every hour (#1477)', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => Str::contains((string) $event->command, 'mail:check-budget'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *')
        ->and($events->first()->withoutOverlapping)->toBeTrue()
        ->and($events->first()->onOneServer)->toBeTrue();
});

test('the alert shows its numbers in HTML and in plain text (#1477)', function () {
    $alert = new MailBudgetAlert(CarbonImmutable::parse('2026-11-01T00:00:00Z'), 2400, 3000, 80, 'local', 95, null);

    $alert->assertSeeInHtml('This month the platform sent 2 400 of 3 000 emails (80%).')
        ->assertSeeInHtml('Notification emails stop at 95%.')
        ->assertSeeInHtml('Sign-in links and other account emails keep going.')
        ->assertSeeInHtml('November 2026')
        ->assertSeeInHtml('The numbers come from the count of the emails that this instance sent.')
        ->assertSeeInText('This month the platform sent 2 400 of 3 000 emails (80%).')
        ->assertSeeInText('Notification emails stop at 95%.')
        ->assertSeeInText('Sign-in links and other account emails keep going.')
        ->assertDontSeeInText('paused');

    $held = new MailBudgetAlert(CarbonImmutable::parse('2026-11-01T00:00:00Z'), 2850, 3000, 95, 'provider', 95, CarbonImmutable::parse('2026-12-01T00:00:00Z'));

    $held->assertSeeInHtml('Notification emails are paused until 1 December 2026, 00:00 UTC.')
        ->assertSeeInHtml('Sign-in links and other account emails keep going.')
        ->assertSeeInHtml('The numbers come from your mail provider.')
        ->assertSeeInText('This month the platform sent 2 850 of 3 000 emails (95%).')
        ->assertSeeInText('Notification emails are paused until 1 December 2026, 00:00 UTC.')
        ->assertDontSeeInText('stop at');
});
