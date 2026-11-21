<?php

use App\Actions\Auth\MagicLinks;
use App\Constants;
use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\Community;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use App\Support\Landing;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use PragmaRX\Google2FA\Google2FA;

/*
 * The landing (#1612, ADR 0057): a person who signs up or asks for a sign-in link from a page of a community lands on
 * that page after the link of the mail, also when the link opens in another browser. The landing travels in the link
 * (the signed link of the address check, the cache entry of the sign-in link). It is always a path that the router
 * gives to one of the pages for visitors, built again from the name of the route: never a text that came in. A sign-in
 * link keeps its landing only for a member of the community, and with the second factor only after the code.
 */

const LANDING_PASSWORD = 'a-Long-Password-9!';
const LANDING_HOME = '/community/harbour-of-landings';
const LANDING_SPACE = '/community/harbour-of-landings/space/open-talk';
const LANDING_POST = '/community/harbour-of-landings/space/open-talk/post/a-first-post';
const LANDING_MEMBERS_SPACE = '/community/harbour-of-landings/space/members-talk';
const LANDING_GROUPS_SPACE = '/community/harbour-of-landings/space/members-room';
// Where the session keeps the landing of a sign-in link during the challenge of the second factor
const LANDING_HELD = 'login.landing';

/**
 * A shared, open community with fixed names. With a public page: a public discussion space with a post. Always a
 * members-only discussion space.
 *
 * @return array{community: Community, owner: User, member: User, outsider: User, membersSpace: Space, publicSpace: ?Space, post: ?Post}
 */
function landingCommunity(string $name = 'Harbour Of Landings', string $slug = 'harbour-of-landings', bool $publicPage = true): array
{
    $c = communityWithMembers();
    $community = $c['community'];
    $community->forceFill(['name' => $name, 'slug' => $slug, 'join_policy' => KnowiiCommunityJoinPolicy::Open])->save();

    $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Main']);
    $make = fn (KnowiiSpaceVisibility $visibility, string $spaceName, string $spaceSlug): Space => Space::factory()->inGroup($group)
        ->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => $visibility, 'name' => $spaceName, 'slug' => $spaceSlug]);

    $membersSpace = $make(KnowiiSpaceVisibility::Members, 'Members talk', 'members-talk');
    $publicSpace = $publicPage ? $make(KnowiiSpaceVisibility::Public, 'Open talk', 'open-talk') : null;
    $post = $publicSpace === null ? null : Post::factory()->inSpace($publicSpace)->create(['author_id' => $c['member']->id, 'title' => 'A first post', 'slug' => 'a-first-post']);

    return ['community' => $community, 'owner' => $c['owner'], 'member' => $c['member'], 'outsider' => $c['outsider'], 'membersSpace' => $membersSpace, 'publicSpace' => $publicSpace, 'post' => $post];
}

/**
 * A person with an account and an address that is not verified yet
 */
function landingGrace(): User
{
    return User::factory()->unverified()->withUserProfile()->withPersonalCommunity()
        ->create(['name' => 'Grace Hopper', 'username' => 'grace-hopper', 'email' => 'grace@example.com']);
}

/**
 * The first address of the last mail that holds the given text, as the person reads it in the mail
 */
function landingAddressOfTheMail(string $part): string
{
    $html = (string) app('mailer')->getSymfonyTransport()->messages()->last()?->getOriginalMessage()->getHtmlBody();
    preg_match('~href="([^"]*'.preg_quote($part, '~').'[^"]*)"~', $html, $match);

    return html_entity_decode($match[1] ?? '');
}

/**
 * The link of the last mail of the address check
 */
function landingLinkOfTheMail(): string
{
    return landingAddressOfTheMail('/email/verify/');
}

/**
 * The token of the last sign-in link that was sent
 */
function landingTokenOfTheMail(): string
{
    return Str::afterLast(landingAddressOfTheMail('/login/link/'), '/');
}

/**
 * The value of the landing in the query of a link of the address check, as it is written, or null
 */
function landingValueOfTheLink(string $link): ?string
{
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
    $landing = $query[Landing::LINK_PARAMETER] ?? null;

    return is_string($landing) ? $landing : null;
}

/**
 * The landing that a link of the address check carries, as a path, or null
 */
function landingOfTheLink(string $link): ?string
{
    $value = landingValueOfTheLink($link);

    return $value === null ? null : '/'.str_replace('.', '/', $value);
}

/**
 * A link of the address check with a good signature and this exact value as its landing: the application never writes
 * such a link for a value that is not a landing, so the test signs it
 */
function landingSignedLink(User $user, string $value): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()), Landing::LINK_PARAMETER => $value]);
}

/**
 * The link of the address check that the application signs for this person, with this landing
 */
function landingLink(User $user, ?string $landing): string
{
    return (new VerifyEmailAddress($landing))->toMail($user)->actionUrl;
}

/**
 * Another browser, or another device: no session, no cookie, and nobody is signed in
 */
function landingNewBrowser(): void
{
    session()->flush();
    session()->regenerate(true);
    app('auth')->forgetGuards();
}

/**
 * A visitor signs up with the form, in the browser of the test
 */
function landingSignUp(): User
{
    test()->post('/register', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'password' => LANDING_PASSWORD, 'password_confirmation' => LANDING_PASSWORD, 'terms' => true])
        ->assertRedirect();

    return User::query()->where('email', 'grace@example.com')->firstOrFail();
}

/**
 * Turns the second factor on for the account, and returns its secret
 */
function landingSecondFactor(User $user): string
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

function landingIsMember(User $user, Community $community): bool
{
    return User::query()->findOrFail($user->id)->belongsToCommunity($community);
}

// ---------------------------------------------------------------------------------------------------------------------
// The landing value
// ---------------------------------------------------------------------------------------------------------------------

test('the routes of a landing are the named GET routes of the pages for visitors', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('GET', $route->methods(), true)
            && in_array('web', $route->gatherMiddleware(), true)
            && in_array('auth.optional', $route->gatherMiddleware(), true));

    expect($routes)->not->toBeEmpty()
        ->and($routes->map(fn (RoutingRoute $route): ?string => $route->getName())->values()->all())->toEqualCanonicalizing(Landing::ROUTES);

    // Each parameter of these routes is a slug, and the first one is the slug of the community
    foreach ($routes as $route) {
        expect($route->parameterNames()[0])->toBe('communitySlug');
        foreach ($route->parameterNames() as $parameter) {
            expect($parameter)->toEndWith('Slug');
        }

        // The path of each route has the form of a link: its fixed words hold only the characters of a slug
        $path = route((string) $route->getName(), array_fill_keys($route->parameterNames(), 'a-slug-2'), false);
        expect(Landing::pathOf($path))->toBe($path)
            ->and(Landing::linkValueOf($path))->toBe(str_replace('/', '.', ltrim($path, '/')))
            ->and(Landing::pathOfLinkValue(Landing::linkValueOf($path)))->toBe($path);
    }
});

