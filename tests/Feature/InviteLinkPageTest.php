<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Http\Controllers\Inertia\InviteLinkController;
use App\Http\Middleware\EndOutdatedSessions;
use App\Http\Middleware\StartSessionWithoutTokenAddresses;
use App\Models\AccessGroup;
use App\Models\AccessGroupMember;
use App\Models\Community;
use App\Models\CommunityJoinBlock;
use App\Models\InviteLink;
use App\Models\MemberTag;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Support\InviteLinkTexts;
use App\Support\Landing;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Jetstream\Http\Middleware\AuthenticateSession;
use PragmaRX\Google2FA\Google2FA;

/*
 * The page of an invite link and the way in (#1391, ADR 0062): the address of a link keeps the hash of its token in the
 * session and sends to the page, whose address, props and HTML hold no token. "Join" is a POST to the page that reads the
 * link of the session. A visitor signs up or signs in and comes back to the page in the
 * same browser; the page is never a landing, and its token never goes in a mail. The page tells nothing about a link that
 * does not work, and never shows the name of a link, its groups, its tags or its counts.
 */

const ILPAGE_PASSWORD = 'a-Long-Password-9!';
const ILPAGE_LINK_NAME = 'Qxpagelink Course buyers';
const ILPAGE_GROUP_NAME = 'Qxpagegroup Cohort';
const ILPAGE_TAG_NAME = 'Qxpagetag Buyer';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->community->forceFill(['name' => 'Harbour Of Links', 'slug' => 'harbour-of-links', 'description' => 'A harbour of example'])->save();
    $this->group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => ILPAGE_GROUP_NAME]);
    $this->tag = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => ILPAGE_TAG_NAME]);
    $this->link = InviteLink::factory()->create(['community_id' => $this->community->id, 'name' => ILPAGE_LINK_NAME, 'max_uses' => 100]);
    $this->link->accessGroups()->attach($this->group->id);
    $this->link->memberTags()->attach($this->tag->id);
    Event::fake([CommunityMembersChanged::class, AccessGroupsChanged::class, SpaceTreeChanged::class]);
});

/**
 * Another browser, or another device: no session, no cookie, and nobody is signed in
 */
function ilpageNewBrowser(): void
{
    session()->flush();
    session()->regenerate(true);
    app('auth')->forgetGuards();
}

/**
 * A visitor signs up with the form, in the browser of the test
 */
function ilpageSignUp(): User
{
    test()->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => ILPAGE_PASSWORD, 'password_confirmation' => ILPAGE_PASSWORD, 'terms' => true])
        ->assertRedirect();

    return User::query()->where('email', 'grace@example.com')->firstOrFail();
}

/**
 * The first address of the last mail that holds the given text, as the person reads it in the mail
 */
function ilpageAddressOfTheMail(string $part): string
{
    $html = (string) app('mailer')->getSymfonyTransport()->messages()->last()?->getOriginalMessage()->getHtmlBody();
    preg_match('~href="([^"]*'.preg_quote($part, '~').'[^"]*)"~', $html, $match);

    return html_entity_decode($match[1] ?? '');
}

/**
 * The whole text of every mail that was sent
 */
function ilpageAllMails(): string
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(static fn ($message): string => (string) $message->getOriginalMessage()->getHtmlBody().(string) $message->getOriginalMessage()->getTextBody())
        ->implode("\n");
}

function ilpageOpen(InviteLink $link): void
{
    test()->get(route('invite-links.open', ['inviteLinkToken' => $link->token]))->assertRedirect(route('invite-links.show'));
}

/**
 * "Join" on the page: the POST of the page, with the cuid of the community that the page shows
 */
function ilpageJoin(Community $community): TestResponse
{
    return test()->post(route('invite-links.join'), ['community' => $community->cuid]);
}

/**
 * The decoded payloads of every session of the database
 */
function ilpageSessionPayloads(): string
{
    return DB::table('sessions')->pluck('payload')->map(static fn ($payload): string => (string) base64_decode((string) $payload, true))->implode("\n");
}

function ilpageIsMember(User $user, Community $community): bool
{
    return User::query()->findOrFail($user->id)->belongsToCommunity($community);
}

// ---------------------------------------------------------------------------------------------------------------------
// The address and the page
// ---------------------------------------------------------------------------------------------------------------------

