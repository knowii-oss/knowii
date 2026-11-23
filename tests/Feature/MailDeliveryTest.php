<?php

use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiEmailSuppressionReason;
use App\Enums\KnowiiMailDeliveryStatus;
use App\Enums\KnowiiMailKind;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Jobs\Middleware\HideMailErrorText;
use App\Jobs\Middleware\HoldNotificationMail;
use App\Jobs\Middleware\SkipSuppressedNotificationMail;
use App\Mail\CommunityInvitation;
use App\Mail\CommunityOwnershipTransferred;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\EmailAddressChanged;
use App\Mail\MagicLink;
use App\Mail\MailBudgetAlert;
use App\Mail\NotificationMail;
use App\Models\EmailSuppression;
use App\Models\MailDelivery;
use App\Models\Notification;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Support\MailKinds;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Resend\Client as ResendClient;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Mail\RefusingTransport;
use Tests\Fixtures\Mail\TestNotificationMail;

/*
 * The delivery log (#1478, ADR 0054): a row for each mail that the platform hands to the mailer, the link to the id of the
 * mail provider, and a notification mail that goes out at most once. The jobs of the notification mail run through the
 * `database` queue and a real worker, like production.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    config(['mail.default' => 'array', 'mail.queue' => 'mail', 'mail.rate_limit_per_second' => 0]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return list<Email>
 */
function mdSent(): array
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $messages = [];
    if ($transport instanceof ArrayTransport) {
        foreach ($transport->messages() as $message) {
            $original = $message->getOriginalMessage();
            if ($original instanceof Email) {
                $messages[] = $original;
            }
        }
    }

    return $messages;
}

function mdHeader(Email $message, string $name): ?string
{
    return $message->getHeaders()->get($name)?->getBodyAsString();
}

function mdRunWorker(string $queue = 'mail'): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queue, '--sleep' => 0, '--tries' => 3]);
}

/**
 * A Resend transport whose client answers with this id, without the network
 */
function mdResendTransport(string $emailId): ResendTransport
{
    $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['id' => $emailId]))]));

    return new ResendTransport(new ResendClient(new HttpTransporter(
        new GuzzleClient(['handler' => $stack]),
        BaseUri::from('api.resend.example'),
        Headers::withAuthorization(ApiKey::from('re_test_key')),
    )));
}

// The map of the kinds

test('every mail class of the application has a kind in the one map (#1478)', function () {
    $classes = [ResetPassword::class];
    foreach (['Mail', 'Notifications'] as $folder) {
        foreach (glob(dirname(__DIR__, 2).'/app/'.$folder.'/*.php') ?: [] as $file) {
            $classes[] = 'App\\'.$folder.'\\'.basename($file, '.php');
        }
    }

    $missing = [];
    foreach ($classes as $class) {
        $named = array_key_exists($class, MailKinds::CLASSES) || is_subclass_of($class, NotificationMail::class);
        if (! $named) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([])
        ->and(count($classes))->toBeGreaterThan(7);
});

test('the kinds of the mails (#1478)', function () {
    expect(MailKinds::of(MagicLink::class))->toBe(KnowiiMailKind::AskedFor)
        ->and(MailKinds::of(ConfirmNewEmailAddress::class))->toBe(KnowiiMailKind::AskedFor)
        ->and(MailKinds::of(VerifyEmailAddress::class))->toBe(KnowiiMailKind::AskedFor)
        ->and(MailKinds::of(ResetPassword::class))->toBe(KnowiiMailKind::AskedFor)
        ->and(MailKinds::of(CommunityInvitation::class))->toBe(KnowiiMailKind::Invitation)
        ->and(MailKinds::of(CommunityOwnershipTransferred::class))->toBe(KnowiiMailKind::Account)
        ->and(MailKinds::of(EmailAddressChanged::class))->toBe(KnowiiMailKind::Account)
        ->and(MailKinds::of(MailBudgetAlert::class))->toBe(KnowiiMailKind::Operator)
        ->and(MailKinds::of(TestNotificationMail::class))->toBeNull();
});

// A row for each mail

test('the sign-in link gets a row of its account, kind asked_for, and the header that names it (#1478)', function () {
    $ada = User::factory()->create(['email' => 'ada@example.com']);

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    $row = MailDelivery::query()->sole();
    expect($row->kind)->toBe(KnowiiMailKind::AskedFor)
        ->and($row->user_id)->toBe($ada->id)
        ->and($row->community_invitation_id)->toBeNull()
        ->and($row->status)->toBe(KnowiiMailDeliveryStatus::Sent)
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->provider_email_id)->toBeNull()
        ->and(mdHeader(mdSent()[0], 'X-Knowii-Delivery'))->toBe($row->cuid);
});