test('a landing is the path of a page for visitors', function (string $address, string $path, string $route) {
    expect(Landing::pathOf($address))->toBe($path)
        // The router gives the same route to that path
        ->and(Route::getRoutes()->match(Request::create($path))->getName())->toBe($route);
})->with([
    'the home of a community' => [LANDING_HOME, LANDING_HOME, 'communities.show'],
    'a space' => [LANDING_SPACE, LANDING_SPACE, 'spaces.show'],
    'a post' => [LANDING_POST, LANDING_POST, 'posts.show'],
    'a page' => [LANDING_SPACE.'/page/a-first-page', LANDING_SPACE.'/page/a-first-page', 'pages.show'],
    'a resource' => [LANDING_SPACE.'/resource/a-first-resource', LANDING_SPACE.'/resource/a-first-resource', 'resources.show'],
    'an event' => [LANDING_SPACE.'/event/a-first-event', LANDING_SPACE.'/event/a-first-event', 'events.show'],
    'the marker of a deleted post' => [LANDING_SPACE.'/post/deleted-tz4a98xxat96iws9zmbrgj3a', LANDING_SPACE.'/post/deleted-tz4a98xxat96iws9zmbrgj3a', 'posts.show'],
    'a full address' => ['https://app.example.com'.LANDING_POST, LANDING_POST, 'posts.show'],
    'a full address with a port' => ['http://localhost:4200'.LANDING_HOME, LANDING_HOME, 'communities.show'],
    'a query is dropped' => [LANDING_SPACE.'?page=2&sort=-createdAt', LANDING_SPACE, 'spaces.show'],
    'a fragment is dropped' => [LANDING_POST.'#comment-12', LANDING_POST, 'posts.show'],
    'a slash at the end is dropped' => [LANDING_HOME.'/', LANDING_HOME, 'communities.show'],
]);

test('a full address of this instance gives its path', function () {
    expect(Landing::pathOf(route('posts.show', ['communitySlug' => 'harbour-of-landings', 'spaceSlug' => 'open-talk', 'postSlug' => 'a-first-post'])))->toBe(LANDING_POST)
        ->and(Landing::pathOf(route('communities.show', ['communitySlug' => 'harbour-of-landings'])))->toBe(LANDING_HOME);
});

/*
 * No open redirect. Each text gives no landing, or a path that the application built and that starts with "/community/".
 * The link of the address check is signed by the application here, with the text as its landing: the check of the text
 * still runs on a link with a good signature. The community "x" exists, so a landing of that community is written.
 */
dataset('texts that are not a plain page for visitors', [
    'an address of another host with a community path' => ['https://other.example/community/x', '/community/x'],
    'an address of another host with a port and a user' => ['https://user:secret@other.example:8443/community/x', '/community/x'],
    'an address without a scheme' => ['//other.example/community/x', '/community/x'],
    'an address without a scheme and without a path' => ['//other.example', null],
    'another host alone' => ['https://other.example', null],
    'three slashes' => ['///other.example/community/x', null],
    'a backslash after the slash' => ['/\\other.example', null],
    'two backslashes' => ['\\\\other.example/community/x', null],
    'a backslash in the place of a slug' => ['/community/\\other.example', null],
    'a script' => ['javascript:alert(1)', null],
    'a data address' => ['data:text/html,/community/x', null],
    'a scheme without slashes' => ['https:other.example/community/x', null],
    'a path that does not start with a slash' => ['community/x', null],
    'a path that goes up to the dashboard' => ['/community/x/../../dashboard', null],
    'a path that goes up' => ['/community/../dashboard', null],
    'two dots as a slug' => ['/community/..', null],
    'encoded dots as a slug' => ['/community/%2e%2e', null],
    'two slashes inside the path' => ['/community//other.example', null],
    'two slashes before the path' => ['https://app.example.com//other.example/community/x', null],
    'an encoded slash that gives the address of another page' => ['/community/x%2Fsettings', null],
    'an encoded slash is read as a slash, as the router reads it' => ['/community/x%2Fspace%2Fopen-talk', '/community/x/space/open-talk'],
    'an encoded backslash in a slug' => ['/community/x%5Cother.example', null],
    'an encoded line break in a slug' => ['/community/x%0Ay', null],
    'an encoded header in a slug' => ['/community/x%0D%0ALocation:%20https://other.example', null],
    'a line break in a slug' => ["/community/x\ny", null],
    'an encoded zero byte in a slug' => ['/community/x%00', null],
    'an encoded question mark in a slug' => ['/community/x%3Fnext=https://other.example', null],
    'an encoded at sign in a slug' => ['/community/x%40other.example', null],
    'a capital letter in a slug' => ['/community/X', null],
    'a dot in a slug' => ['/community/other.example', null],
    'a space in a slug' => ['/community/x y', null],
    'a slug that is not text of the alphabet' => ['/community/caf%C3%A9', null],
    'a slug that is not valid text' => ['/community/x%FF', null],
    'another address in the query' => ['/community/x?next=https://other.example', '/community/x'],
    'another address in the fragment' => ['/community/x#//other.example', '/community/x'],
    'an encoded letter in a slug' => ['/community/%78', '/community/x'],
    'the dashboard' => ['/dashboard', null],
    'the sign-in page' => ['/login', null],
    'the page of the address check' => ['/email/verify', null],
    'the address of an invitation' => ['/community-invitations/tz4a98xxat96iws9zmbrgj3a?expires=1&signature=abc', null],
    'an address of the API' => ['/api/v1/communities', null],
    'the settings of a community' => ['/community/x/settings', null],
    'the details of a community' => ['/community/x/about', null],
    'the members of a community' => ['/community/x/members', null],
    'the profile of a member' => ['/community/x/member/ada', null],
    'the messages of a member' => ['/community/x/messages', null],
    'the form of an event' => ['/community/x/space/y/events/new', null],
    'the edit form of an event' => ['/community/x/space/y/event/z/edit', null],
    'the home page' => ['/', null],
    'an address that only the fallback route answers' => ['/no-such-page', null],
    'an address under a page that only the fallback route answers' => ['/community/x/space/y/post/z/more', null],
    'an empty text' => ['', null],
    'a text of 3000 characters' => ['/community/'.str_repeat('x', 2989), null],
    'a slug that is longer than a slug of the application' => ['/community/'.str_repeat('x', Constants::MAX_LENGTH_SLUG + 21), null],
]);

test('a text that is not a plain page for visitors gives no landing, or a path that the application built', function (string $text, ?string $path) {
    expect(Landing::pathOf($text))->toBe($path);

    if ($path !== null) {
        expect($path)->toStartWith('/community/');
    }
})->with('texts that are not a plain page for visitors');