test('the address of a link keeps the hash of the token in the session and sends to the page, whose address holds no token', function () {
    $response = $this->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]));

    $response->assertRedirect(route('invite-links.show'))->assertSessionHas(InviteLinkController::SESSION_KEY, $this->link->token_hash);
    expect(session()->all())->not->toContain($this->link->token);
    expect((string) $response->headers->get('Location'))->not->toContain($this->link->token)
        ->and(route('invite-links.show', [], false))->toBe('/invite')
        ->and($this->link->url())->toBe(url('/invite/'.$this->link->token));
});

test('a visitor reads the page: the community and the state join, no token, and the session keeps the page without the token', function () {
    ilpageOpen($this->link);

    $this->get(route('invite-links.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('InviteLinks/Show')
            ->where('state', 'join')
            ->missing('token')
            ->where('community', [
                'cuid' => $this->community->cuid,
                'name' => 'Harbour Of Links',
                'slug' => 'harbour-of-links',
                'description' => 'A harbour of example',
                'visibility' => $this->community->visibility->value,
                'join_policy' => 'invitation',
                'is_member' => false,
            ]))
        ->assertSessionHas('url.intended', route('invite-links.show'));
});

test('opening the page writes nothing: no membership, no count, no record', function () {
    $before = [DB::table('community_members')->count(), DB::table('invite_link_uses')->count(), DB::table('access_group_members')->count(), DB::table('member_tag_members')->count()];

    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk();
    app('auth')->forgetGuards();
    $this->actingAs($this->c['outsider'])->get(route('invite-links.show'))->assertOk();

    expect([DB::table('community_members')->count(), DB::table('invite_link_uses')->count(), DB::table('access_group_members')->count(), DB::table('member_tag_members')->count()])->toBe($before)
        ->and($this->link->fresh()?->uses_count)->toBe(0);
});

test('a signed-in account reads its state: join, member (the owner too) or blocked', function () {
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $this->c['outsider']->id]);
    $newcomer = User::factory()->withUserProfile()->create();
    $cases = [[$newcomer, 'join'], [$this->c['member'], 'member'], [$this->c['owner'], 'member'], [$this->c['outsider'], 'blocked']];

    foreach ($cases as [$user, $state]) {
        app('auth')->forgetGuards();
        $this->actingAs($user);
        ilpageOpen($this->link);
        $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->component('InviteLinks/Show')->where('state', $state));
    }
});

test('every link that does not work gives the page of an unusable link, with status 404 and no data of a community', function (string $case) {
    $expected = ['community' => null, 'state' => 'unusable'];
    $token = $this->link->token;
    match ($case) {
        'off' => $this->link->update(['active' => false]),
        'ended' => $this->link->update(['expires_at' => '2026-11-23T09:00:00Z']),
        'full' => $this->link->forceFill(['max_uses' => 1, 'uses_count' => 1])->save(),
        'deleted' => $this->link->delete(),
        'unknown' => $token = InviteLink::newToken(),
        'of a personal community' => $this->community->forceFill(['visibility' => KnowiiCommunityVisibility::Personal])->save(),
        'no link in the session' => $token = null,
    };

    if ($token !== null) {
        $this->get(route('invite-links.open', ['inviteLinkToken' => $token]))->assertRedirect(route('invite-links.show'));
    }
    $response = $this->get(route('invite-links.show'))->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('InviteLinks/Show')->where('community', null)->where('state', 'unusable')->missing('token'));

    $props = $response->viewData('page')['props'];
    expect(array_intersect_key($props, [...$expected, 'token' => null]))->toBe($expected)
        ->and(json_encode($props))->not->toContain('Harbour Of Links')->not->toContain($this->community->cuid);

    // "Join" on the page answers the same refusal for every reason, and writes nothing
    $this->actingAs($this->c['outsider']);
    ilpageJoin($this->community)->assertRedirect(route('invite-links.show'))->assertSessionHasErrors([InviteLinkController::ERROR_KEY => InviteLinkTexts::UNUSABLE]);
    // The same answer when the page sends the cuid of another community: the community of the link stays unknown
    ilpageJoin(communityWithMembers()['community'])->assertRedirect(route('invite-links.show'))->assertSessionHasErrors([InviteLinkController::ERROR_KEY => InviteLinkTexts::UNUSABLE]);
    expect(ilpageIsMember($this->c['outsider'], $this->community))->toBeFalse()
        ->and(DB::table('invite_link_uses')->count())->toBe(0);
})->with(['off', 'ended', 'full', 'deleted', 'unknown', 'of a personal community', 'no link in the session']);

