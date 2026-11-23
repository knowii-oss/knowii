<?php

use App\Contracts\Auth\SendsMagicLinks;
use App\Contracts\Communities\TransfersCommunityOwnership;
use App\Contracts\CommunityMembers\InvitesCommunityMembers;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiNotificationEmailGroup;
use App\Http\Controllers\Inertia\MagicLinkController;
use App\Mail\MailBudgetAlert;
use App\Mail\NotificationMail;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use App\Support\MailStopLink;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Mail\TestNotificationMail;

/*
 * The one-click stop of a notification email (#1478, ADR 0054, RFC 8058): the signed stop link, its page, the one-click POST
 * of a mail program, the undo, and the headers and the footer of a notification mail.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    config(['mail.default' => 'array']);
    $this->ada = User::factory()->withUserProfile()->create(['email' => 'ada@example.com']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function msChoices(): int
{
    return NotificationEmailChoice::query()->count();
}

/**
 * The path and the query of a signed address, for a request of the test
 */
function msPath(string $url): string
{
    return Str::after($url, config()->string('app.url'));
}

function msOneClick(string $url, bool $multipart = false): TestResponse
{
    return $multipart
        ? test()->call('POST', msPath($url), ['List-Unsubscribe' => 'One-Click'], [], [], ['CONTENT_TYPE' => 'multipart/form-data; boundary=x'])
        : test()->call('POST', msPath($url), [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'List-Unsubscribe=One-Click');
}

/**
 * @return list<Email>
 */
function msSent(): array
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
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

// The routes

test('three routes without a sign-in: the limit per IP address, then the signature (#1478)', function () {
    foreach (['mail.stop.show' => 'GET', 'mail.stop.store' => 'POST', 'mail.stop.undo' => 'POST'] as $name => $method) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull();
        $middleware = app('router')->gatherRouteMiddleware($route);
        $throttle = collect($middleware)->search(fn ($m) => is_string($m) && str_starts_with($m, ThrottleRequests::class));
        $signed = collect($middleware)->search(fn ($m) => is_string($m) && str_starts_with($m, ValidateSignature::class));

        expect($route->methods())->toContain($method)
            ->and($route->parameterNames())->toBe([])
            ->and($route->gatherMiddleware())->toContain('throttle:mail-stop')
            ->and($throttle)->toBeInt()
            ->and($signed)->toBeInt()
            ->and($throttle)->toBeLessThan($signed);
        foreach ($middleware as $name) {
            expect(is_string($name) ? $name : '')->not->toStartWith('auth')->not->toStartWith('ability')->not->toStartWith('verified');
        }
        if ($method === 'POST') {
            expect($middleware)->not->toContain(PreventRequestForgery::class)
                ->and($middleware)->not->toContain(StartSession::class);
        }
    }
});

// The page

test('the GET shows the confirm state and changes nothing (#1478)', function () {
    $url = MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::RepliesAndMentions);

    $this->get(msPath($url))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Mail/Stop')
            ->where('state', 'confirm')
            ->where('group', 'replies_and_mentions')
            ->where('stopUrl', $url)
            ->where('undoUrl', null));

    expect(msChoices())->toBe(0);
});

test('the GET of a group that is off shows the done state, with the undo (#1478)', function () {
    NotificationEmailChoice::query()->create(['user_id' => $this->ada->id, 'group' => KnowiiNotificationEmailGroup::Events, 'state' => NotificationEmailChoice::STATE_OFF]);

    $this->get(msPath(MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Mail/Stop')
            ->where('state', 'done')
            ->where('undoUrl', MailStopLink::undo($this->ada->cuid, KnowiiNotificationEmailGroup::Events)));
});

test('the page names no person, no address and no community (#1478)', function () {
    $c = communityWithMembers();
    $member = $c['member'];

    $html = $this->get(msPath(MailStopLink::for($member, KnowiiNotificationEmailGroup::ImportantNews)))->assertOk()->getContent();

    expect($html)->not->toContain($member->email)
        ->and($html)->not->toContain($member->name)
        ->and($html)->not->toContain($c['community']->name);
});

// The one-click POST

test('the one-click POST stops the group, answers 200 with an empty body, sets no cookie and needs no CSRF token (#1478)', function (bool $multipart) {
    $url = MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::DirectMessages);

    $response = msOneClick($url, $multipart);

    $response->assertStatus(200);
    expect($response->getContent())->toBe('')
        ->and($response->headers->getCookies())->toBe([])
        ->and(NotificationEmailChoice::query()->sole()->group)->toBe(KnowiiNotificationEmailGroup::DirectMessages);

    // A second POST changes nothing
    msOneClick($url, $multipart)->assertStatus(200);
    expect(msChoices())->toBe(1);
})->with(['urlencoded' => false, 'multipart' => true]);

test('the stop sets the group off whatever state its row held (#1478)', function () {
    NotificationEmailChoice::query()->create(['user_id' => $this->ada->id, 'group' => KnowiiNotificationEmailGroup::WeeklySummary, 'state' => 'weekly']);

    msOneClick(MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::WeeklySummary))->assertStatus(200);

    expect(NotificationEmailChoice::query()->sole()->state)->toBe(NotificationEmailChoice::STATE_OFF);
});