test('the link of the address check never lands outside of this instance', function (string $text, ?string $path) {
    landingCommunity('X', 'x');
    $grace = landingGrace();
    $root = URL::to('/');

    // The mail writes a landing only: the path that the application built, in the form of a link
    $link = landingLink($grace, $text);
    expect(landingValueOfTheLink($link))->toBe($path === null ? null : str_replace('/', '.', ltrim($path, '/')));

    $location = (string) $this->actingAs($grace)->get($link)->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith($root.'/')
        ->and($location)->toBe($path === null ? $root.'/dashboard?verified=1' : $root.$path)
        ->and($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with('texts that are not a plain page for visitors');

/*
 * The same texts as the value of a link with a good signature, in the form of a link ("." in the place of "/", without the
 * first slash) and as they are (the form of a path, which no link of the application has). The only text that is a landing
 * in the form of a link is the path without its first slash.
 */
test('a link of the address check with a good signature and a value that is not a landing lands on the dashboard', function (string $text) {
    landingCommunity('X', 'x');
    $grace = landingGrace();
    $root = URL::to('/');
    $value = str_replace('/', '.', str_starts_with($text, '/') ? substr($text, 1) : $text);
    $path = $text === 'community/x' ? '/community/x' : null;

    expect(Landing::pathOfLinkValue($value))->toBe($path)
        ->and(Landing::pathOfLinkValue($text))->toBeNull();

    $location = (string) $this->actingAs($grace)->get(landingSignedLink($grace, $value))->assertRedirect()->headers->get('Location');
    expect($location)->toBe($path === null ? $root.'/dashboard?verified=1' : $root.$path);

    $location = (string) $this->actingAs($grace)->get(landingSignedLink($grace, $text))->assertRedirect()->headers->get('Location');
    expect($location)->toBe($root.'/dashboard?verified=1')
        ->and($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with('texts that are not a plain page for visitors');

test('the value of a landing in a link reads back as a landing only in its plain form', function (string $value, ?string $path) {
    landingCommunity('X', 'x');
    $grace = landingGrace();
    $root = URL::to('/');

    expect(Landing::pathOfLinkValue($value))->toBe($path);

    $location = (string) $this->actingAs($grace)->get(landingSignedLink($grace, $value))->assertRedirect()->headers->get('Location');
    expect($location)->toBe($path === null ? $root.'/dashboard?verified=1' : $root.$path);
})->with([
    'the home of a community' => ['community.x', '/community/x'],
    'a space' => ['community.x.space.open-talk', '/community/x/space/open-talk'],
    'a post' => ['community.x.space.open-talk.post.a-first-post', '/community/x/space/open-talk/post/a-first-post'],
    'two dots: an empty part' => ['community..x', null],
    'two dots at the start' => ['..community.x', null],
    'two dots at the end' => ['community.x..', null],
    'two dots as a slug' => ['community...space.open-talk', null],
    'two dots that go up to the dashboard' => ['community.x.....dashboard', null],
    'a first dot' => ['.community.x', null],
    'a last dot' => ['community.x.', null],
    'only a dot' => ['.', null],
    'a path: the form that no link has' => ['/community/x', null],
    'a slash in the place of a dot' => ['community/x', null],
    'a slash inside a value' => ['community.x/space.open-talk', null],
    'a slash at the start' => ['/community.x', null],
    'an encoded slash' => ['community%2Fx', null],
    'an encoded slash in a slug' => ['community.x%2Fsettings', null],
    'an encoded slash, encoded again' => ['community%252Fx', null],
    'an encoded dot' => ['community%2Ex', null],
    'a backslash' => ['community\\x', null],
    'a capital letter' => ['community.X', null],
    'a space inside a value' => ['community.x y', null],
    'a line break inside a value' => ["community.x\ny", null],
    'an encoded zero byte' => ['community.x%00', null],
    'the settings of a community' => ['community.x.settings', null],
    'the details of a community' => ['community.x.about', null],
    'the form of an event' => ['community.x.space.open-talk.events.new', null],
    'the dashboard' => ['dashboard', null],
    'the sign-in page' => ['login', null],
    'an address of the API' => ['api.v1.communities', null],
    'another host' => ['other.example', null],
    'another host before the path' => ['other.example.community.x', null],
    'a full address' => ['https:..other.example.community.x', null],
    'an address under a page' => ['community.x.space.open-talk.post.a-first-post.more', null],
    'an empty value' => ['', null],
    'a slug that is longer than a slug of the application' => ['community.'.str_repeat('x', Constants::MAX_LENGTH_SLUG + 21), null],
    'a value of 3000 characters' => [str_repeat('community.', 300), null],
]);

test('the link of the address check writes the landing without a character that needs an escape', function (string $path, string $value) {
    $grace = landingGrace();

    $link = landingLink($grace, $path);
    $query = (string) parse_url($link, PHP_URL_QUERY);

    expect($query)->not->toContain('%')
        ->and($query)->toMatch('/\A[A-Za-z0-9=&.-]+\z/')
        ->and($query)->toContain('landing='.$value.'&')
        ->and(Landing::linkValueOf($path))->toBe($value)
        ->and(Landing::pathOfLinkValue($value))->toBe($path)
        ->and(URL::hasValidSignature(Request::create($link)))->toBeTrue();
})->with([
    'the home of a community' => [LANDING_HOME, 'community.harbour-of-landings'],
    'a space' => [LANDING_SPACE, 'community.harbour-of-landings.space.open-talk'],
    'a post' => [LANDING_POST, 'community.harbour-of-landings.space.open-talk.post.a-first-post'],
    'a page' => [LANDING_SPACE.'/page/a-first-page', 'community.harbour-of-landings.space.open-talk.page.a-first-page'],
    'a resource' => [LANDING_SPACE.'/resource/a-first-resource', 'community.harbour-of-landings.space.open-talk.resource.a-first-resource'],
    'an event' => [LANDING_SPACE.'/event/a-first-event', 'community.harbour-of-landings.space.open-talk.event.a-first-event'],
]);

test('a parameter of a landing is at most as long as the longest slug of the application', function () {
    // A slug of the application: Constants::MAX_LENGTH_SLUG characters, then "-" and a number when its parent has the same
    // slug already
    $limit = Constants::MAX_LENGTH_SLUG + 20;
    $longest = str_repeat('x', $limit);

    expect($limit)->toBe(148)
        ->and(Landing::pathOf('/community/'.$longest))->toBe('/community/'.$longest)
        ->and(Landing::pathOf('/community/'.$longest.'x'))->toBeNull()
        ->and(Landing::pathOf(LANDING_SPACE.'/post/'.$longest))->toBe(LANDING_SPACE.'/post/'.$longest)
        ->and(Landing::pathOf(LANDING_SPACE.'/post/'.$longest.'x'))->toBeNull()
        ->and(Landing::pathOf('/community/'.$longest.'/space/open-talk'))->toBe('/community/'.$longest.'/space/open-talk')
        ->and(Landing::pathOf('/community/'.$longest.'x/space/open-talk'))->toBeNull()
        ->and(Landing::pathOfLinkValue('community.'.$longest))->toBe('/community/'.$longest)
        ->and(Landing::pathOfLinkValue('community.'.$longest.'x'))->toBeNull();
});

test('the longest slug that the application makes is a landing', function () {
    $c = landingCommunity();
    $title = str_repeat('a', Constants::MAX_LENGTH_SLUG + 40);
    assert($c['publicSpace'] !== null);

    // Two posts of one space with the same long title: the slug is cut, and the second one gets a number
    $first = Post::factory()->inSpace($c['publicSpace'])->create(['author_id' => $c['member']->id, 'title' => $title]);
    $second = Post::factory()->inSpace($c['publicSpace'])->create(['author_id' => $c['member']->id, 'title' => $title]);

    expect($first->slug)->toBe(str_repeat('a', Constants::MAX_LENGTH_SLUG))
        ->and($second->slug)->toBe(str_repeat('a', Constants::MAX_LENGTH_SLUG).'-2')
        ->and(Landing::pathOf(LANDING_SPACE.'/post/'.$second->slug))->toBe(LANDING_SPACE.'/post/'.$second->slug)
        ->and(Landing::pathOfLinkValue(Landing::linkValueOf(LANDING_SPACE.'/post/'.$second->slug)))->toBe(LANDING_SPACE.'/post/'.$second->slug);
});

test('a landing leaves the route of the running request as it is', function () {
    $route = Route::getRoutes()->getByName('communities.show');
    $route?->bind(Request::create('/community/first-community'));

    expect(Landing::pathOf('/community/second-community'))->toBe('/community/second-community')
        ->and($route?->parameter('communitySlug'))->toBe('first-community');
});

test('a landing is the same when the routes are cached, as in production', function () {
    // What `php artisan route:cache` does, for the routes of this test
    $routes = Route::getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();
    foreach ($routes as $route) {
        $route->prepareForSerialization();
    }
    Route::setCompiledRoutes($routes->compile());
    expect(Route::getRoutes())->toBeInstanceOf(CompiledRouteCollection::class);

    $current = Route::getRoutes()->getByName('communities.show');
    $current?->bind(Request::create('/community/first-community'));

    expect(Landing::pathOf('https://other.example'.LANDING_HOME.'?page=2#top'))->toBe(LANDING_HOME)
        ->and(Landing::pathOf(LANDING_SPACE))->toBe(LANDING_SPACE)
        ->and(Landing::pathOf(LANDING_POST))->toBe(LANDING_POST)
        ->and(Landing::pathOf(LANDING_SPACE.'/page/a-first-page'))->toBe(LANDING_SPACE.'/page/a-first-page')
        ->and(Landing::pathOf(LANDING_SPACE.'/resource/a-first-resource'))->toBe(LANDING_SPACE.'/resource/a-first-resource')
        ->and(Landing::pathOf(LANDING_SPACE.'/event/a-first-event'))->toBe(LANDING_SPACE.'/event/a-first-event')
        ->and(Landing::pathOf(LANDING_HOME.'/settings'))->toBeNull()
        ->and(Landing::pathOf(LANDING_HOME.'/about'))->toBeNull()
        ->and(Landing::pathOf(LANDING_SPACE.'/events/new'))->toBeNull()
        ->and(Landing::pathOf('/community/x%2Fsettings'))->toBeNull()
        ->and(Landing::pathOf('/community/X'))->toBeNull()
        ->and(Landing::pathOf('/dashboard'))->toBeNull()
        ->and(Landing::pathOf('/'))->toBeNull()
        ->and(Landing::pathOf('/no-such-page'))->toBeNull()
        ->and(Landing::pathOf('/api/v1/communities'))->toBeNull()
        ->and($current?->parameter('communitySlug'))->toBe('first-community');
});

test('without a session there is no landing, and nothing to remember', function () {
    $grace = landingGrace();
    landingCommunity();

    expect(Landing::fromSession())->toBeNull();
    Landing::remember(Request::create('/'), $grace, LANDING_HOME);

    // A command or a job sends the mail: its link is the one of before
    expect(landingOfTheLink(landingLink($grace, Landing::fromSession())))->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// The mail of the address check
// ---------------------------------------------------------------------------------------------------------------------

test('the mail of the address check keeps its words, its life and its queue', function () {
    $grace = landingGrace();

    $before = (new VerifyEmail)->toMail($grace);
    $after = (new VerifyEmailAddress(LANDING_HOME))->toMail($grace);

    expect($after->subject)->toBe($before->subject)
        ->and($after->introLines)->toBe($before->introLines)
        ->and($after->outroLines)->toBe($before->outroLines)
        ->and($after->actionText)->toBe($before->actionText)
        ->and(new VerifyEmailAddress(LANDING_HOME))->not->toBeInstanceOf(ShouldQueue::class)
        ->and(app(VerifyEmailResponseContract::class))->toBeInstanceOf(VerifyEmailResponse::class);

});

test('the link of the address check with a landing lives 60 minutes, as the link without one', function () {
    landingCommunity();
    $grace = landingGrace();
    $this->travelTo('2026-03-02 10:00:00');

    // After 59 minutes it works
    $link = landingLink($grace, LANDING_HOME);
    $this->travel(59)->minutes();
    $this->actingAs($grace)->get($link)->assertRedirect(URL::to(LANDING_HOME));
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();

    // After 61 minutes it is refused
    $grace->forceFill(['email_verified_at' => null])->save();
    session()->forget('url.intended');
    $link = landingLink($grace, LANDING_HOME);
    $this->travel(61)->minutes();
    $this->actingAs($grace->fresh())->get($link)->assertForbidden();
    expect($grace->fresh()?->hasVerifiedEmail())->toBeFalse();
});

test('a sign-up from a page of a community: the link of the mail holds the landing, and its signature is valid', function (string $page) {
    landingCommunity();

    landingNewBrowser();
    $this->get($page)->assertOk();
    landingSignUp();
    $link = landingLinkOfTheMail();

    expect(landingOfTheLink($link))->toBe($page)
        ->and(URL::hasValidSignature(Request::create($link)))->toBeTrue();
})->with([
    'the public page of the community' => [LANDING_HOME],
    'a public space' => [LANDING_SPACE],
    'a public post' => [LANDING_POST],
]);

test('a sign-up from an address that sent the visitor to the sign-in page: the link of the mail holds that address', function () {
    landingCommunity();

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    landingSignUp();

    expect(landingOfTheLink(landingLinkOfTheMail()))->toBe(LANDING_MEMBERS_SPACE);
});

test('a sign-up from another place: the link of the mail holds no landing', function (string $from) {
    $c = landingCommunity();
    $invitation = $c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $addresses = [
        'the dashboard' => route('dashboard'),
        'the link of an invitation' => URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]),
        'the details of a community' => route('communities.about', ['communitySlug' => 'harbour-of-landings']),
    ];

    landingNewBrowser();
    if (isset($addresses[$from])) {
        $this->get($addresses[$from])->assertRedirect(route('login'));
    }
    landingSignUp();
    $link = landingLinkOfTheMail();

    expect($link)->toContain('/email/verify/')
        ->and(landingOfTheLink($link))->toBeNull()
        ->and(URL::hasValidSignature(Request::create($link)))->toBeTrue();
})->with(['the dashboard', 'the link of an invitation', 'the details of a community', 'nothing']);

test('"Resend verification email" holds the landing of its session, and none without one', function () {
    landingCommunity();
    $grace = landingGrace();

    $this->actingAs($grace)->withSession(['url.intended' => URL::to(LANDING_POST)])->post('/email/verification-notification')->assertSessionHasNoErrors();
    expect(landingOfTheLink(landingLinkOfTheMail()))->toBe(LANDING_POST);

    $this->actingAs($grace)->withSession(['url.intended' => route('dashboard')])->post('/email/verification-notification')->assertSessionHasNoErrors();
    expect(landingLinkOfTheMail())->toContain('/email/verify/')
        ->and(landingOfTheLink(landingLinkOfTheMail()))->toBeNull();
});

test('a correction of an address that was never verified holds the landing of its session, and a verified member gets no address check', function () {
    $c = landingCommunity();
    $grace = landingGrace();

    // An address that was never verified changes at once, and the address check goes to it (#1706, ADR 0058)
    $this->actingAs($grace)->withSession(['url.intended' => URL::to(LANDING_SPACE)])
        ->put('/user/profile-information', ['name' => $grace->name, 'email' => 'first-new-address@example.com', 'current_password' => 'password'])->assertSessionHasNoErrors();
    expect($grace->fresh()?->email)->toBe('first-new-address@example.com')
        ->and($grace->fresh()?->hasVerifiedEmail())->toBeFalse()
        ->and(landingOfTheLink(landingLinkOfTheMail()))->toBe(LANDING_SPACE);

    // A verified member keeps the verified address: the new one waits for the link of its own mail, not the address check
    $member = $c['member'];
    $member->forceFill(['email' => 'member-of-the-harbour@example.com'])->save();
    session()->forget('url.intended');
    $this->actingAs($member->fresh())
        ->put('/user/profile-information', ['name' => $member->name, 'email' => 'second-new-address@example.com', 'current_password' => 'password'])->assertSessionHasNoErrors();
    expect($member->fresh()?->hasVerifiedEmail())->toBeTrue()
        ->and($member->fresh()?->email)->toBe('member-of-the-harbour@example.com')
        ->and(landingAddressOfTheMail('/user/email-change/'))->not->toBe('')
        ->and(landingLinkOfTheMail())->toBe('');
});

// ---------------------------------------------------------------------------------------------------------------------
// The address check
// ---------------------------------------------------------------------------------------------------------------------

test('the link of the address check, opened in another browser, lands on the page of the community that the person came from', function () {
    $c = landingCommunity();
    $url = URL::to(LANDING_HOME);

    // The first browser: a visitor reads the public page of the community and signs up
    landingNewBrowser();
    $this->get($url)->assertOk();
    $grace = landingSignUp();
    $link = landingLinkOfTheMail();
    expect($link)->not->toBe('');

    // Another browser: the link asks for a sign-in first, then it verifies the address and lands in the community
    landingNewBrowser();
    $this->get($link)->assertRedirect(route('login'));
    $this->post('/login', ['email' => 'grace@example.com', 'password' => LANDING_PASSWORD])->assertRedirect($link);
    $this->get($link)->assertRedirect($url);
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();

    // The public page, with "Join": the person presses it, nothing joined by itself
    app('auth')->forgetGuards();
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Public')->where('community.join_policy', 'open')->where('community.is_member', false));
    expect(landingIsMember($grace, $c['community']))->toBeFalse();
});

test('the link of the address check, opened in another browser, lands on the post that the person came from', function () {
    landingCommunity();

    landingNewBrowser();
    $this->get(LANDING_POST)->assertOk();
    landingSignUp();
    $link = landingLinkOfTheMail();

    landingNewBrowser();
    $this->get($link)->assertRedirect(route('login'));
    $this->post('/login', ['email' => 'grace@example.com', 'password' => LANDING_PASSWORD])->assertRedirect($link);
    $this->get($link)->assertRedirect(URL::to(LANDING_POST));

    app('auth')->forgetGuards();
    $this->get(LANDING_POST)->assertOk()->assertInertia(fn ($page) => $page->component('Posts/Show'));
});

test('the session wins over the link of the address check', function () {
    landingCommunity();
    $other = landingCommunity('Second Harbour', 'second-harbour');
    $invitation = $other['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $invitationLink = URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);
    $grace = landingGrace();
    $link = landingLink($grace, LANDING_HOME);

    // A page of another community in this browser: it is the newer intent
    $second = URL::to('/community/second-harbour/space/open-talk').'?page=2';
    $this->actingAs($grace)->withSession(['url.intended' => $second])->get($link)->assertRedirect($second);

    // The link of an invitation in this browser: the flow of the invitation goes on
    $this->actingAs($grace)->withSession(['url.intended' => $invitationLink])->get($link)->assertRedirect($invitationLink);

    // Another address in this browser, for example the dashboard: the link fills the gap
    $this->actingAs($grace)->withSession(['url.intended' => route('dashboard')])->get($link)->assertRedirect(URL::to(LANDING_HOME));
    $this->actingAs($grace)->withSession(['url.intended' => route('verification.notice')])->get($link)->assertRedirect(URL::to(LANDING_HOME));
});

test('a link of the address check with a changed landing is refused, and the address is not verified', function () {
    landingCommunity();
    landingCommunity('Second Harbour', 'second-harbour');
    $grace = landingGrace();
    $link = landingLink($grace, LANDING_HOME);
    expect($link)->toContain('harbour-of-landings');

    expect($link)->toContain('landing=community.harbour-of-landings&');

    // Another community, a longer page, no landing, a second landing, a landing on a link that had none
    $this->actingAs($grace)->get(str_replace('harbour-of-landings', 'second-harbour', $link))->assertForbidden();
    $this->actingAs($grace)->get(str_replace('community.harbour-of-landings', 'community.harbour-of-landings.space.members-talk', $link))->assertForbidden();
    $this->actingAs($grace)->get(preg_replace('~landing=[^&]*&~', '', $link))->assertForbidden();
    $this->actingAs($grace)->get($link.'&landing=community.second-harbour')->assertForbidden();
    $this->actingAs($grace)->get(landingLink($grace, null).'&landing=community.second-harbour')->assertForbidden();

    expect($grace->fresh()?->hasVerifiedEmail())->toBeFalse();
});

test('a link of the address check with its landing written in another form is refused, and the address is not verified', function () {
    landingCommunity();
    $grace = landingGrace();
    $link = landingLink($grace, LANDING_HOME);
    expect($link)->toContain('landing=community.harbour-of-landings&');

    // The same landing in the form of a path, with and without escapes
    $this->actingAs($grace)->get(str_replace('landing=community.harbour-of-landings', 'landing=/community/harbour-of-landings', $link))->assertForbidden();
    $this->actingAs($grace)->get(str_replace('landing=community.harbour-of-landings', 'landing=%2Fcommunity%2Fharbour-of-landings', $link))->assertForbidden();
    $this->actingAs($grace)->get(str_replace('landing=community.harbour-of-landings', 'landing=community/harbour-of-landings', $link))->assertForbidden();

    expect($grace->fresh()?->hasVerifiedEmail())->toBeFalse();
});

test('a link of the address check without a landing lands on the dashboard', function () {
    landingCommunity();
    $grace = landingGrace();

    $this->actingAs($grace)->get(landingLink($grace, null))->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
});

test('a request for JSON of the link of the address check gets 204, and the session keeps no landing', function () {
    landingCommunity();
    $grace = landingGrace();

    $this->actingAs($grace)->getJson(landingLink($grace, LANDING_HOME))->assertNoContent()->assertSessionMissing('url.intended');
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
});

test('a second click on the link of the address check gets the same landing', function () {
    landingCommunity();
    $grace = landingGrace();
    $link = landingLink($grace, LANDING_POST);

    $this->actingAs($grace)->get($link)->assertRedirect(URL::to(LANDING_POST));
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($grace->fresh())->get($link)->assertRedirect(URL::to(LANDING_POST));
});

// ---------------------------------------------------------------------------------------------------------------------
// Where the person lands: the page answers with its own rules
// ---------------------------------------------------------------------------------------------------------------------

test('a person who is a member already lands on the page of the link', function (string $landing, string $component) {
    $c = landingCommunity();
    $grace = landingGrace();
    $c['community']->users()->attach($grace, ['role' => KnowiiCommunityMemberRole::Member]);

    $this->actingAs($grace)->get(landingLink($grace, $landing))->assertRedirect(URL::to($landing));

    app('auth')->forgetGuards();
    $this->actingAs($grace->fresh())->get($landing)->assertOk()->assertInertia(fn ($page) => $page->component($component));
})->with([
    'the home of the community' => [LANDING_HOME, 'Communities/Show'],
    'a members-only space' => [LANDING_MEMBERS_SPACE, 'Spaces/Show'],
]);

test('an open community with a public page: the public page with "Join", and the person is not a member after the landing', function () {
    $c = landingCommunity();
    $grace = landingGrace();

    $this->actingAs($grace)->get(landingLink($grace, LANDING_HOME))->assertRedirect(URL::to(LANDING_HOME));

    app('auth')->forgetGuards();
    $this->actingAs($grace->fresh())->get(LANDING_HOME)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/Public')->where('community.join_policy', 'open')->where('community.is_member', false));
    expect(landingIsMember($grace, $c['community']))->toBeFalse();
});

test('a community by invitation with a public page: the public page, without "Join"', function () {
    $c = landingCommunity();
    $c['community']->forceFill(['join_policy' => KnowiiCommunityJoinPolicy::Invitation])->save();
    $grace = landingGrace();

    $this->actingAs($grace)->get(landingLink($grace, LANDING_SPACE))->assertRedirect(URL::to(LANDING_SPACE));

    app('auth')->forgetGuards();
    $this->actingAs($grace->fresh())->get(LANDING_HOME)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/Public')->where('community.join_policy', 'invitation'));
    expect(landingIsMember($grace, $c['community']))->toBeFalse();
});

test('a space that is not for this person, or that is gone: the home of the community', function (string $case) {
    $c = landingCommunity();
    $grace = landingGrace();
    $landing = match ($case) {
        'a members-only space' => LANDING_MEMBERS_SPACE,
        'a post of a members-only space' => LANDING_MEMBERS_SPACE.'/post/a-private-post',
        'a space that does not exist' => LANDING_HOME.'/space/no-such-space',
        default => LANDING_POST,
    };
    $link = landingLink($grace, $landing);
    if ($case === 'a space that was deleted') {
        $c['post']?->delete();
        $c['publicSpace']?->delete();
        // The community keeps a public page
        Space::factory()->inGroup(SpaceGroup::query()->where('community_id', $c['community']->id)->firstOrFail())
            ->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'Open news', 'slug' => 'open-news']);
    }
    if ($case === 'a space that became members-only') {
        $c['publicSpace']?->forceFill(['visibility' => KnowiiSpaceVisibility::Members])->save();
        Space::factory()->inGroup(SpaceGroup::query()->where('community_id', $c['community']->id)->firstOrFail())
            ->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'Open news', 'slug' => 'open-news']);
    }

    $this->actingAs($grace)->get($link)->assertRedirect(URL::to(LANDING_HOME));

    app('auth')->forgetGuards();
    $this->actingAs($grace->fresh())->get(LANDING_HOME)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/Public')->where('community.join_policy', 'open'));
})->with(['a members-only space', 'a post of a members-only space', 'a space that does not exist', 'a space that was deleted', 'a space that became members-only']);

test('a community without a public page: its home, which answers 403 with the page without access', function (string $policy) {
    $c = landingCommunity(publicPage: false);
    $c['community']->forceFill(['join_policy' => $policy])->save();
    $grace = landingGrace();

    $this->actingAs($grace)->get(landingLink($grace, $policy === 'open' ? LANDING_HOME : LANDING_MEMBERS_SPACE))->assertRedirect(URL::to(LANDING_HOME));

    app('auth')->forgetGuards();
    $this->actingAs($grace->fresh())->get(LANDING_HOME)->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Communities/NoAccess')->where('community', null));
    expect(landingIsMember($grace, $c['community']))->toBeFalse();
})->with(['open', 'invitation']);

test('a community that is gone, or a personal one: the dashboard', function (string $case) {
    $c = landingCommunity();
    $grace = landingGrace();
    $personal = $c['owner']->ownedCommunities()->save(Community::factory()->make(['name' => 'Quiet Corner', 'slug' => 'quiet-corner', 'visibility' => KnowiiCommunityVisibility::Personal]));
    $link = landingLink($grace, match ($case) {
        'a personal community' => '/community/quiet-corner',
        'the personal community of the person' => '/community/'.$grace->ownedCommunities()->firstOrFail()->slug,
        'a community that never existed' => '/community/no-such-community/space/open-talk',
        default => LANDING_POST,
    });
    expect($personal)->not->toBeFalse();
    if ($case === 'a community that was deleted') {
        $c['community']->delete();
    }
    if ($case === 'a community whose slug changed') {
        $c['community']->forceFill(['slug' => 'harbour-of-arrivals'])->save();
    }

    $this->actingAs($grace)->get($link)->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect($grace->fresh()?->hasVerifiedEmail())->toBeTrue();
})->with(['a personal community', 'the personal community of the person', 'a community that never existed', 'a community that was deleted', 'a community whose slug changed']);

// ---------------------------------------------------------------------------------------------------------------------
// The sign-in from a page of a community
// ---------------------------------------------------------------------------------------------------------------------

test('a sign-in with a password and the second factor comes back to the page', function () {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['outsider']);

    landingNewBrowser();
    $this->get(LANDING_POST)->assertOk();
    $this->post('/login', ['email' => $c['outsider']->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'))
        ->assertSessionMissing(LANDING_HELD);
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(URL::to(LANDING_POST));
    $this->assertAuthenticatedAs($c['outsider']);
});

test('a sign-in with a password and the second factor answers as before', function (string $proof) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['member']);
    $input = $proof === 'a recovery code' ? ['recovery_code' => 'recovery-one'] : ['code' => (new Google2FA)->getCurrentOtp($secret)];
    expect(app(TwoFactorLoginResponseContract::class))->toBeInstanceOf(TwoFactorLoginResponse::class);

    // No page of a community in the session, and no sign-in link: the dashboard
    landingNewBrowser();
    $this->post('/login', ['email' => $c['member']->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'))
        ->assertSessionMissing('url.intended')->assertSessionMissing(LANDING_HELD);
    $this->assertGuest();

    $this->post('/two-factor-challenge', $input)->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended');
    $this->assertAuthenticatedAs($c['member']);
})->with(['the code', 'a recovery code']);

test('a request for JSON of the challenge of the second factor gets 204, as before', function (string $how) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['member']);

    landingNewBrowser();
    if ($how === 'after a sign-in link') {
        $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
        $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
        $token = landingTokenOfTheMail();
        landingNewBrowser();
        $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'))->assertSessionHas(LANDING_HELD, LANDING_MEMBERS_SPACE);
    } else {
        $this->post('/login', ['email' => $c['member']->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    }

    // Nothing stays in the session, and the session gets no intended address
    $this->postJson('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertNoContent()
        ->assertSessionMissing(LANDING_HELD)->assertSessionMissing('url.intended');
    $this->assertAuthenticatedAs($c['member']);
})->with(['after a password', 'after a sign-in link']);

test('a sign-in link in the same browser comes back to the page, also for a person who is not a member', function () {
    $c = landingCommunity();

    landingNewBrowser();
    $this->get(LANDING_POST)->assertOk();
    $this->post('/login/link', ['email' => $c['outsider']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // The session of this browser holds the page: the person read it here
    $this->get('/login/link/'.$token)->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(URL::to(LANDING_POST));
    $this->assertAuthenticatedAs($c['outsider']);
    expect(landingIsMember($c['outsider'], $c['community']))->toBeFalse();
});

test('a sign-in link of a member, opened in another browser, lands on the page that the person came from', function (string $who, string $page) {
    $c = landingCommunity();

    landingNewBrowser();
    $this->get($page);
    $this->post('/login/link', ['email' => $c[$who]->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->get('/login/link/'.$token)->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(URL::to($page))->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c[$who]);
})->with([
    'a member, from a public post' => ['member', LANDING_POST],
    'a member, from a members-only space' => ['member', LANDING_MEMBERS_SPACE],
    'the owner, from the home of the community' => ['owner', LANDING_HOME],
    'the owner, from a members-only space' => ['owner', LANDING_MEMBERS_SPACE],
]);

test('a sign-in link of an account that is not a member, opened in another browser, lands on the dashboard', function (string $page) {
    $c = landingCommunity();

    // A link that was asked from a public page of an open community, for the address of an account that did not join it
    landingNewBrowser();
    $this->get($page)->assertOk();
    $this->post('/login/link', ['email' => $c['outsider']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->get('/login/link/'.$token)->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended');
    $this->assertAuthenticatedAs($c['outsider']);
    expect(landingIsMember($c['outsider'], $c['community']))->toBeFalse();
})->with([
    'the public page of the community' => [LANDING_HOME],
    'a public space' => [LANDING_SPACE],
    'a public post' => [LANDING_POST],
]);

test('a member who is removed from the community after the sign-in link was sent lands on the dashboard', function (string $page) {
    $c = landingCommunity();

    // The account is a member when the link is asked and sent
    landingNewBrowser();
    $this->get($page);
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();
    expect(landingIsMember($c['member'], $c['community']))->toBeTrue();

    // Removed before the link is used: the member rule is checked when the link is used, not when it is sent
    $c['community']->users()->detach($c['member']);

    landingNewBrowser();
    $this->get('/login/link/'.$token)->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended')->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c['member']);
    expect(landingIsMember($c['member'], $c['community']))->toBeFalse();
})->with([
    'a public post' => [LANDING_POST],
    'a members-only space' => [LANDING_MEMBERS_SPACE],
]);

test('a person who joins the community after the sign-in link was sent lands on the page', function (string $page) {
    $c = landingCommunity();

    // The account is not a member when the link is asked and sent
    landingNewBrowser();
    $this->get($page);
    $this->post('/login/link', ['email' => $c['outsider']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();
    expect(landingIsMember($c['outsider'], $c['community']))->toBeFalse();

    // A member before the link is used: the member rule is checked when the link is used, not when it is sent
    $c['community']->users()->attach($c['outsider'], ['role' => KnowiiCommunityMemberRole::Member]);

    landingNewBrowser();
    $this->get('/login/link/'.$token)->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(URL::to($page))->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c['outsider']);
})->with([
    'a public post' => [LANDING_POST],
    'a members-only space' => [LANDING_MEMBERS_SPACE],
]);

test('a sign-in link with the second factor, opened in another browser, lands on the page after the code', function (string $proof) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['member']);
    $input = $proof === 'a recovery code' ? ['recovery_code' => 'recovery-one'] : ['code' => (new Google2FA)->getCurrentOtp($secret)];

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post('/two-factor-challenge', $input)->assertRedirect(URL::to(LANDING_MEMBERS_SPACE))->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c['member']);
})->with(['the code', 'a recovery code']);

test('before the code of the second factor, the session holds only the path of the link, and no intended address', function (string $who, string $page, string $after) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c[$who]);
    $group = SpaceGroup::query()->where('community_id', $c['community']->id)->firstOrFail();
    // A space for access groups: the member is in none of them
    Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups, 'name' => 'Members room', 'slug' => 'members-room']);

    landingNewBrowser();
    $this->get($page);
    $this->post('/login/link', ['email' => $c[$who]->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // The link is used: the path is the one of the link, whatever the account may read, and nothing is chosen yet
    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'))
        ->assertSessionMissing('url.intended')
        ->assertSessionHas(LANDING_HELD, $page)
        ->assertSessionHas('login.id', $c[$who]->id);
    $this->assertGuest();
    $this->get('/two-factor-challenge')->assertOk()->assertSessionMissing('url.intended')->assertSessionHas(LANDING_HELD, $page);

    // The code is accepted: the landing is chosen for this account
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect($after === 'the dashboard' ? route('dashboard') : URL::to($after))
        ->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c[$who]);
})->with([
    'a member, a members-only space' => ['member', LANDING_MEMBERS_SPACE, LANDING_MEMBERS_SPACE],
    'a member, a space that does not exist' => ['member', LANDING_HOME.'/space/no-such-space', LANDING_HOME],
    'a member, a space of a group that the member is not in' => ['member', LANDING_GROUPS_SPACE, LANDING_HOME],
    'the owner, a space of a group' => ['owner', LANDING_GROUPS_SPACE, LANDING_GROUPS_SPACE],
    'an account that is not a member, a public post' => ['outsider', LANDING_POST, 'the dashboard'],
    'an account that is not a member, a members-only space' => ['outsider', LANDING_MEMBERS_SPACE, 'the dashboard'],
]);

test('a wrong code of the second factor keeps the landing of the sign-in link for the next try', function () {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['member']);

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'));
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code')
        ->assertSessionMissing('url.intended')->assertSessionHas(LANDING_HELD, LANDING_MEMBERS_SPACE);
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(URL::to(LANDING_MEMBERS_SPACE));
    $this->assertAuthenticatedAs($c['member']);
});

test('the landing that a session kept for a challenge is checked for the account that passes the challenge', function (string $who, string $after) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c[$who]);

    // A session that kept the path of a link, and the challenge of this account
    landingNewBrowser();
    $this->withSession(['login.id' => $c[$who]->id, 'login.remember' => false, LANDING_HELD => LANDING_MEMBERS_SPACE])
        ->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect($after === 'the dashboard' ? route('dashboard') : URL::to($after))
        ->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c[$who]);
})->with([
    'an account that is not a member' => ['outsider', 'the dashboard'],
    'the owner' => ['owner', LANDING_MEMBERS_SPACE],
]);

test('a member who is removed between the use of the sign-in link and the code of the second factor lands on the dashboard', function (string $proof) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['member']);

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // The link is used while the account is a member: the session holds the path, and nothing is chosen yet
    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'))
        ->assertSessionMissing('url.intended')->assertSessionHas(LANDING_HELD, LANDING_MEMBERS_SPACE);
    $this->assertGuest();
    expect(landingIsMember($c['member'], $c['community']))->toBeTrue();

    // Removed before the code: the member rule is checked when the code is accepted, not when the link is used
    $c['community']->users()->detach($c['member']);

    $input = $proof === 'a recovery code' ? ['recovery_code' => 'recovery-one'] : ['code' => (new Google2FA)->getCurrentOtp($secret)];
    $this->post('/two-factor-challenge', $input)->assertRedirect(route('dashboard'))
        ->assertSessionMissing('url.intended')->assertSessionMissing(LANDING_HELD);
    $this->assertAuthenticatedAs($c['member']);
})->with(['the code', 'a recovery code']);