test('an address that is not the address of a link answers 404', function (string $token) {
    $this->get('/invite/'.$token)->assertNotFound();
})->with(['too-short', 'abcdefghijabcdefghijabcdefghijabcdefghij-', 'abcdefghijabcdefghijabcdefghijabcdefghijk']);

test('the page never holds the name of the link, its groups, its tags, its counts, its hash or a member', function (string $who) {
    $this->link->forceFill(['uses_count' => 7])->save();
    if ($who !== 'visitor') {
        $this->actingAs($this->c[$who]);
    }
    ilpageOpen($this->link);

    $response = $this->get(route('invite-links.show'))->assertOk();
    $props = json_encode($response->viewData('page')['props']);
    $html = (string) $response->getContent();

    foreach ([$this->link->token, ILPAGE_LINK_NAME, ILPAGE_GROUP_NAME, ILPAGE_TAG_NAME, $this->group->cuid, $this->tag->cuid, $this->link->cuid, $this->link->token_hash, '"uses_count"', '"max_uses"', '"member_count"', $this->c['admin']->cuid, $this->c['admin']->username] as $secret) {
        expect($props)->not->toContain($secret)->and($html)->not->toContain($secret);
    }
})->with(['visitor', 'outsider', 'member']);

test('the token of a link is in no prop of another page', function () {
    $this->actingAs($this->c['outsider']);
    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk();

    $dashboard = $this->get(route('dashboard'))->assertOk();
    expect((string) $dashboard->getContent())->not->toContain($this->link->token);
});

// ---------------------------------------------------------------------------------------------------------------------
// The way in
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor opens the link, signs up, confirms the address in the same browser, comes back to the page and joins', function () {
    ilpageNewBrowser();
    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'join'));

    $grace = ilpageSignUp();
    $link = ilpageAddressOfTheMail('/email/verify/');
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    // The link of the address check holds no landing and no token
    expect($link)->toContain('/email/verify/')
        ->and($query)->not->toHaveKey(Landing::LINK_PARAMETER)
        ->and($link)->not->toContain($this->link->token)
        ->and(ilpageAllMails())->not->toContain($this->link->token)->not->toContain('/invite');

    // An account without a verified address goes to the address check, which keeps the page
    $this->get(route('invite-links.show'))->assertRedirect(route('verification.notice'));
    $this->get($link)->assertRedirect(route('invite-links.show'));
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();

    app('auth')->forgetGuards();
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'join')->missing('token'));
    expect(ilpageIsMember($grace, $this->community))->toBeFalse();

    // "Join" is a POST of the person on the page, which reads the link of the session; then the session forgets it
    ilpageJoin($this->community)->assertRedirect(route('communities.show', ['communitySlug' => 'harbour-of-links']))->assertSessionHasNoErrors();
    expect(session()->has(InviteLinkController::SESSION_KEY))->toBeFalse()
        ->and(ilpageIsMember($grace, $this->community))->toBeTrue()
        ->and(AccessGroupMember::query()->where('user_id', $grace->id)->where('access_group_id', $this->group->id)->value('manual'))->toBeTrue()
        ->and(DB::table('member_tag_members')->where('user_id', $grace->id)->pluck('member_tag_id')->all())->toBe([$this->tag->id]);
});

test('an account without a verified address that opens the link goes to the address check, then back to the page', function () {
    $grace = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create(['email' => 'grace@example.com']);
    $this->actingAs($grace);

    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertRedirect(route('verification.notice'));
    $this->post('/email/verification-notification')->assertSessionHasNoErrors();
    $link = ilpageAddressOfTheMail('/email/verify/');
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
    expect($query)->not->toHaveKey(Landing::LINK_PARAMETER)->and($link)->not->toContain($this->link->token);

    $this->get($link)->assertRedirect(route('invite-links.show'));
    app('auth')->forgetGuards();
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'join'));
});

test('a link of the address check with the landing of another community does not replace the page of the link in the session', function () {
    $other = communityWithMembers(KnowiiCommunityVisibility::Public)['community'];
    $other->forceFill(['slug' => 'second-harbour', 'join_policy' => KnowiiCommunityJoinPolicy::Open])->save();
    $grace = User::factory()->unverified()->withUserProfile()->withPersonalCommunity()->create(['email' => 'grace@example.com']);
    $link = (new VerifyEmailAddress('/community/second-harbour'))->toMail($grace)->actionUrl;
    expect($link)->toContain(Landing::LINK_PARAMETER.'=community.second-harbour');

    $this->actingAs($grace)->withSession(['url.intended' => route('invite-links.show'), InviteLinkController::SESSION_KEY => $this->link->token_hash])
        ->get($link)->assertRedirect(route('invite-links.show'));
});

