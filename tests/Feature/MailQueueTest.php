<?php

use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Mail\RecordsSentEmails;
use App\Jobs\Middleware\HideMailErrorText;
use App\Jobs\Middleware\HoldNotificationMail;
use App\Jobs\Middleware\SkipSuppressedNotificationMail;
use App\Mail\CommunityInvitation;
use App\Mail\CommunityOwnershipTransferred;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\EmailAddressHasAccount;
use App\Mail\FinishSignUp;
use App\Mail\MagicLink;
use App\Mail\MailBudgetAlert;
use App\Mail\NotificationMail;
use App\Mail\SignUpAccountExists;
use App\Support\MailWorkerActivity;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Fixtures\Mail\RefusingTransport;
use Tests\Fixtures\Mail\TestNotificationMail;

/*
 * The email delivery (#1477, ADR 0054): the queue `mail`, its rate limiter, the hold of the notification mail, the refusal
 * of the provider for a quota, the count of sent mail and the second worker. The jobs run through the `database` queue
 * and a real worker (`queue:work --once`), so a release, a retry and a failure are the ones of production.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.queue' => 'mail', 'mail.rate_limit_per_second' => 5]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Runs one job of the queue with a real worker, like the second worker of production (--tries=3)
 */
function mailQueueRunWorker(string $queue = 'mail'): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queue, '--sleep' => 0, '--tries' => 3]);
}

/**
 * The mail that the array transport holds
 */
function mailQueueSentCount(): int
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);

    return $transport instanceof ArrayTransport ? $transport->messages()->count() : 0;
}

/**
 * The jobs of a queue, oldest first
 *
 * @return list<object>
 */
function mailQueueJobs(string $queue = 'mail'): array
{
    return DB::table('jobs')->where('queue', $queue)->orderBy('id')->get()->all();
}

/**
 * A mailer whose transport refuses every mail, with this error type of the provider (null: a plain transport error)
 */
function mailQueueUseRefusingMailer(?string $errorType): void
{
    Mail::extend('refusing', static fn () => new RefusingTransport($errorType));
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    Mail::purge('refusing');
}

function mailQueueHoldUntil(?CarbonImmutable $until): void
{
    DB::table('mail_usage_months')->updateOrInsert(
        ['month' => '2026-11-01'],
        ['cuid' => 'mailqueuetestrow0000000a', 'sent_count' => 0, 'held_until' => $until, 'created_at' => now(), 'updated_at' => now()],
    );
}

function mailQueueHeldUntil(): ?string
{
    $value = DB::table('mail_usage_months')->where('month', '2026-11-01')->value('held_until');

    return is_string($value) ? $value : null;
}

// The base class

test('a notification mail goes on the queue mail (#1477)', function () {
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    expect(mailQueueJobs('mail'))->toHaveCount(1)
        ->and(mailQueueJobs('default'))->toHaveCount(0);
});

test('every way to queue a notification mail gives the queue mail, also when the caller names another queue (#1477)', function () {
    Mail::to('ada@example.com')->later(60, new TestNotificationMail('later'));
    // A mail that implements ShouldQueue is queued by send() too
    Mail::to('ada@example.com')->send(new TestNotificationMail('send'));
    Mail::to('ada@example.com')->queue((new TestNotificationMail('other queue'))->onQueue('default'));
    Mail::to('ada@example.com')->later(60, (new TestNotificationMail('other queue, later'))->onQueue('default'));

    expect(mailQueueJobs('mail'))->toHaveCount(4)
        ->and(mailQueueJobs('default'))->toHaveCount(0);
});

test('MAIL_QUEUE=default puts a notification mail on the queue default (#1477)', function () {
    config(['mail.queue' => 'default']);

    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    Mail::to('ada@example.com')->later(60, new TestNotificationMail);

    expect(mailQueueJobs('default'))->toHaveCount(2)
        ->and(mailQueueJobs('mail'))->toHaveCount(0);
});