test('a sign-in with a password that replaces the challenge of a sign-in link gets no landing of that link', function () {
    $c = landingCommunity();
    landingSecondFactor($c['member']);
    $secret = landingSecondFactor($c['owner']);

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // The link of the member starts a challenge, and nobody gives the code
    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'))->assertSessionHas(LANDING_HELD, LANDING_MEMBERS_SPACE);

    // The owner, who may read the same space, signs in with a password in the same browser: the answer of before
    $this->post('/login', ['email' => $c['owner']->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'))
        ->assertSessionMissing(LANDING_HELD)->assertSessionMissing('url.intended');
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($c['owner']);
});

test('a sign-in link that replaces the challenge of another sign-in link gets no landing of that link', function (string $secondFactor) {
    $c = landingCommunity();
    landingSecondFactor($c['member']);
    $secret = $secondFactor === 'with the second factor' ? landingSecondFactor($c['owner']) : null;

    landingNewBrowser();
    $this->get(LANDING_MEMBERS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // The link of the owner was asked from no page of a community
    landingNewBrowser();
    $this->post('/login/link', ['email' => $c['owner']->email])->assertRedirect(route('login'));
    $ownerToken = landingTokenOfTheMail();

    // The link of the member starts a challenge, and nobody gives the code
    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('two-factor.login'))->assertSessionHas(LANDING_HELD, LANDING_MEMBERS_SPACE);

    // The owner, who may read the same space, uses the other link in the same browser
    if ($secret === null) {
        $this->post('/login/link/'.$ownerToken)->assertRedirect(route('dashboard'))->assertSessionMissing(LANDING_HELD);
    } else {
        $this->post('/login/link/'.$ownerToken)->assertRedirect(route('two-factor.login'))->assertSessionMissing(LANDING_HELD);
        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('dashboard'));
    }
    $this->assertAuthenticatedAs($c['owner']);
})->with(['without the second factor', 'with the second factor']);

test('a sign-in link of a member who may not read the space lands on the home of the community', function () {
    $c = landingCommunity();
    $group = SpaceGroup::query()->where('community_id', $c['community']->id)->firstOrFail();
    Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Groups, 'name' => 'Members room', 'slug' => 'members-room']);

    landingNewBrowser();
    $this->get(LANDING_GROUPS_SPACE)->assertRedirect(route('login'));
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(URL::to(LANDING_HOME));
    $this->assertAuthenticatedAs($c['member']);
});