test('a sign-in with a password, with the second factor, or with a sign-in link in the same browser comes back to the page', function (string $how) {
    $person = $this->c['outsider'];
    if ($how === 'the second factor') {
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $person->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])), 'two_factor_confirmed_at' => now()])->save();
    }

    ilpageNewBrowser();
    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk();

    match ($how) {
        'a password' => $this->post('/login', ['email' => $person->email, 'password' => 'password'])->assertRedirect(route('invite-links.show')),
        'the second factor' => (function () use ($person, $secret) {
            $this->post('/login', ['email' => $person->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
            $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('invite-links.show'));
        })(),
        'a sign-in link' => (function () use ($person) {
            $this->post('/login/link', ['email' => $person->email])->assertRedirect(route('login'));
            $mailLink = ilpageAddressOfTheMail('/login/link/');
            expect($mailLink)->not->toContain($this->link->token)->and(ilpageAllMails())->not->toContain('/invite');
            $this->post('/login/link/'.Str::afterLast($mailLink, '/'))->assertRedirect(route('invite-links.show'));
        })(),
    };

    $this->assertAuthenticatedAs($person);
    app('auth')->forgetGuards();
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'join')->missing('token'));
})->with(['a password', 'the second factor', 'a sign-in link']);

test('a person who confirms the address in another browser lands as before, and opens the link again', function () {
    ilpageNewBrowser();
    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk();
    $grace = ilpageSignUp();
    $link = ilpageAddressOfTheMail('/email/verify/');

    ilpageNewBrowser();
    $this->post('/login', ['email' => 'grace@example.com', 'password' => ILPAGE_PASSWORD]);
    $this->get($link)->assertRedirect(route('dashboard').'?verified=1');
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();

    app('auth')->forgetGuards();
    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'join'));
});

// ---------------------------------------------------------------------------------------------------------------------
// "Join" on the page, and the session
// ---------------------------------------------------------------------------------------------------------------------

test('with the sessions in the database, no session holds the token, after the opening and after the join', function () {
    config(['session.driver' => 'database']);
    $this->actingAs($this->c['outsider']);

    ilpageOpen($this->link);
    expect(DB::table('sessions')->count())->toBe(1)
        ->and(ilpageSessionPayloads())->toContain($this->link->token_hash)->not->toContain($this->link->token);

    // The browser sends its session cookie: one row of the database holds the session
    $this->withCookie((string) config('session.cookie'), (string) DB::table('sessions')->value('id'));
    $this->get(route('invite-links.show'))->assertOk();
    expect(ilpageSessionPayloads())->toContain($this->link->token_hash)->not->toContain($this->link->token);

    ilpageJoin($this->community)->assertRedirect(route('communities.show', ['communitySlug' => 'harbour-of-links']));
    expect(ilpageIsMember($this->c['outsider'], $this->community))->toBeTrue()
        ->and(DB::table('sessions')->count())->toBe(1)
        ->and(ilpageSessionPayloads())->not->toContain($this->link->token)->not->toContain($this->link->token_hash);
});

test('with the sessions in the database, an address near the address of a link keeps no token as the previous address', function (string $suffix) {
    config(['session.driver' => 'database']);

    // A mail or a chat that glues a character to the link: the fallback route answers
    $this->get('/invite/'.$this->link->token.$suffix)->assertNotFound();
    expect(DB::table('sessions')->count())->toBeGreaterThanOrEqual(1)
        ->and(ilpageSessionPayloads())->not->toContain($this->link->token)
        ->and(session()->previousUrl())->toBeNull();

    // The page of the link is still the previous address of its session
    $this->get(route('invite-links.show'))->assertNotFound();
    expect(session()->previousUrl())->toBe(route('invite-links.show'))
        ->and(ilpageSessionPayloads())->not->toContain($this->link->token);
})->with([')', '.', 'x', '/x', '%20']);

test('with the sessions in the database, an address of a link in capital letters keeps no token as the previous address', function (string $prefix) {
    config(['session.driver' => 'database']);

    $this->get($prefix.$this->link->token)->assertNotFound();
    expect(ilpageSessionPayloads())->not->toContain($this->link->token)
        ->and(session()->previousUrl())->toBeNull();
})->with(['/Invite/', '/INVITE/', '/API/v1/invite-links/', '/Api/v1/invite-links/']);