test('an empty MAIL_QUEUE gives the queue mail (#1477)', function () {
    config(['mail.queue' => '']);

    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    expect(mailQueueJobs('mail'))->toHaveCount(1);
});

test('a notification mail has the hold, then the rate limiter, and the retry rules (#1477)', function () {
    $mail = new TestNotificationMail;
    $middleware = $mail->middleware();

    expect($middleware)->toHaveCount(4)
        ->and($middleware[0])->toBeInstanceOf(HideMailErrorText::class)
        ->and($middleware[1])->toBeInstanceOf(HoldNotificationMail::class)
        ->and($middleware[2])->toBeInstanceOf(SkipSuppressedNotificationMail::class)
        ->and($middleware[3])->toBeInstanceOf(RateLimited::class)
        ->and((fn () => $this->limiterName)->call($middleware[3]))->toBe('mail')
        ->and($mail->maxExceptions)->toBe(3)
        ->and($mail->backoff())->toBe([60, 300, 1800])
        ->and($mail->retryUntil()->equalTo(CarbonImmutable::parse('2026-11-18T12:00:00Z')))->toBeTrue();

    // The queued job carries them: the retry limit is a time, computed when the mail is queued
    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    $payload = json_decode((string) mailQueueJobs()[0]->payload, true);

    expect($payload['retryUntil'])->toBe(CarbonImmutable::parse('2026-11-18T12:00:00Z')->getTimestamp())
        ->and($payload['maxExceptions'])->toBe(3)
        ->and($payload['backoff'])->toBe('60,300,1800');
});

