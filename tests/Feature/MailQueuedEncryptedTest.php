<?php

use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\Mail\ChecksMailBudgets;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Jobs\Middleware\HideMailErrorText;
use App\Jobs\SendInvitationImportMail;
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
use App\Models\Community;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Support\MailBudgetCheck;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\Mail\RecipientNamingTransport;
use Tests\Fixtures\Mail\TestNotificationMail;
use Tests\Fixtures\MailSearch\Deep\Nested\NestedMailable;
use Tests\Fixtures\MailSearch\Elsewhere\NestedMailNotification;

/*
 * Every mail of the application goes through the queue, encrypted, and a failed mail keeps no address (ADR 0064 point 11,
 * ADR 0069): the row of `jobs` holds no address in clear, and when the mail provider refuses the mail with an error text
 * that names the recipient, neither `failed_jobs` nor the log gets that text. The jobs run through the `database` queue
 * and a real worker (`queue:work --once`).
 *
 * A new mail class is covered, in any folder of `app/`: the first test finds it, and the data set of the second one fails
 * for a class without a case in mailQueuedCase(). A mail sent with no class (Mail::raw(), Mail::send() with a view) has no
 * place for these rules, so no file of `app/` sends one.
 */

const MAIL_QUEUED_ADDRESS = 'reader.secret@example.com';

/**
 * The class that stands for an abstract mail class in these tests
 */
const MAIL_QUEUED_FIXTURES = [NotificationMail::class => TestNotificationMail::class];

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-24T10:00:00Z'));
    config(['mail.default' => 'array', 'mail.queue' => 'mail', 'mail.rate_limit_per_second' => 0]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Every mail class of the application, also the abstract ones: applicationMailClasses(), in any folder of `app/`
 *
 * @return list<class-string>
 */
function mailQueuedClasses(): array
{
    return applicationMailClasses();
}

/**
 * Queues one mail of the class to MAIL_QUEUED_ADDRESS, as the application queues it
 *
 * @return Closure(): mixed
 */
function mailQueuedCase(string $class): Closure
{
    $queue = static fn (Mailable $mail): Closure => static fn () => Mail::to(MAIL_QUEUED_ADDRESS)->queue($mail);
    $account = static fn (): User => User::factory()->withUserProfile()->create(['email' => MAIL_QUEUED_ADDRESS]);

    switch ($class) {
        case CommunityInvitation::class:
            $owner = User::factory()->withUserProfile()->create();
            $invitation = Community::factory()->create(['owner_id' => $owner->id])->communityInvitations()->create(['email' => MAIL_QUEUED_ADDRESS, 'role' => 'member']);

            return $queue(new CommunityInvitation($invitation, $owner));
        case CommunityOwnershipTransferred::class:
            $former = User::factory()->withUserProfile()->create();

            return $queue(new CommunityOwnershipTransferred(Community::factory()->create(['owner_id' => $account()->id]), $former));
        case ConfirmNewEmailAddress::class:
            return $queue(new ConfirmNewEmailAddress('https://knowii.test/email/change/1/abc?expires=1795600000&signature=0123abcd', 60));
        case EmailAddressChanged::class:
            return $queue(new EmailAddressChanged('r***@example.com'));
        case EmailAddressHasAccount::class:
            return $queue(new EmailAddressHasAccount);
        case FinishSignUp::class:
            return $queue(new FinishSignUp(str_repeat('a1', 32), 60));
        case MagicLink::class:
            return $queue(new MagicLink(str_repeat('b2', 32)));
        case MailBudgetAlert::class:
            return $queue(new MailBudgetAlert(CarbonImmutable::parse('2026-11-01', 'UTC'), 2400, 3000, 80, MailBudgetCheck::SOURCE_PROVIDER, 90, null));
        case SignUpAccountExists::class:
            return $queue(new SignUpAccountExists);
        case TestNotificationMail::class:
            return $queue(new TestNotificationMail('A test notification', $account()));
        case ResetPasswordLink::class:
            $user = $account();

            return static fn () => $user->notify(new ResetPasswordLink(str_repeat('c3', 32)));
        case VerifyEmailAddress::class:
            $user = User::factory()->unverified()->withUserProfile()->create(['email' => MAIL_QUEUED_ADDRESS]);

            return static fn () => $user->notify(new VerifyEmailAddress);
        default:
            throw new LogicException("The mail class {$class} has no case in mailQueuedCase(): add one");
    }
}

/**
 * From now on the mail provider refuses every mail, with an error text that names the recipient
 */
function mailQueuedRefuseEveryMail(): void
{
    Mail::extend('recipient-naming', static fn () => new RecipientNamingTransport);
    config(['mail.mailers.recipient-naming' => ['transport' => 'recipient-naming'], 'mail.default' => 'recipient-naming']);
    Mail::purge('recipient-naming');
}

/**
 * The one mail job of `jobs`: its row as the database stores it, and its command as the worker reads it
 *
 * @return array{raw: string, queue: string, command: object}
 */
function mailQueuedJob(): array
{
    $row = DB::table('jobs')->where(static fn ($query) => $query->where('payload', 'like', '%SendQueuedMailable%')->orWhere('payload', 'like', '%SendQueuedNotifications%'))->sole();
    $command = (string) json_decode((string) $row->payload, true)['data']['command'];
    // A command that is not encrypted is a serialized object: it starts with "O:"
    expect(str_starts_with($command, 'O:'))->toBeFalse();
    $job = unserialize((string) app('encrypter')->decrypt($command));
    expect($job)->toBeObject();

    return ['raw' => (string) json_encode($row, JSON_UNESCAPED_SLASHES).stripslashes((string) $row->payload), 'queue' => (string) $row->queue, 'command' => $job];
}

/**
 * Everything that the application logs from now on: each message with its context, an error with its whole text
 */
function mailQueuedCaptureLogs(): ArrayObject
{
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($logged) {
        $context = array_map(static fn (mixed $value): string => $value instanceof Throwable ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES), $event->context);
        $logged[] = $event->message.' '.implode(' ', $context);
    });

    return $logged;
}