test('with the sessions in the database, a first-party GET of the API read of a link keeps no token as the previous address', function () {
    config(['session.driver' => 'database']);
    $this->actingAs($this->c['outsider'], 'web');

    // A plain link of the application to that API address: the session cookie and the Referer of a page, without XHR
    $this->get(ApiRoutes::path(ApiRoutes::INVITE_LINK, [ApiRoutes::PARAM_INVITE_LINK_TOKEN => $this->link->token]), ['Referer' => config('app.url').'/dashboard'])
        ->assertOk();
    expect(DB::table('sessions')->count())->toBe(1)
        ->and(ilpageSessionPayloads())->not->toContain($this->link->token)
        ->and(session()->previousUrl())->toBeNull();
});

test('a session of an account taken over since its sign-in that opens a link keeps the hash and the page of the link, never the token', function () {
    config(['session.driver' => 'database']);
    $person = $this->c['outsider'];
    $person->forceFill(['session_version' => 1])->save();

    $this->actingAs($person->fresh() ?? $person)->withSession([EndOutdatedSessions::SESSION_KEY => 0])
        ->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]))
        ->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session('url.intended'))->toBe(route('invite-links.show'))
        ->and(session(InviteLinkController::SESSION_KEY))->toBe($this->link->token_hash)
        ->and(session()->previousUrl())->toBeNull()
        ->and(ilpageSessionPayloads())->toContain($this->link->token_hash)->not->toContain($this->link->token);

    // The sign-in in the same browser comes back to the page of the link
    $this->post('/login', ['email' => $person->email, 'password' => 'password'])->assertRedirect(route('invite-links.show'));
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('community.cuid', $this->community->cuid)->where('state', 'join'));
});

test('the session middleware is the one of the application, with the cache that blocks a session, in the place of the framework middleware', function () {
    expect(app(StartSession::class))->toBeInstanceOf(StartSessionWithoutTokenAddresses::class)
        ->and(app(StartSession::class))->toBe(app(StartSession::class));

    // A route that blocks its session, and every route when the configuration blocks the sessions
    Route::middleware('web')->get('/qx-blocked-session', static fn (): string => 'blocked')->block(2, 2);
    $this->get('/qx-blocked-session')->assertOk()->assertSee('blocked');
    config(['session.block' => true]);
    $this->get('/qx-blocked-session')->assertOk();
    $this->actingAs($this->c['outsider'])->get(route('dashboard'))->assertOk();

    // The order of the middleware of the web routes keeps the name of the framework middleware
    $router = app('router');
    $start = [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EndOutdatedSessions::class,
    ];
    expect(array_slice($router->gatherRouteMiddleware($router->getRoutes()->getByName('dashboard')), 0, 9))->toBe([
        ...$start,
        Authenticate::class.':sanctum',
        AuthenticateSession::class,
        SubstituteBindings::class,
    ])
        ->and(array_slice($router->gatherRouteMiddleware($router->getRoutes()->getByName('invite-links.open')), 0, 6))->toBe($start)
        ->and(array_slice($router->gatherRouteMiddleware($router->getRoutes()->getByName('invite-links.show')), 0, 6))->toBe($start);
});

test('a member reads the page one time: the session forgets the link', function () {
    $this->actingAs($this->c['member']);
    ilpageOpen($this->link);

    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('state', 'member'));
    expect(session()->has(InviteLinkController::SESSION_KEY))->toBeFalse();
    $this->get(route('invite-links.show'))->assertNotFound()->assertInertia(fn ($page) => $page->where('state', 'unusable'));
});

test('a second tab opened a newer link: the join of the older page is refused with a clear text, and the page shows the newer link', function () {
    $other = communityWithMembers()['community'];
    $newer = InviteLink::factory()->create(['community_id' => $other->id]);
    $person = $this->c['outsider'];
    $this->actingAs($person);

    ilpageOpen($this->link);
    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('community.cuid', $this->community->cuid));
    // The second tab
    ilpageOpen($newer);

    ilpageJoin($this->community)->assertRedirect(route('invite-links.show'))
        ->assertSessionHasErrors([InviteLinkController::ERROR_KEY => InviteLinkTexts::ANOTHER_LINK]);
    expect(ilpageIsMember($person, $this->community))->toBeFalse()
        ->and(ilpageIsMember($person, $other))->toBeFalse()
        ->and(DB::table('invite_link_uses')->count())->toBe(0)
        ->and(InviteLinkTexts::ANOTHER_LINK)->toBe('This browser opened another invite link after this page loaded. This page now shows that link. To join through the first link, open it again.');

    $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('community.cuid', $other->cuid)->where('state', 'join'));
    ilpageJoin($other)->assertRedirect(route('communities.show', ['communitySlug' => $other->slug]));
    expect(ilpageIsMember($person, $other))->toBeTrue()->and(ilpageIsMember($person, $this->community))->toBeFalse();
});