test('a single invitation gets a row of its invitation, never of an account (#1478)', function () {
    $c = communityWithMembers();
    User::factory()->create(['email' => 'grace@example.com']);

    $invitation = app(InvitesCommunityMembers::class)->invite($c['owner'], $c['community'], ['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member->value]);

    $row = MailDelivery::query()->sole();
    expect($row->kind)->toBe(KnowiiMailKind::Invitation)
        ->and($row->community_invitation_id)->toBe($invitation->id)
        ->and($row->user_id)->toBeNull()
        ->and(mdHeader(mdSent()[0], 'X-Knowii-Delivery'))->toBe($row->cuid);
});

test('the mail of a new owner gets a row of its account, kind account (#1478)', function () {
    $c = communityWithMembers();

    app(TransfersCommunityOwnership::class)->transfer($c['owner'], $c['community'], ['member' => $c['admin']->cuid, 'password' => 'password']);

    $row = MailDelivery::query()->sole();
    expect($row->kind)->toBe(KnowiiMailKind::Account)
        ->and($row->user_id)->toBe($c['admin']->id);
});

test('a change of the address: the confirmation is asked_for, the notice to the address of before is account, both of the account (#1478)', function () {
    $ada = User::factory()->create(['email' => 'ada@example.com']);

    app(ChangesEmailAddresses::class)->request($ada, 'ada.new@example.com');
    $confirm = MailDelivery::query()->sole();
    expect($confirm->kind)->toBe(KnowiiMailKind::AskedFor)
        ->and($confirm->user_id)->toBe($ada->id);

    expect(mdHeader(mdSent()[0], 'X-Knowii-Delivery'))->toBe($confirm->cuid);

    preg_match('#/user/email-change/[^/]+/([a-f0-9]+)#', (string) mdSent()[0]->getHtmlBody(), $matches);
    expect($matches)->toHaveKey(1);
    app(ChangesEmailAddresses::class)->confirm($ada->refresh(), $matches[1]);

    $notice = MailDelivery::query()->where('kind', KnowiiMailKind::Account->value)->sole();
    expect($notice->user_id)->toBe($ada->id);
});

test('the alert of the operator gets a row without an account, kind operator (#1478)', function () {
    config(['mail.budget.monthly_quota' => 100, 'mail.budget.alert_to' => ['ops@example.com']]);
    DB::table('mail_usage_months')->insert(['cuid' => 'mailbudgetrowfortestsaaa', 'month' => '2026-11-01', 'sent_count' => 85, 'created_at' => now(), 'updated_at' => now()]);

    app(ChecksMailBudgets::class)->check();

    $row = MailDelivery::query()->sole();
    expect($row->kind)->toBe(KnowiiMailKind::Operator)
        ->and($row->user_id)->toBeNull()
        ->and($row->community_invitation_id)->toBeNull();
});

test('the address check and the password reset of the framework get no row (#1478)', function () {
    $ada = User::factory()->unverified()->create(['email' => 'ada@example.com']);

    $ada->sendEmailVerificationNotification();
    Password::broker()->sendResetLink(['email' => 'ada@example.com']);

    expect(mdSent())->toHaveCount(2)
        ->and(MailDelivery::query()->count())->toBe(0)
        ->and(mdHeader(mdSent()[0], 'X-Knowii-Delivery'))->toBeNull();
});

test('a row cannot belong to an account and to an invitation (#1478)', function () {
    $c = communityWithMembers();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => 'member']);

    expect(fn () => MailDelivery::query()->create(['kind' => KnowiiMailKind::Invitation, 'user_id' => $c['member']->id, 'community_invitation_id' => $invitation->id, 'status' => KnowiiMailDeliveryStatus::Queued]))
        ->toThrow(QueryException::class);
});