/**
 * Runs the one mail job of `jobs` with a real worker: the other jobs of the test (a live update) are not part of it
 */
function mailQueuedRunMailJob(string $queue = 'default'): void
{
    DB::table('jobs')->where('payload', 'not like', '%SendQueuedMailable%')->where('payload', 'not like', '%SendQueuedNotifications%')->delete();
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queue, '--sleep' => 0, '--tries' => 1]);
}

function mailQueuedSentCount(): int
{
    $transport = Mail::mailer('array')->getSymfonyTransport();

    return $transport instanceof ArrayTransport ? $transport->messages()->count() : 0;
}

// ---------------------------------------------------------------------------------------------------------------------
// Every class
// ---------------------------------------------------------------------------------------------------------------------

test('every mail class goes through the queue, is encrypted there, and hides the text of an error of the send', function () {
    $classes = mailQueuedClasses();
    expect($classes)->toContain(CommunityInvitation::class, EmailAddressChanged::class, MailBudgetAlert::class, NotificationMail::class, VerifyEmailAddress::class);

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->implementsInterface(ShouldQueue::class))->toBeTrue("{$class} does not implement ShouldQueue")
            ->and($reflection->implementsInterface(ShouldBeEncrypted::class))->toBeTrue("{$class} does not implement ShouldBeEncrypted")
            ->and($reflection->hasMethod('middleware'))->toBeTrue("{$class} has no middleware()");

        if ($reflection->isAbstract()) {
            expect(array_key_exists($class, MAIL_QUEUED_FIXTURES))->toBeTrue("The abstract mail class {$class} needs a class in MAIL_QUEUED_FIXTURES");
            $reflection = new ReflectionClass(MAIL_QUEUED_FIXTURES[$class]);
            expect($reflection->isSubclassOf($class))->toBeTrue();
        }
        $mail = $reflection->newInstanceWithoutConstructor();
        $middleware = $mail instanceof Mailable ? $mail->middleware() : $mail->middleware(new User, 'mail');

        // The first one: it is outside the others, so no other middleware sees or logs the text of the error
        expect($middleware[0] ?? null)->toBeInstanceOf(HideMailErrorText::class, "{$class} does not hide the text of an error of the send");
    }

    // The one job that sends a mail itself (the mail of a row of an import of invitations) holds no address, and hides
    // the text of an error too
    $job = new SendInvitationImportMail(1, 1);
    expect($job->middleware()[0])->toBeInstanceOf(HideMailErrorText::class)
        ->and(serialize($job))->not->toContain('@');
});