test('a signed-in session does not change the one-click POST (#1478)', function () {
    $this->actingAs(User::factory()->create());

    msOneClick(MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events))->assertStatus(200);

    expect(NotificationEmailChoice::query()->sole()->user_id)->toBe($this->ada->id);
});

// The POST of the page and the undo

test('the button of the page stops, then the page shows the done state; the undo turns the group on again (#1478)', function () {
    $group = KnowiiNotificationEmailGroup::WeeklySummary;
    $url = MailStopLink::for($this->ada, $group);

    $this->post(msPath($url), ['source' => 'page'])->assertStatus(303)->assertRedirect(MailStopLink::done($this->ada->cuid, $group));
    expect(msChoices())->toBe(1);

    $this->get(msPath(MailStopLink::done($this->ada->cuid, $group)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'done')->where('undoUrl', MailStopLink::undo($this->ada->cuid, $group)));

    $this->post(msPath(MailStopLink::undo($this->ada->cuid, $group)))->assertStatus(303)->assertRedirect(MailStopLink::undone($this->ada->cuid, $group));
    expect(msChoices())->toBe(0);

    $this->get(msPath(MailStopLink::undone($this->ada->cuid, $group)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'undone')->where('stopUrl', null));
});

// A link that is not valid

test('a changed user, group or restore gives 403: the page in its not valid state for the GET, an empty body for a POST, and nothing changes (#1478)', function (string $from, string $to, string $route) {
    $url = $route === 'undo'
        ? MailStopLink::undo($this->ada->cuid, KnowiiNotificationEmailGroup::Events)
        : MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events);
    NotificationEmailChoice::query()->create(['user_id' => $this->ada->id, 'group' => KnowiiNotificationEmailGroup::Events, 'state' => NotificationEmailChoice::STATE_OFF]);
    $changed = str_replace($from, $to, $url);
    expect($changed)->not->toBe($url);

    if ($route === 'show') {
        $this->get(msPath($changed))
            ->assertStatus(403)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Mail/Stop')->where('state', 'invalid')->where('group', null));
        $response = msOneClick($changed);
    } else {
        $response = $this->post(msPath($changed));
    }

    $response->assertStatus(403);
    expect($response->getContent())->toBe('')
        ->and(NotificationEmailChoice::query()->sole()->state)->toBe('off');
})->with([
    'the user' => ['user=', 'user=x', 'show'],
    'the group' => ['group=events', 'group=important_news', 'show'],
    'the restore' => ['restore=default', 'restore=off', 'undo'],
    'the signature' => ['signature=', 'signature=0', 'show'],
]);

test('the not valid state is the page in production too, not the general error page (#1478)', function () {
    configuredInstance();
    app()->detectEnvironment(fn () => 'production');

    $this->get('/mail/stop?user=x&group=events&signature=0')
        ->assertStatus(403)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Mail/Stop')->where('state', 'invalid'));
    expect($this->call('POST', '/mail/stop?user=x&group=events&signature=0')->getContent())->toBe('');
});

test('a valid link of an account that does not exist gives the answers of a live account and writes nothing (#1478)', function () {
    $group = KnowiiNotificationEmailGroup::Events;
    $url = MailStopLink::address('nosuchaccountaaaaaaaaaaa', $group);

    $this->get(msPath($url))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'confirm'));
    msOneClick($url)->assertStatus(200);
    $this->post(msPath($url), ['source' => 'page'])->assertStatus(303)->assertRedirect(MailStopLink::done('nosuchaccountaaaaaaaaaaa', $group));
    $this->get(msPath(MailStopLink::done('nosuchaccountaaaaaaaaaaa', $group)))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('state', 'done'));
    $this->post(msPath(MailStopLink::undo('nosuchaccountaaaaaaaaaaa', $group)))->assertStatus(303);

    expect(msChoices())->toBe(0);
});

test('the three routes share a limit per IP address: the 61st call in a minute gives 429, also with bad signatures (#1478)', function () {
    for ($i = 0; $i < 60; $i++) {
        $i % 2 === 0
            ? $this->get('/mail/stop?user=x&group=events&signature=bad')->assertStatus(403)
            : $this->call('POST', '/mail/stop/undo?user=x&group=events&restore=default&signature=bad')->assertStatus(403);
    }

    $this->get('/mail/stop?user=x&group=events&signature=bad')->assertStatus(429);
    msOneClick(MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events))->assertStatus(429);
    expect(msChoices())->toBe(0);
});

test('a link of another key of the application works while that key is a previous key (#1478)', function () {
    $url = MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events);
    $old = config()->string('app.key');
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$old]]);

    msOneClick($url)->assertStatus(200);
});

// The headers and the footer