test('a session that holds a page of a community wins over the landing of the sign-in link', function () {
    $c = landingCommunity();
    landingCommunity('Second Harbour', 'second-harbour');

    landingNewBrowser();
    $this->get(LANDING_POST)->assertOk();
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    landingNewBrowser();
    $this->get('/community/second-harbour')->assertOk();
    $this->post('/login/link/'.$token)->assertRedirect(URL::to('/community/second-harbour'));
    $this->assertAuthenticatedAs($c['member']);
});

test('a session that holds the link of an invitation wins over the landing of the sign-in link', function () {
    $c = landingCommunity();
    $other = landingCommunity('Second Harbour', 'second-harbour');
    $invitation = $other['community']->communityInvitations()->create(['email' => $c['member']->email, 'role' => KnowiiCommunityMemberRole::Member]);
    $invitationLink = URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);

    landingNewBrowser();
    $this->get(LANDING_POST)->assertOk();
    $this->post('/login/link', ['email' => $c['member']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();

    // Another browser: the member opens the invitation first, then the sign-in link. The flow of the invitation goes on
    landingNewBrowser();
    $this->get($invitationLink)->assertRedirect(route('login'));
    $this->post('/login/link/'.$token)->assertRedirect($invitationLink);
    $this->assertAuthenticatedAs($c['member']);
});

test('a sign-in link that was asked from another place lands on the dashboard', function () {
    $c = landingCommunity();

    landingNewBrowser();
    $this->post('/login/link', ['email' => $c['outsider']->email])->assertRedirect(route('login'));
    $token = landingTokenOfTheMail();
    expect(app(ConsumesMagicLinks::class)->peek($token)?->is($c['outsider']))->toBeTrue();

    landingNewBrowser();
    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($c['outsider']);
});

test('a sign-in link that was stored without a landing still signs in, and lands on the dashboard', function () {
    $c = landingCommunity();
    // The entry of a link, without the entry of a landing beside it: the account and the HMAC of the address that the link
    // was sent to, under the hash of the token (ADR 0058)
    $token = str_repeat('k', 64);
    Cache::put('magic-link:'.hash('sha256', $token), ['user' => $c['outsider']->id, 'address' => hash_hmac('sha256', $c['outsider']->email, config()->string('app.key'))], now()->addMinutes(MagicLinks::VALIDITY_MINUTES));

    $this->post('/login/link/'.$token)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($c['outsider']);
});

test('a sign-in link for an unknown address stores no landing', function () {
    landingCommunity();
    Cache::spy();

    app(SendsMagicLinks::class)->send(['email' => 'nobody@example.com'], LANDING_HOME);

    Cache::shouldNotHaveReceived('put');
});

test('the landing of a sign-in link is read once, and it is gone after the link is used', function () {
    $c = landingCommunity();
    $links = app(ConsumesMagicLinks::class);

    app(SendsMagicLinks::class)->send(['email' => $c['outsider']->email], LANDING_HOME);
    $token = landingTokenOfTheMail();
    expect($links->pullLanding($token))->toBe(LANDING_HOME)
        ->and($links->pullLanding($token))->toBeNull()
        // The link itself is not used up by the read of its landing
        ->and($links->peek($token)?->is($c['outsider']))->toBeTrue();

    app(SendsMagicLinks::class)->send(['email' => $c['outsider']->email], LANDING_POST);
    $token = landingTokenOfTheMail();
    expect($links->consume($token)?->is($c['outsider']))->toBeTrue()
        ->and($links->pullLanding($token))->toBeNull();
});

test('the landing of a sign-in link is gone after 15 minutes, with the link', function () {
    $c = landingCommunity();
    $links = app(ConsumesMagicLinks::class);

    app(SendsMagicLinks::class)->send(['email' => $c['outsider']->email], LANDING_HOME);
    $token = landingTokenOfTheMail();

    $this->travel(MagicLinks::VALIDITY_MINUTES - 1)->minutes();
    expect($links->peek($token))->not->toBeNull();

    $this->travel(2)->minutes();
    expect($links->peek($token))->toBeNull()
        ->and($links->pullLanding($token))->toBeNull();
});

test('a landing of the cache that is not a page of this instance is not followed', function (string $text) {
    $c = landingCommunity();
    $secret = landingSecondFactor($c['owner']);

    // A member: a page of the community would be followed
    app(SendsMagicLinks::class)->send(['email' => $c['member']->email], $text);
    $this->post('/login/link/'.landingTokenOfTheMail())->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended');

    // With the second factor: the session keeps nothing of it
    landingNewBrowser();
    app(SendsMagicLinks::class)->send(['email' => $c['owner']->email], $text);
    $this->post('/login/link/'.landingTokenOfTheMail())->assertRedirect(route('two-factor.login'))->assertSessionMissing(LANDING_HELD);
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('dashboard'));
})->with([
    'an address of another host' => ['https://other.example/dashboard'],
    'the settings of the community' => [LANDING_HOME.'/settings'],
    'an address without a scheme' => ['//other.example'],
]);