// The id of the provider

test('a send through the Resend transport fills the id of the provider (#1478)', function () {
    Mail::extend('resend', static fn () => mdResendTransport('em-provider-1'));
    config(['mail.default' => 'resend', 'mail.mailers.resend' => ['transport' => 'resend'], 'services.resend.key' => 're_test_key']);
    Mail::purge('resend');
    User::factory()->create(['email' => 'ada@example.com']);

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    $row = MailDelivery::query()->sole();
    expect($row->provider_email_id)->toBe('em-provider-1')
        ->and($row->status)->toBe(KnowiiMailDeliveryStatus::Sent);
});

test('a send with the array transport leaves the id of the provider null and fails nothing (#1478)', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    expect(MailDelivery::query()->sole()->provider_email_id)->toBeNull()
        ->and(mdSent())->toHaveCount(1);
});

test('the listener never throws: a header that names no row changes nothing (#1478)', function () {
    Mail::html('<p>x</p>', static function ($message) {
        $message->to('ada@example.com')->subject('x');
        $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-Knowii-Delivery', 'nosuchrowaaaaaaaaaaaaaaaa');
    });

    expect(mdSent())->toHaveCount(1)
        ->and(MailDelivery::query()->count())->toBe(0);
});

// A notification mail goes out at most once

test('a notification mail hides the text of an error, then has the hold, the skip of a suppressed address or a stopped group, and the rate limiter (#1478)', function () {
    $middleware = (new TestNotificationMail)->middleware();

    expect($middleware)->toHaveCount(4)
        ->and($middleware[0])->toBeInstanceOf(HideMailErrorText::class)
        ->and($middleware[1])->toBeInstanceOf(HoldNotificationMail::class)
        ->and($middleware[2])->toBeInstanceOf(SkipSuppressedNotificationMail::class)
        ->and($middleware[3])->toBeInstanceOf(RateLimited::class);
});

test('a notification mail is queued with a row queued, of its account and its group, and the worker sends it once (#1478)', function () {
    config(['queue.default' => 'database']);
    $ada = User::factory()->create(['email' => 'ada@example.com']);

    Mail::to($ada)->queue(new TestNotificationMail('Hello', $ada, KnowiiNotificationEmailGroup::Events));

    $row = MailDelivery::query()->sole();
    expect($row->status)->toBe(KnowiiMailDeliveryStatus::Queued)
        ->and($row->kind)->toBe(KnowiiMailKind::Events)
        ->and($row->user_id)->toBe($ada->id);

    mdRunWorker();

    expect(mdSent())->toHaveCount(1)
        ->and($row->refresh()->status)->toBe(KnowiiMailDeliveryStatus::Sent)
        ->and(mdHeader(mdSent()[0], 'X-Knowii-Delivery'))->toBe($row->cuid);
});

test('the second run of the same job sends nothing; a run after failed sends once (#1478)', function () {
    config(['queue.default' => 'database']);
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    Mail::to($ada)->queue(new TestNotificationMail('Hello', $ada));
    $job = (array) DB::table('jobs')->sole();
    unset($job['id']);

    mdRunWorker();
    expect(mdSent())->toHaveCount(1);

    // The same job again (a crash after the send, before the job ended)
    DB::table('jobs')->insert($job);
    mdRunWorker();
    expect(mdSent())->toHaveCount(1)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    // A row that failed can be claimed again
    MailDelivery::query()->update(['status' => KnowiiMailDeliveryStatus::Failed->value]);
    DB::table('jobs')->insert($job);
    mdRunWorker();
    expect(mdSent())->toHaveCount(2)
        ->and(MailDelivery::query()->sole()->status)->toBe(KnowiiMailDeliveryStatus::Sent);
});