test('a notification mail has the one-click headers: the signed link of its group, from APP_URL, equal to its footer link (#1478)', function () {
    config(['queue.default' => 'database']);
    Mail::to($this->ada)->queue(new TestNotificationMail('Hello', $this->ada, KnowiiNotificationEmailGroup::Events));

    // The queued job holds no link
    $payload = (string) DB::table('jobs')->where('queue', 'mail')->value('payload');
    expect($payload)->not->toContain('mail/stop')->and($payload)->not->toContain('signature=');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'mail', '--sleep' => 0]);

    $message = msSent()[0];
    $link = MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events);
    expect($message->getHeaders()->get('List-Unsubscribe')?->getBodyAsString())->toBe('<'.$link.'>')
        ->and($message->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($link)->toStartWith(config()->string('app.url').'/mail/stop?')
        ->and((string) $message->getHtmlBody())->toContain('href="'.e($link).'"')
        ->and((string) $message->getTextBody())->toContain('Stop emails like this: '.$link);
});

test('the footer: the links as a list, then the operator and the postal address; the plain text in the same order (#1478)', function () {
    config(['legal.operator' => 'Example Operator SRL', 'legal.postal_address' => '1 Example Street, 1000 Brussels, Belgium']);

    Mail::to($this->ada)->send(new TestNotificationMail('Hello', $this->ada));

    $message = msSent()[0];
    $html = (string) $message->getHtmlBody();
    $settings = route('profile.notifications');
    expect($html)->toMatch('#<ul>\s*<li><a href="[^"]+">Stop emails like this</a></li>\s*<li><a href="'.preg_quote($settings, '#').'">Settings</a></li>\s*</ul>\s*<p>Example Operator SRL, 1 Example Street, 1000 Brussels, Belgium</p>#');

    $lines = array_values(array_filter(explode("\n", (string) $message->getTextBody()), fn (string $line) => trim($line) !== ''));
    expect($lines)->toBe([
        'Hello',
        'Stop emails like this: '.MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::RepliesAndMentions),
        'Settings: '.$settings,
        'Example Operator SRL, 1 Example Street, 1000 Brussels, Belgium',
    ]);

    config(['legal.postal_address' => '']);
    Mail::to($this->ada)->send(new TestNotificationMail('Hello', $this->ada));
    expect((string) msSent()[1]->getHtmlBody())->toContain('<p>Example Operator SRL</p>');
});

test('no other mail has the one-click headers: every mail class that is not a notification mail, and the two notifications (#1478)', function () {
    foreach (glob(dirname(__DIR__, 2).'/app/Mail/*.php') ?: [] as $file) {
        $class = 'App\\Mail\\'.basename($file, '.php');
        if ($class === NotificationMail::class || is_subclass_of($class, NotificationMail::class)) {
            continue;
        }
        $mail = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $headers = method_exists($mail, 'headers') ? $mail->headers()->text : [];
        expect(array_keys($headers))->not->toContain('List-Unsubscribe')->not->toContain('List-Unsubscribe-Post');
    }

    // Each of them, sent as the application sends it
    $c = communityWithMembers();
    $unverified = User::factory()->unverified()->create(['email' => 'grace@example.com']);
    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    app(InvitesCommunityMembers::class)->invite($c['owner'], $c['community'], ['email' => 'new@example.com', 'role' => KnowiiCommunityMemberRole::Member->value]);
    app(TransfersCommunityOwnership::class)->transfer($c['owner'], $c['community'], ['member' => $c['admin']->cuid, 'password' => 'password']);
    app(ChangesEmailAddresses::class)->request($this->ada, 'ada.new@example.com');
    Mail::to('ops@example.com')->send(new MailBudgetAlert(CarbonImmutable::parse('2026-11-01'), 80, 100, 80, 'count', 95, null));
    $unverified->notify(new VerifyEmailAddress);
    $this->ada->notify(new ResetPasswordLink('a-token'));
    Password::broker();

    $sent = msSent();
    expect(count($sent))->toBe(7);
    foreach ($sent as $message) {
        expect($message->getHeaders()->has('List-Unsubscribe'))->toBeFalse()
            ->and($message->getHeaders()->has('List-Unsubscribe-Post'))->toBeFalse();
    }
});

test('the stop link and the headers work with the mailer log and smtp, without the provider (#1478)', function (string $mailer) {
    config(['mail.default' => $mailer, 'services.resend.webhook_secret' => '']);

    msOneClick(MailStopLink::for($this->ada, KnowiiNotificationEmailGroup::Events))->assertStatus(200);
    expect(msChoices())->toBe(1)
        ->and((new TestNotificationMail('Hello', $this->ada))->headers()->text)->toHaveKey('List-Unsubscribe');
})->with(['log', 'smtp']);

// The sign-in form

test('the answer of the sign-in link form has the help line, the same for every address (#1478)', function () {
    $response = $this->post('/login/link', ['email' => 'nobody@example.com']);

    expect(session('status'))->toEndWith("\n".MagicLinkController::HELP_LINE)
        ->and(MagicLinkController::HELP_LINE)->toBe('No email after a few minutes? Look in your spam folder, then ask again. An address that refused our mail before can need a second try.');
    $response->assertRedirect(route('login'));
});