test('the search finds a mail class in any folder, and a mail sent without a class', function () {
    $fixtures = dirname(__DIR__).'/Fixtures/MailSearch';

    expect(applicationMailClasses($fixtures, 'Tests\\Fixtures\\MailSearch\\'))->toBe([NestedMailable::class, NestedMailNotification::class])
        ->and(applicationMailSendsWithoutAClass($fixtures))->toBe(['Sends/AnonymousMailable.php', 'Sends/HtmlThroughAMailer.php', 'Sends/RawText.php', 'Sends/ViewAndClosure.php']);
});

test('the application sends no mail without a class', function () {
    expect(mailQueuedClasses())->toContain(CommunityInvitation::class, NotificationMail::class, ResetPasswordLink::class, VerifyEmailAddress::class)
        ->and(applicationMailSendsWithoutAClass())->toBe([]);
});

test('the row of a queued mail holds no address, and a mail that the provider refuses leaves no address behind', function (string $class) {
    $queueIt = mailQueuedCase($class);
    mailQueuedRefuseEveryMail();
    config(['queue.default' => 'database']);
    $logged = mailQueuedCaptureLogs();

    // Nothing is sent when the application queues the mail: a send would throw here
    $queueIt();

    $queued = mailQueuedJob();
    $job = $queued['command'];
    expect($job instanceof SendQueuedMailable ? $job->mailable : ($job instanceof SendQueuedNotifications ? $job->notification : null))->toBeInstanceOf($class)
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(stripos($queued['raw'], 'reader.secret'))->toBeFalse()
        ->and(stripos($queued['raw'], rawurlencode(MAIL_QUEUED_ADDRESS)))->toBeFalse();

    // The worker tries the mail until it fails: a notification mail has three tries, with a wait between them
    foreach (range(1, 3) as $try) {
        mailQueuedRunMailJob($queued['queue']);
        Carbon::setTestNow(now()->addMinutes(31));
    }

    $failed = DB::table('failed_jobs')->get();
    expect($failed)->toHaveCount(1);
    $left = (string) json_encode($failed[0], JSON_UNESCAPED_SLASHES).stripslashes((string) $failed[0]->payload).implode("\n", $logged->getArrayCopy());
    expect((string) $failed[0]->exception)->toContain('The mail could not be sent (Symfony\Component\Mailer\Exception\TransportException)')
        ->and(implode("\n", $logged->getArrayCopy()))->toContain('The mail could not be sent')
        ->and(stripos($left, MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(stripos($left, 'reader.secret'))->toBeFalse()
        ->and(stripos($left, 'Recipient address rejected'))->toBeFalse();
})->with(fn () => array_map(
    static fn (string $class): array => [MAIL_QUEUED_FIXTURES[$class] ?? $class],
    array_combine(array_map(static fn (string $class): string => class_basename($class), mailQueuedClasses()), mailQueuedClasses()),
));

test('a mailable that is sent with send() goes to the queue too', function () {
    config(['queue.default' => 'database']);

    Mail::to(MAIL_QUEUED_ADDRESS)->send(new EmailAddressChanged('r***@example.com'));

    expect(mailQueuedSentCount())->toBe(0)
        ->and(mailQueuedJob()['command'])->toBeInstanceOf(SendQueuedMailable::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// The mails as the application sends them
// ---------------------------------------------------------------------------------------------------------------------

test('the mail of an invitation waits in the queue with no address in its row', function () {
    $c = communityWithMembers();
    config(['queue.default' => 'database']);

    app(InvitesCommunityMembers::class)->invite($c['owner'], $c['community'], ['email' => MAIL_QUEUED_ADDRESS, 'role' => 'member']);

    $queued = mailQueuedJob();
    expect($queued['command'] instanceof SendQueuedMailable ? $queued['command']->mailable : null)->toBeInstanceOf(CommunityInvitation::class)
        ->and($queued['queue'])->toBe('default')
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(mailQueuedSentCount())->toBe(0);

    mailQueuedRunMailJob();
    expect(mailQueuedSentCount())->toBe(1)->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('the mail of a new owner waits in the queue with no address in its row, and its To header holds the address only', function () {
    $c = communityWithMembers();
    $c['owner']->forceFill(['password' => Hash::make('a-Long-Password-9!')])->save();
    $c['admin']->forceFill(['email' => MAIL_QUEUED_ADDRESS, 'name' => "Ada\r\nLovelace"])->save();
    config(['queue.default' => 'database']);

    app(TransfersCommunityOwnership::class)->transfer($c['owner'], $c['community'], ['member' => $c['admin']->cuid, 'password' => 'a-Long-Password-9!']);

    $queued = mailQueuedJob();
    expect($queued['command'] instanceof SendQueuedMailable ? $queued['command']->mailable : null)->toBeInstanceOf(CommunityOwnershipTransferred::class)
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse();

    mailQueuedRunMailJob();
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $message = $transport instanceof ArrayTransport ? $transport->messages()->last()?->getOriginalMessage() : null;
    expect($message?->getHeaders()->get('To')?->getBodyAsString())->toBe(MAIL_QUEUED_ADDRESS);
});

test('the notice of a changed address waits in the queue with neither address in its row', function () {
    $ada = User::factory()->withUserProfile()->create(['email' => MAIL_QUEUED_ADDRESS, 'password' => Hash::make('a-Long-Password-9!')]);
    config(['queue.default' => 'database']);

    // The request of the change, the job that queues the link, then the link of the mail to the new address
    app(ChangesEmailAddresses::class)->request($ada, 'reader.new@example.com');
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default', '--sleep' => 0, '--tries' => 1]);
    $link = mailQueuedJob()['command'];
    expect($link instanceof SendQueuedMailable ? $link->mailable : null)->toBeInstanceOf(ConfirmNewEmailAddress::class);
    $url = $link instanceof SendQueuedMailable && $link->mailable instanceof ConfirmNewEmailAddress ? $link->mailable->url : '';
    DB::table('jobs')->delete();

    $this->actingAs($ada->fresh())->get($url)->assertRedirect(route('profile.show'));

    $queued = mailQueuedJob();
    expect($ada->fresh()?->email)->toBe('reader.new@example.com')
        ->and($queued['command'] instanceof SendQueuedMailable ? $queued['command']->mailable : null)->toBeInstanceOf(EmailAddressChanged::class)
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(stripos($queued['raw'], 'reader.new@example.com'))->toBeFalse()
        ->and(mailQueuedSentCount())->toBe(0);
});

test('the alert of the mail budget waits in the queue with no address of the operator in its row', function () {
    config(['queue.default' => 'database', 'mail.budget.monthly_quota' => 100, 'mail.budget.alert_to' => [MAIL_QUEUED_ADDRESS]]);
    DB::table('mail_usage_months')->insert(['cuid' => 'mailqueuedtestrow00000aa', 'month' => '2026-11-01', 'sent_count' => 85, 'created_at' => now(), 'updated_at' => now()]);

    app(ChecksMailBudgets::class)->check();

    $queued = mailQueuedJob();
    expect($queued['command'] instanceof SendQueuedMailable ? $queued['command']->mailable : null)->toBeInstanceOf(MailBudgetAlert::class)
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(mailQueuedSentCount())->toBe(0);
});

test('the mail of the address check is not sent inside the request: it waits in the queue, and the worker sends it with a link that works', function () {
    $grace = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create(['email' => MAIL_QUEUED_ADDRESS]);
    config(['queue.default' => 'database']);

    $this->actingAs($grace)->post('/email/verification-notification')->assertSessionHasNoErrors();

    $queued = mailQueuedJob();
    expect($queued['command'] instanceof SendQueuedNotifications ? $queued['command']->notification : null)->toBeInstanceOf(VerifyEmailAddress::class)
        ->and($queued['queue'])->toBe('default')
        ->and(stripos($queued['raw'], MAIL_QUEUED_ADDRESS))->toBeFalse()
        ->and(mailQueuedSentCount())->toBe(0);

    mailQueuedRunMailJob();

    $transport = Mail::mailer('array')->getSymfonyTransport();
    $message = $transport instanceof ArrayTransport ? $transport->messages()->last()?->getOriginalMessage() : null;
    expect(mailQueuedSentCount())->toBe(1)
        ->and(preg_match('#https?://[^\s"]+/email/verify/[^\s"]+#', (string) $message?->getTextBody(), $match))->toBe(1);

    $this->actingAs($grace)->get(html_entity_decode($match[0]))->assertRedirect();
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
});