test('an error of the transport sets the row failed, and the retry sends it (#1478)', function () {
    config(['queue.default' => 'database']);
    Mail::extend('refusing', static fn () => new RefusingTransport(null));
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    Mail::purge('refusing');
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    Mail::to($ada)->queue(new TestNotificationMail('Hello', $ada));

    mdRunWorker();

    expect(MailDelivery::query()->sole()->status)->toBe(KnowiiMailDeliveryStatus::Failed);

    // The job keeps the name of its mailer: the same mailer works again
    Mail::extend('refusing', static fn () => Mail::mailer('array')->getSymfonyTransport());
    Mail::purge('refusing');
    Carbon::setTestNow(now()->addMinutes(2));
    mdRunWorker();

    expect(mdSent())->toHaveCount(1)
        ->and(MailDelivery::query()->sole()->status)->toBe(KnowiiMailDeliveryStatus::Sent);
});

test('a held notification mail sets its row held (#1478)', function () {
    config(['queue.default' => 'database']);
    DB::table('mail_usage_months')->insert(['cuid' => 'mailbudgetrowfortestsaab', 'month' => '2026-11-01', 'sent_count' => 0, 'held_until' => '2026-12-01 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    Mail::to($ada)->queue(new TestNotificationMail('Hello', $ada));

    mdRunWorker();

    expect(mdSent())->toHaveCount(0)
        ->and(MailDelivery::query()->sole()->status)->toBe(KnowiiMailDeliveryStatus::Held);
});

test('a notification mail to a suppressed address is not sent: the job ends as done, with the row skipped (#1478)', function () {
    config(['queue.default' => 'database']);
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    EmailSuppression::query()->create(['user_id' => $ada->id, 'email' => 'ada@example.com', 'reason' => KnowiiEmailSuppressionReason::HardBounce, 'suppressed_at' => now(), 'last_event_at' => now()]);
    Mail::to($ada)->queue(new TestNotificationMail('Hello', $ada));

    mdRunWorker();

    expect(mdSent())->toHaveCount(0)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(MailDelivery::query()->sole()->status)->toBe(KnowiiMailDeliveryStatus::Skipped);
});

test('a notification mail of a group that the member stopped is not sent either (#1478)', function () {
    config(['queue.default' => 'database']);
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    NotificationEmailChoice::query()->create(['user_id' => $ada->id, 'group' => KnowiiNotificationEmailGroup::Events, 'state' => NotificationEmailChoice::STATE_OFF]);
    Mail::to($ada)->queue(new TestNotificationMail('Stopped', $ada, KnowiiNotificationEmailGroup::Events));
    Mail::to($ada)->queue(new TestNotificationMail('Still on', $ada, KnowiiNotificationEmailGroup::DirectMessages));

    mdRunWorker();
    mdRunWorker();

    expect(mdSent())->toHaveCount(1)
        ->and(mdSent()[0]->getSubject())->toBe('Still on')
        ->and(MailDelivery::query()->where('kind', KnowiiMailKind::Events->value)->sole()->status)->toBe(KnowiiMailDeliveryStatus::Skipped);
});

test('a notification mail of a notification goes to the queue once (#1478)', function () {
    config(['queue.default' => 'database']);
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    $notification = Notification::factory()->create(['user_id' => $ada->id])->id;

    Mail::to($ada)->queue((new TestNotificationMail('Hello', $ada))->forNotification($notification));
    Mail::to($ada)->queue((new TestNotificationMail('Hello', $ada))->forNotification($notification));

    expect(MailDelivery::query()->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(1);
});

test('an account deletion removes its delivery log (#1478)', function () {
    $ada = User::factory()->create(['email' => 'ada@example.com']);
    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    expect(MailDelivery::query()->count())->toBe(1);

    $ada->delete();

    expect(MailDelivery::query()->count())->toBe(0);
});