test('the join of the page refuses a person whom a manager removed and a member with their text, and a visitor signs in first', function () {
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $this->c['outsider']->id]);

    ilpageOpen($this->link);
    ilpageJoin($this->community)->assertRedirect(route('login'));

    $this->actingAs($this->c['outsider']);
    ilpageJoin($this->community)->assertRedirect(route('invite-links.show'))
        ->assertSessionHasErrors([InviteLinkController::ERROR_KEY => InviteLinkTexts::BLOCKED]);
    expect(ilpageIsMember($this->c['outsider'], $this->community))->toBeFalse()->and(session()->has(InviteLinkController::SESSION_KEY))->toBeTrue();

    app('auth')->forgetGuards();
    $this->actingAs($this->c['member']);
    ilpageJoin($this->community)->assertRedirect(route('invite-links.show'))
        ->assertSessionHasErrors([InviteLinkController::ERROR_KEY => InviteLinkTexts::ALREADY_MEMBER]);
    expect(DB::table('invite_link_uses')->count())->toBe(0)->and($this->link->fresh()?->uses_count)->toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------------
// Abuse
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor opens 20 links a minute per IP address: the address and the page each count one opening once', function () {
    for ($i = 0; $i < Constants::INVITE_LINK_READS_PER_MINUTE; $i++) {
        ilpageOpen($this->link);
        $this->get(route('invite-links.show'))->assertOk();
    }

    $this->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]))->assertStatus(429);
    $this->get(route('invite-links.show'))->assertStatus(429);
    // Another address has its own count
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]))->assertRedirect(route('invite-links.show'));
});

test('the page of a link is never indexed, and robots.txt blocks the addresses of the links', function () {
    config(['robots.allow_indexing' => true]);

    expect(explode("\n", (string) $this->get('/robots.txt')->getContent()))->toContain('Disallow: /invite$')->toContain('Disallow: /invite/')->not->toContain('Disallow: /invite');

    $open = $this->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]));
    $open->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    foreach (['a link that works' => true, 'a link that does not work' => false] as $case => $works) {
        if (! $works) {
            $this->link->update(['active' => false]);
        }
        $page = $this->get(route('invite-links.show'));
        $page->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        expect((string) $page->getContent())->toContain('<meta name="robots" content="noindex, nofollow"');
    }
});

test('the page of a link loads no statistics script, also when the instance sets the values, and another page does', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);

    $open = $this->get(route('invite-links.open', ['inviteLinkToken' => $this->link->token]));
    expect((string) $open->getContent())->not->toContain('stats.example.com');

    foreach (['a link that works' => true, 'a link that does not work' => false] as $works) {
        if (! $works) {
            $this->link->update(['active' => false]);
        }
        $html = (string) $this->get(route('invite-links.show'))->getContent();
        expect($html)->not->toContain('stats.example.com')
            ->and($html)->not->toContain('data-domain=')
            ->and($html)->not->toContain('/content/script.js');
    }

    expect((string) $this->get('/')->getContent())->toContain('src="https://stats.example.com/content/script.js"');
});

test('the settings page tells the owner and the admins of a shared community that they manage the invite links, and nobody else', function (string $who, bool $manageMembers, bool $allowed) {
    if ($manageMembers) {
        $this->community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
    }

    $this->actingAs($this->c[$who])->get(route('communities.settings', ['communitySlug' => 'harbour-of-links']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.canManageInviteLinks', $allowed));
})->with([
    'the owner' => ['owner', false, true],
    'an admin' => ['admin', false, true],
    'a moderator with manage members' => ['moderator', true, false],
]);

test('the settings page of a personal community offers no invite link', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $owner->ownedCommunities()->firstOrFail();

    $this->actingAs($owner)->get(route('communities.settings', ['communitySlug' => $personal->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.canManageInviteLinks', false));
});