test('the retry limit of a delayed mail counts from the end of its delay, and the mail is sent when it is due (#1477)', function () {
    Mail::to('ada@example.com')->later(now()->addHours(3), new TestNotificationMail('by date'));
    Mail::to('ada@example.com')->later(3 * 3600, new TestNotificationMail('by seconds'));
    Mail::to('ada@example.com')->later(new DateInterval('PT3H'), new TestNotificationMail('by interval'));

    foreach (mailQueueJobs() as $job) {
        expect(json_decode((string) $job->payload, true)['retryUntil'])->toBe(CarbonImmutable::parse('2026-11-18T15:00:00Z')->getTimestamp())
            ->and((int) $job->available_at)->toBe(CarbonImmutable::parse('2026-11-18T13:00:00Z')->getTimestamp());
    }

    // Not due yet: the worker takes nothing
    mailQueueRunWorker();
    expect(mailQueueSentCount())->toBe(0);

    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T13:00:01Z'));
    foreach (range(1, 3) as $run) {
        mailQueueRunWorker();
    }

    expect(mailQueueSentCount())->toBe(3)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a mail that waited for more than 2 hours is not sent late: the worker fails it (#1477)', function () {
    Mail::to('ada@example.com')->queue(new TestNotificationMail('in time'));
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T11:59:00Z'));
    mailQueueRunWorker();
    expect(mailQueueSentCount())->toBe(1);

    // The mail worker did not run for more than 2 hours
    Mail::to('ada@example.com')->queue(new TestNotificationMail('too late'));
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T14:00:00Z'));
    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(1)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});

test('the account mailables have no limiter, no hold, and stay on the queue default (#1477)', function () {
    foreach ([MagicLink::class, ConfirmNewEmailAddress::class, FinishSignUp::class, SignUpAccountExists::class, EmailAddressHasAccount::class, EmailAddressChanged::class, CommunityInvitation::class, CommunityOwnershipTransferred::class, MailBudgetAlert::class] as $class) {
        // The only middleware of an account mail keeps the text of an error out of the log (HideMailErrorText)
        $middleware = array_map(static fn (object $item): string => $item::class, (new ReflectionClass($class))->newInstanceWithoutConstructor()->middleware());
        expect(is_subclass_of($class, NotificationMail::class))->toBeFalse()
            ->and($middleware)->toBe([HideMailErrorText::class])
            ->and(method_exists($class, 'retryUntil'))->toBeFalse()
            ->and((new ReflectionClass($class))->newInstanceWithoutConstructor()->queue)->toBeNull();
    }

    Mail::to('ada@example.com')->queue(new MagicLink('a-token'));

    $jobs = mailQueueJobs('default');
    expect($jobs)->toHaveCount(1)
        ->and(mailQueueJobs('mail'))->toHaveCount(0)
        ->and(json_decode((string) $jobs[0]->payload, true)['retryUntil'])->toBeNull();
});

// The rate limit

test('at 2 a second, the third mail of the same second is released and sent later, not failed (#1477)', function () {
    config(['mail.rate_limit_per_second' => 2]);
    foreach (['first', 'second', 'third'] as $text) {
        Mail::to('ada@example.com')->queue(new TestNotificationMail($text));
    }

    mailQueueRunWorker();
    mailQueueRunWorker();
    mailQueueRunWorker();

    $jobs = mailQueueJobs();
    expect(mailQueueSentCount())->toBe(2)
        ->and($jobs)->toHaveCount(1)
        ->and((int) $jobs[0]->attempts)->toBe(1)
        ->and((int) $jobs[0]->available_at)->toBeGreaterThan(now()->getTimestamp())
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    // The worker finds nothing to do before the job is available again
    mailQueueRunWorker();
    expect(mailQueueSentCount())->toBe(2);

    Carbon::setTestNow(now()->addSeconds(10));
    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(3)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('with the limit 0, nothing is released (#1477)', function () {
    config(['mail.rate_limit_per_second' => 0]);
    foreach (range(1, 6) as $number) {
        Mail::to('ada@example.com')->queue(new TestNotificationMail("mail {$number}"));
    }

    foreach (range(1, 6) as $number) {
        mailQueueRunWorker();
    }

    expect(mailQueueSentCount())->toBe(6)
        ->and(mailQueueJobs())->toHaveCount(0);
});

test('a value below 0 counts as no limit (#1477)', function () {
    config(['mail.rate_limit_per_second' => -3]);
    foreach (range(1, 6) as $number) {
        Mail::to('ada@example.com')->queue(new TestNotificationMail("mail {$number}"));
        mailQueueRunWorker();
    }

    expect(mailQueueSentCount())->toBe(6);
});

test('a mail released five times under --tries=3 is then sent once and is not failed (#1477)', function () {
    config(['mail.rate_limit_per_second' => 1]);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    foreach (range(1, 5) as $release) {
        // Another mail took the place of this second
        RateLimiter::hit(md5('mail'.'mail'), 1);
        mailQueueRunWorker();
        expect(mailQueueJobs())->toHaveCount(1);
        Carbon::setTestNow(now()->addSeconds(10));
    }

    expect((int) mailQueueJobs()[0]->attempts)->toBe(5)
        ->and(mailQueueSentCount())->toBe(0);

    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(1)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

// The hold

test('a held notification mail sends nothing and is not failed (#1477)', function () {
    mailQueueHoldUntil(CarbonImmutable::parse('2026-12-01T00:00:00Z'));
    Log::spy();
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(0)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'is not sent'))->once();
});

test('a notification mail is sent when the hold is over (#1477)', function () {
    mailQueueHoldUntil(CarbonImmutable::parse('2026-11-18T09:00:00Z'));
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(1);
});

test('an account mail is sent while notification mail is held (#1477)', function () {
    mailQueueHoldUntil(CarbonImmutable::parse('2026-12-01T00:00:00Z'));
    Mail::to('ada@example.com')->queue(new MagicLink('a-token'));

    mailQueueRunWorker('default');

    expect(mailQueueSentCount())->toBe(1);
});

// The refusal of the provider for a quota

test('a refusal for the monthly quota holds notification mail until the next month and ends the job (#1477)', function () {
    mailQueueUseRefusingMailer('monthly_quota_exceeded');
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(mailQueueHeldUntil())->toBe('2026-12-01 00:00:00')
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    // The next notification mail is held: with a mailer that works again, it is still not sent
    config(['mail.default' => 'array']);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    mailQueueRunWorker();
    expect(mailQueueSentCount())->toBe(0)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a refusal at the end of a year or of a month holds until the right day (#1477)', function (string $now, string $errorType, string $month, string $heldUntil) {
    Carbon::setTestNow(CarbonImmutable::parse($now));
    mailQueueUseRefusingMailer($errorType);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(DB::table('mail_usage_months')->where('month', $month)->value('held_until'))->toBe($heldUntil)
        ->and(app(ChecksMailBudgets::class)->isHeld())->toBeTrue();

    // The new month has a new row: the hold is over
    Carbon::setTestNow(CarbonImmutable::parse($heldUntil.'Z')->addSecond());
    expect(app(ChecksMailBudgets::class)->isHeld())->toBeFalse();
})->with([
    'the monthly quota on the last evening of the year' => ['2026-12-31T23:30:00Z', 'monthly_quota_exceeded', '2026-12-01', '2027-01-01 00:00:00'],
    'the daily quota on the last day of a month' => ['2026-11-30T12:00:00Z', 'daily_quota_exceeded', '2026-11-01', '2026-12-01 00:00:00'],
    'the daily quota on the last evening of the year' => ['2026-12-31T23:30:00Z', 'daily_quota_exceeded', '2026-12-01', '2027-01-01 00:00:00'],
]);

test('a refusal for the daily quota holds notification mail until the next day (#1477)', function () {
    mailQueueUseRefusingMailer('daily_quota_exceeded');
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(mailQueueHeldUntil())->toBe('2026-11-19 00:00:00')
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a later hold stays when a shorter one comes (#1477)', function () {
    $budget = app(ChecksMailBudgets::class);
    $budget->holdUntil(CarbonImmutable::parse('2026-12-01T00:00:00Z'), 'monthly_quota_exceeded');
    $budget->holdUntil(CarbonImmutable::parse('2026-11-19T00:00:00Z'), 'daily_quota_exceeded');

    expect(mailQueueHeldUntil())->toBe('2026-12-01 00:00:00')
        ->and($budget->isHeld())->toBeTrue();
});

test('another refusal of the provider and a plain transport error are retried and set no hold (#1477)', function (?string $errorType) {
    mailQueueUseRefusingMailer($errorType);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    $jobs = mailQueueJobs();
    expect(mailQueueHeldUntil())->toBeNull()
        ->and($jobs)->toHaveCount(1)
        ->and((int) $jobs[0]->attempts)->toBe(1)
        // The first backoff
        ->and((int) $jobs[0]->available_at)->toBe(now()->addSeconds(60)->getTimestamp())
        ->and(DB::table('failed_jobs')->count())->toBe(0);
})->with([
    'the rate limit of the provider' => ['rate_limit_exceeded'],
    'a plain transport error' => [null],
]);

test('a notification mail fails after 3 real exceptions (#1477)', function () {
    mailQueueUseRefusingMailer(null);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    foreach (range(1, 3) as $try) {
        mailQueueRunWorker();
        Carbon::setTestNow(now()->addMinutes(10));
    }

    expect(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});

// The activity of the mail worker (the check mail-queue of app:health reads it)

test('a job of the mail queue that ends notes the activity of the mail worker; a job of another queue does not (#1477)', function () {
    Mail::to('ada@example.com')->queue(new MagicLink('a-token'));
    mailQueueRunWorker('default');
    expect(MailWorkerActivity::lastAt())->toBeNull();

    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    mailQueueRunWorker();
    expect(MailWorkerActivity::lastAt()?->toIso8601String())->toBe('2026-11-18T10:00:00+00:00');

    // A released job ends too: the worker runs, the mail waits behind the limit
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T10:05:00Z'));
    config(['mail.rate_limit_per_second' => 1]);
    RateLimiter::hit(md5('mail'.'mail'), 1);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    mailQueueRunWorker();
    expect(mailQueueJobs())->toHaveCount(1)
        ->and(MailWorkerActivity::lastAt()?->toIso8601String())->toBe('2026-11-18T10:05:00+00:00');
});

test('a job that throws does not note the activity of the mail worker (#1477)', function () {
    mailQueueUseRefusingMailer(null);
    Mail::to('ada@example.com')->queue(new TestNotificationMail);

    mailQueueRunWorker();

    expect(MailWorkerActivity::lastAt())->toBeNull();
});

// The count of sent mail

test('a sent mail counts each recipient: to, cc and bcc (#1477)', function () {
    Mail::to('ada@example.com')->cc('bob@example.com')->bcc('cy@example.com')->sendNow(new MagicLink('a-token'));

    expect((int) DB::table('mail_usage_months')->where('month', '2026-11-01')->value('sent_count'))->toBe(3);
});

test('two sends give the sum, and a notification mail counts too (#1477)', function () {
    Mail::to('ada@example.com')->sendNow(new MagicLink('a-token'));
    Mail::to(['bob@example.com', 'cy@example.com'])->queue(new TestNotificationMail);
    mailQueueRunWorker();

    $rows = DB::table('mail_usage_months')->get();
    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->sent_count)->toBe(3)
        ->and($rows[0]->cuid)->toBeString()->not->toBe('');
});

test('a send that throws counts nothing (#1477)', function () {
    mailQueueUseRefusingMailer('monthly_quota_exceeded');

    expect(fn () => Mail::to('ada@example.com')->sendNow(new MagicLink('a-token')))->toThrow(TransportException::class);
    expect(DB::table('mail_usage_months')->count())->toBe(0);
});

test('a new month gets a new row (#1477)', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-30T23:59:59Z'));
    Mail::to('ada@example.com')->sendNow(new MagicLink('a-token'));
    Carbon::setTestNow(CarbonImmutable::parse('2026-12-01T00:00:00Z'));
    Mail::to('ada@example.com')->sendNow(new MagicLink('a-token'));
    Mail::to('bob@example.com')->sendNow(new MagicLink('a-token'));

    expect(DB::table('mail_usage_months')->orderBy('month')->pluck('sent_count', 'month')->map(fn ($count) => (int) $count)->all())
        ->toBe(['2026-11-01' => 1, '2026-12-01' => 2]);
});

test('a recorder that fails never breaks the send (#1477)', function () {
    app()->instance(RecordsSentEmails::class, new class implements RecordsSentEmails
    {
        public function record(int $recipients): void
        {
            throw new RuntimeException('the database is down');
        }
    });
    Log::spy();

    Mail::to('ada@example.com')->sendNow(new MagicLink('a-token'));
    Mail::to('ada@example.com')->queue(new TestNotificationMail);
    mailQueueRunWorker();

    expect(mailQueueSentCount())->toBe(2)
        ->and(mailQueueJobs())->toHaveCount(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'count of sent mail failed'))->twice();
});

// The worker commands

test('every file that runs a worker runs one for the mail queue, and the first worker never reads it (#1477)', function () {
    foreach (['docker/8.5/supervisord.conf', 'compose.production.yaml', 'SELF_HOSTING.md', 'CONTRIBUTING.md'] as $file) {
        $text = (string) file_get_contents(base_path($file));

        expect($text)->toContain('--queue=broadcasts,notifications,default')
            ->and($text)->toContain('--queue=mail');

        foreach (explode("\n", $text) as $number => $line) {
            expect(str_contains($line, 'broadcasts') && str_contains($line, 'mail'))->toBeFalse("{$file} line ".($number + 1).' names both queues');
        }
    }
});
