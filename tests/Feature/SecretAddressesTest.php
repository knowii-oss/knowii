<?php

use App\ApiRoutes;
use App\Contracts\Auth\SendsMagicLinks;
use App\Enums\KnowiiCommunityMemberRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Mail\ConfirmNewEmailAddress;
use App\Mail\MagicLink;
use App\Models\Community;
use App\Models\InviteLink;
use App\Models\User;
use App\Support\Analytics;
use App\Support\SecretAddresses;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;

/*
 * The addresses that hold a secret (ADR 0064): a link token, a signature, or the one-time code of the OpenID Connect
 * provider. An address holds a secret when its route is signed, has a token parameter, or is named in the list
 * (App\Support\SecretAddresses), and an address near one of them (which the fallback route answers) holds it too. The
 * session keeps none of them as its previous address, the session is encrypted by default, the statistics never see them,
 * and a queued mail that holds a link is encrypted in the queue.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Every route whose addresses hold a secret, with the states that reach it: a visitor (`guest`), the account of the link
 * (`account`), another account (`another`), and the link of an invitation that is gone (`gone`). The guard test below
 * computes the same routes from the router.
 *
 * @var array<string, list<string>>
 */
const SA_ROUTES = [
    'login/link/{token}' => ['guest', 'account'],
    'reset-password/{token}' => ['guest', 'account'],
    'email/verify/{id}/{hash}' => ['guest', 'account', 'another'],
    'user/email-change/{userCuid}/{hash}' => ['guest', 'account', 'another'],
    'community-invitations/{invitation}' => ['guest', 'account', 'another', 'gone'],
    'invite/{inviteLinkToken}' => ['guest', 'account'],
    'api/v1/invite-links/{inviteLinkToken}' => ['account'],
    'auth/oidc/callback' => ['guest'],
];

/**
 * The addresses near a secret address, which the fallback route answers with a 404: a character too many (a mail program
 * that glues a dot to a link), a path too long, an address in capital letters, a bent path
 *
 * @var list<string>
 */
const SA_NEAR_ADDRESSES = [
    'an invite link with a dot',
    'an invite link in capital letters',
    'a sign-in link with a path too long',
    'a sign-in link in capital letters',
    'a reset link with a path too long',
    'a reset link in capital letters',
    'a link of the address check with a path too long',
    'a link of a change of address with a path too long',
    'a link of an invitation with a path too long',
    'the API read of an invite link in capital letters',
    'the callback of the provider in capital letters',
    'a sign-in link with a double slash',
    'a sign-in link with a dot segment',
    'a sign-in link behind the name of the front controller',
    'a reset link with a semicolon',
    'a link of the address check with a double slash',
    'the callback of the provider with a double slash',
];

/**
 * The parameter names of the routes, of every method, that hold no secret: an identifier, a slug, a cuid, a page number
 *
 * @var list<string>
 */
const SA_PUBLIC_PARAMETERS = [
    'accessGroup', 'comment', 'community', 'communityInvitation', 'communitySlug', 'conversation', 'conversationCuid',
    'event', 'eventSlug', 'fallbackPlaceholder', 'id', 'identityCuid', 'importCuid', 'invitation', 'invitationImport',
    'inviteLink', 'material', 'member', 'memberTag', 'message', 'notification', 'page', 'pageSlug', 'post', 'postSlug',
    'resource', 'resourceSlug', 'space', 'spaceGroup', 'spaceSlug', 'userCuid', 'username',
];

dataset('secret addresses', function (): array {
    $rows = [];
    foreach (SA_ROUTES as $route => $states) {
        foreach ($states as $state) {
            $rows["{$route} ({$state})"] = [$route, $state];
        }
    }
    foreach (SA_NEAR_ADDRESSES as $near) {
        $rows[$near] = [$near, 'near'];
    }

    return $rows;
});

/**
 * The token of a sign-in link for ada@example.com, from the queued mail
 */
function saMagicToken(): string
{
    Mail::fake();
    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);
    $token = '';
    Mail::assertQueued(MagicLink::class, function (MagicLink $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    return $token;
}

/**
 * The secrets of a signed address: its signature, and the hash of its path when it has one
 *
 * @return list<string>
 */
function saSignedSecrets(string $url, ?string $hash = null): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $signature = $query['signature'] ?? '';

    return array_values(array_filter([is_string($signature) ? $signature : '', $hash ?? '']));
}

/**
 * An address that the fallback route answers: a path segment too many before the query
 */
function saLonger(string $url): string
{
    return str_contains($url, '?') ? Str::replaceFirst('?', '/x?', $url) : $url.'/x';
}

/**
 * Prepare a row of the dataset: the accounts, the link, and the person who opens it. Ada is the account of every link.
 *
 * @return array{url: string, secrets: list<string>, as: User|null}
 */
function saCase(string $address, string $state): array
{
    $ada = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com', 'email_verified_at' => null]);
    $grace = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'grace@example.com']);
    $as = match ($state) {
        'account', 'gone' => $ada,
        'another' => $grace,
        default => null,
    };

    switch ($address) {
        case 'login/link/{token}':
            $token = saMagicToken();

            return ['url' => route('login.link.show', ['token' => $token]), 'secrets' => [$token], 'as' => $as];
        case 'a sign-in link with a path too long':
        case 'a sign-in link in capital letters':
        case 'a sign-in link with a double slash':
        case 'a sign-in link with a dot segment':
        case 'a sign-in link behind the name of the front controller':
            $token = saMagicToken();
            $url = config()->string('app.url').match ($address) {
                'a sign-in link in capital letters' => '/LOGIN/LINK/'.$token,
                'a sign-in link with a double slash' => '/login//link/'.$token,
                'a sign-in link with a dot segment' => '/login/./link/'.$token,
                'a sign-in link behind the name of the front controller' => '/index.php/login/link/'.$token,
                default => '/login/link/'.$token.'/x',
            };

            return ['url' => $url, 'secrets' => [$token], 'as' => null];
        case 'reset-password/{token}':
        case 'a reset link with a path too long':
        case 'a reset link in capital letters':
        case 'a reset link with a semicolon':
            $token = Password::broker()->createToken($ada);
            $url = match ($address) {
                'a reset link with a semicolon' => config()->string('app.url').'/reset-password;/'.$token.'?email=ada%40example.com',
                'a reset link with a path too long' => url('/reset-password/'.$token.'/x?email=ada%40example.com'),
                'a reset link in capital letters' => url('/Reset-Password/'.$token.'?email=ada%40example.com'),
                default => route('password.reset', ['token' => $token, 'email' => 'ada@example.com']),
            };

            return ['url' => $url, 'secrets' => [$token], 'as' => $as];
        case 'email/verify/{id}/{hash}':
        case 'a link of the address check with a path too long':
        case 'a link of the address check with a double slash':
            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $ada->id, 'hash' => sha1('ada@example.com')]);
            $url = match ($address) {
                'a link of the address check with a path too long' => saLonger($url),
                'a link of the address check with a double slash' => Str::replaceFirst(config()->string('app.url').'/', config()->string('app.url').'//', $url),
                default => $url,
            };

            return ['url' => $url, 'secrets' => saSignedSecrets($url, sha1('ada@example.com')), 'as' => $state === 'near' ? $ada : $as];
        case 'user/email-change/{userCuid}/{hash}':
        case 'a link of a change of address with a path too long':
            $hash = hash_hmac('sha256', 'pending-email:ada.new@example.com', config()->string('app.key'));
            $url = URL::temporarySignedRoute('email-change.confirm', now()->addMinutes(60), ['userCuid' => $ada->cuid, 'hash' => $hash]);
            $url = $state === 'near' ? saLonger($url) : $url;

            return ['url' => $url, 'secrets' => saSignedSecrets($url, $hash), 'as' => $state === 'near' ? $ada : $as];
        case 'community-invitations/{invitation}':
        case 'a link of an invitation with a path too long':
            $community = Community::factory()->create(['owner_id' => $grace->id]);
            $invitation = $community->communityInvitations()->create(['email' => 'ada@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
            $url = URL::temporarySignedRoute('community-invitations.accept', $invitation->expiresAt(), ['invitation' => $invitation]);
            if ($state === 'gone') {
                $invitation->delete();
            }
            $url = $state === 'near' ? saLonger($url) : $url;

            return ['url' => $url, 'secrets' => saSignedSecrets($url), 'as' => $state === 'near' ? $ada : $as];
        case 'invite/{inviteLinkToken}':
        case 'an invite link with a dot':
        case 'an invite link in capital letters':
        case 'api/v1/invite-links/{inviteLinkToken}':
        case 'the API read of an invite link in capital letters':
            $token = InviteLink::factory()->create()->token;
            $url = match ($address) {
                'an invite link with a dot' => url('/invite/'.$token.'.'),
                'an invite link in capital letters' => url('/INVITE/'.$token),
                'api/v1/invite-links/{inviteLinkToken}' => url(ApiRoutes::path(ApiRoutes::INVITE_LINK, [ApiRoutes::PARAM_INVITE_LINK_TOKEN => $token])),
                'the API read of an invite link in capital letters' => url('/API/v1/invite-links/'.$token),
                default => route('invite-links.open', ['inviteLinkToken' => $token]),
            };

            return ['url' => $url, 'secrets' => [$token], 'as' => str_contains($address, 'API') ? $ada : $as];
        case 'auth/oidc/callback':
        case 'the callback of the provider in capital letters':
        case 'the callback of the provider with a double slash':
            $path = match ($address) {
                'the callback of the provider in capital letters' => '/AUTH/OIDC/CALLBACK',
                'the callback of the provider with a double slash' => '//auth/oidc/callback',
                default => '/auth/oidc/callback',
            };

            return ['url' => config()->string('app.url').($path.'?code=one-time-code-of-the-provider&state=state-of-the-flow'), 'secrets' => ['one-time-code-of-the-provider', 'state-of-the-flow'], 'as' => null];
    }

    throw new LogicException('No case for '.$address);
}

/**
 * Open the address as the browser opens the link of a mail: a full load (no XHR), with the session cookie of the person.
 * The API read is a plain link of a page of the application to that address
 *
 * @param  array{url: string, secrets: list<string>, as: User|null}  $case
 */
function saOpen(array $case): TestResponse
{
    if ($case['as'] instanceof User) {
        test()->actingAs($case['as'], 'web');
    }

    return test()->get($case['url'], ['Referer' => config('app.url').'/dashboard']);
}

/**
 * Run as in production, where a 403, a 404 or a 500 renders the page `Error` at the address of the request. Production
 * needs the values of the operator (ADR 0055)
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function saAsInProduction(Closure $callback): mixed
{
    configuredInstance();
    app()->detectEnvironment(static fn (): string => 'production');
    try {
        return $callback();
    } finally {
        app()->detectEnvironment(static fn (): string => 'testing');
    }
}

/**
 * The values of every session of the database, flattened (`_previous.url`, `url.intended`, ...), decrypted when the
 * sessions are encrypted
 *
 * @return array<string, mixed>
 */
function saSessionValues(): array
{
    $values = [];
    foreach (DB::table('sessions')->pluck('payload') as $index => $payload) {
        $stored = (string) base64_decode((string) $payload, true);
        $json = config('session.encrypt') ? (string) app('encrypter')->decrypt($stored) : $stored;
        foreach (Arr::dot((array) json_decode($json, true)) as $key => $value) {
            $values["{$index}.{$key}"] = $value;
        }
    }

    return $values;
}

/**
 * The raw rows of the table of the sessions, as the database stores them
 */
function saRawSessions(): string
{
    return DB::table('sessions')->get()
        ->map(static fn (object $row): string => (string) json_encode($row, JSON_UNESCAPED_SLASHES).(string) base64_decode((string) $row->payload, true))
        ->implode("\n");
}

/**
 * The value of the prop `statistics` of a page, or 'no page' for an answer that is no page of the application
 */
function saStatisticsProp(TestResponse $response): mixed
{
    if ($response->headers->get('X-Inertia') === 'true') {
        return $response->json('props.statistics');
    }
    $page = $response->original instanceof View ? ($response->original->getData()['page'] ?? null) : null;

    return is_array($page) ? ($page['props']['statistics'] ?? null) : 'no page';
}

// ---------------------------------------------------------------------------------------------------------------------
// The session
// ---------------------------------------------------------------------------------------------------------------------

test('the session keeps no address that holds a secret as its previous address, also when it is not encrypted', function (string $address, string $state) {
    config(['session.driver' => 'database', 'session.encrypt' => false]);
    $case = saCase($address, $state);

    saOpen($case);

    // A visitor who opens a signed link goes to the sign-in page and comes back: the intended address is the one value
    // that holds it, and the session is encrypted by default (the next test)
    $values = array_filter(saSessionValues(), static fn (string $key): bool => ! str_ends_with($key, '.url.intended'), ARRAY_FILTER_USE_KEY);
    $text = implode("\n", array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values));
    expect(DB::table('sessions')->count())->toBeGreaterThanOrEqual(1)
        ->and($text)->not->toContain($case['url'])
        ->and(session()->previousUrl())->not->toBe($case['url']);
    foreach ($case['secrets'] as $secret) {
        expect($text)->not->toContain($secret)
            ->and((string) session()->previousUrl())->not->toContain($secret);
    }
})->with('secret addresses');

test('the session is encrypted by default: its row holds no secret of these addresses, also not the intended address', function (string $address, string $state) {
    config(['session.driver' => 'database', 'session.encrypt' => true]);
    $case = saCase($address, $state);

    saOpen($case);

    $raw = saRawSessions();
    expect(DB::table('sessions')->count())->toBeGreaterThanOrEqual(1)
        ->and($raw)->not->toContain($case['url'])
        ->and($raw)->not->toContain(Str::after($case['url'], config()->string('app.url')));
    foreach ($case['secrets'] as $secret) {
        expect($raw)->not->toContain($secret);
    }
})->with('secret addresses');

test('the configuration encrypts the session unless the instance turns it off', function () {
    $load = static function (?string $value): mixed {
        $value === null ? putenv('SESSION_ENCRYPT') : putenv("SESSION_ENCRYPT={$value}");
        unset($_ENV['SESSION_ENCRYPT'], $_SERVER['SESSION_ENCRYPT']);

        return (require config_path('session.php'))['encrypt'];
    };

    try {
        // Only an explicit false value turns it off: an empty value keeps it on
        expect($load(null))->toBeTrue()
            ->and($load(''))->toBeTrue()
            ->and($load('true'))->toBeTrue()
            ->and($load('on'))->toBeTrue()
            ->and($load('false'))->toBeFalse()
            ->and($load('0'))->toBeFalse();
        foreach (['off', 'OFF', 'no', 'No', 'FALSE', 'False', ' off '] as $off) {
            expect($load($off))->toBeFalse($off);
        }
    } finally {
        putenv('SESSION_ENCRYPT=true');
    }

    foreach (['.env.example', '.env.production.example'] as $file) {
        expect((string) file_get_contents(base_path($file)))->toContain("\nSESSION_ENCRYPT=true\n");
    }
});

test('the session still keeps the previous address of a page that holds no secret', function () {
    config(['session.driver' => 'database', 'session.encrypt' => true]);

    $this->get('/?utm_source=newsletter')->assertOk();

    expect(session()->previousUrl())->toBe(config('app.url').'/?utm_source=newsletter')
        ->and(saSessionValues()['0._previous.url'] ?? null)->toBe(config('app.url').'/?utm_source=newsletter');
});

// ---------------------------------------------------------------------------------------------------------------------
// The statistics
// ---------------------------------------------------------------------------------------------------------------------

test('the statistics never see an address that holds a secret: no page view and no script of the proxy, as in production', function (string $address, string $state) {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);
    $case = saCase($address, $state);

    $response = saAsInProduction(static fn (): TestResponse => saOpen($case));

    expect($response->getStatusCode())->toBeIn([200, 302, 403, 404])
        ->and(saStatisticsProp($response))->toBeIn([null, 'no page'])
        ->and((string) $response->getContent())->not->toContain('stats.example.com')
        ->and((string) $response->getContent())->not->toContain('/content/script.js');
    // An address near a secret one is the error page of the fallback route
    if ($state === 'near') {
        $response->assertNotFound()->assertInertia(fn ($page) => $page->component('Error')->where('statistics', null));
    }
})->with('secret addresses');

test('the error page of a signed link opened by another account, rendered as in production, sends no page view', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);
    $case = saCase('user/email-change/{userCuid}/{hash}', 'another');

    [$refused, $missing, $home] = saAsInProduction(fn (): array => [saOpen($case), $this->get('/no-such-page'), $this->get('/')]);

    $refused->assertForbidden()->assertInertia(fn ($page) => $page->component('Error')->where('status', 403)->where('statistics', null));
    // No 404 of the fallback route sends a page view, and a page of the application does
    $missing->assertNotFound()->assertInertia(fn ($page) => $page->component('Error')->where('statistics', null));
    $home->assertOk()->assertInertia(fn ($page) => $page->where('statistics.url', url('/')));
});

test('an Inertia visit that ends at a signed address gets no page view', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);
    $case = saCase('user/email-change/{userCuid}/{hash}', 'another');
    $version = (string) app(HandleInertiaRequests::class)->version(request());

    $response = saAsInProduction(fn (): TestResponse => $this->actingAs($case['as'] ?? throw new LogicException('No account'))
        ->get($case['url'], ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest']));

    $response->assertForbidden()->assertJsonPath('component', 'Error')->assertJsonPath('props.statistics', null);
});

// ---------------------------------------------------------------------------------------------------------------------
// The Referer header
// ---------------------------------------------------------------------------------------------------------------------

test('an address that holds a secret answers a referrer policy that sends no path to another host', function (string $address, string $state) {
    $response = saOpen(saCase($address, $state));

    // A page answers the policy of the application. An answer that a middleware gives before the headers of the web group
    // (the redirect of a visitor to the sign-in page, the 404 of an invitation that is gone) has none: the browser then
    // applies its default, strict-origin-when-cross-origin, which sends no path to another host either
    expect($response->headers->get('Referrer-Policy'))
        ->toBeIn(['origin-when-cross-origin', 'strict-origin-when-cross-origin', 'origin', 'strict-origin', 'same-origin', 'no-referrer', null]);
    if ($response->getStatusCode() === 200) {
        expect($response->headers->get('Referrer-Policy'))->toBe('origin-when-cross-origin');
    }
})->with('secret addresses');

test('a failed reset of the password goes back to the reset page, through the Referer header of the page', function () {
    config(['session.driver' => 'database']);
    $ada = User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'password' => Hash::make('old-password-of-ada')]);
    $token = Password::broker()->createToken($ada);
    $page = route('password.reset', ['token' => $token, 'email' => 'ada@example.com']);

    $this->get($page)->assertOk();
    expect(session()->previousUrl())->not->toBe($page);

    $this->withHeader('Referer', $page)
        ->post('/reset-password', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertRedirect($page)
        ->assertSessionHasErrors('password');
});

test('a failed reset of the password keeps no token in the session, after a normal POST and after an Inertia POST', function (bool $inertia) {
    config(['session.driver' => 'database', 'session.encrypt' => false]);
    $ada = User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'password' => Hash::make('old-password-of-ada')]);
    $token = Password::broker()->createToken($ada);
    $page = route('password.reset', ['token' => $token, 'email' => 'ada@example.com']);
    $headers = ['Referer' => $page];
    if ($inertia) {
        $headers += ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()), 'X-Requested-With' => 'XMLHttpRequest'];
    }

    $this->post('/reset-password', ['token' => $token, 'email' => 'ada@example.com', 'password' => 'short', 'password_confirmation' => 'short'], $headers)
        ->assertRedirect($page);

    $text = implode("\n", array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', saSessionValues()));
    expect($text)->toContain('ada@example.com')
        ->and($text)->not->toContain($token);
})->with(['a normal POST' => [false], 'an Inertia POST' => [true]]);

test('the newsletter form is off at an address that holds a secret, also on the error page there as in production', function () {
    config([
        'services.emailoctopus.api_key' => 'key-of-the-list',
        'services.emailoctopus.newsletter_list_id' => 'list-of-the-newsletter',
        'services.turnstile.site_key' => 'site-key-of-the-bot-check',
        'services.turnstile.secret_key' => 'secret-key-of-the-bot-check',
    ]);
    $off = ['enabled' => false, 'turnstileSiteKey' => null];

    $this->get('/')->assertInertia(fn ($page) => $page->where('newsletter', ['enabled' => true, 'turnstileSiteKey' => 'site-key-of-the-bot-check']));
    $case = saCase('user/email-change/{userCuid}/{hash}', 'another');
    $this->get(route('login.link.show', ['token' => 'a-token-of-a-link']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/MagicLink')->where('newsletter', $off));

    $refused = saAsInProduction(static fn (): TestResponse => saOpen($case));
    $refused->assertForbidden()->assertInertia(fn ($page) => $page->component('Error')->where('newsletter', $off));
    expect((string) $refused->getContent())->not->toContain('site-key-of-the-bot-check');
});

test('an Inertia visit to an address that holds a secret is never the previous address, also when Inertia keeps the visits', function () {
    config(['session.driver' => 'database', 'session.encrypt' => false, 'inertia.store_previous_url' => true]);
    $case = saCase('login/link/{token}', 'guest');
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()), 'X-Requested-With' => 'XMLHttpRequest'];

    // Inertia keeps a visit to a page without a secret
    $this->get('/?utm_source=newsletter', $headers)->assertOk();
    expect(session()->previousUrl())->toBe(config('app.url').'/?utm_source=newsletter');

    $this->get($case['url'], $headers)->assertOk()->assertJsonPath('component', 'Auth/MagicLink');
    $text = implode("\n", array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', saSessionValues()));
    expect(session()->previousUrl())->toBe(config('app.url').'/?utm_source=newsletter')
        ->and($text)->not->toContain($case['secrets'][0]);
});

test('a page that visitors may read and that refuses a visitor sends to the sign-in page without an address that holds a secret', function () {
    Route::middleware(['web', 'auth.optional'])->get('/qx-refused-page/{token}', static fn () => abort(404));
    Route::middleware(['web', 'auth.optional'])->get('/qx-refused-page', static fn () => abort(404));

    $this->get('/qx-refused-page/a-token-of-a-link')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBeNull()
        ->and(json_encode(session()->all(), JSON_UNESCAPED_SLASHES))->not->toContain('a-token-of-a-link');

    // Without a secret, the visitor comes back to the page after the sign-in
    $this->get('/qx-refused-page?page=2')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(config('app.url').'/qx-refused-page?page=2');
});

test('a page that visitors may read keeps no address that holds a secret as the intended address of a visitor', function () {
    Route::middleware(['web', 'auth.optional'])->get('/qx-visitor-page/{token}', static fn (): string => 'visitor page');
    Route::middleware(['web', 'auth.optional'])->get('/qx-visitor-page', static fn (): string => 'visitor page');

    $this->get('/qx-visitor-page/a-token-of-a-link')->assertOk()->assertSee('visitor page');
    expect(session('url.intended'))->toBeNull();

    // Without a secret, the visitor comes back to the page after the sign-in
    $this->get('/qx-visitor-page?page=2')->assertOk();
    expect(session('url.intended'))->toBe(config('app.url').'/qx-visitor-page?page=2');
});

// ---------------------------------------------------------------------------------------------------------------------
// The rule, and the guard of the routes
// ---------------------------------------------------------------------------------------------------------------------

test('every parameter of a route, of every method, is a secret of the rule or a public name of this test', function () {
    $unknown = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($route->parameterNames() as $name) {
            if (! in_array($name, [...SecretAddresses::SECRET_PARAMETERS, ...SA_PUBLIC_PARAMETERS], true)) {
                $unknown[] = $name.' ('.$route->uri().')';
            }
        }
    }

    // A new parameter name: put it in SecretAddresses::SECRET_PARAMETERS when its value is a secret, else in the list above
    expect($unknown)->toBe([]);
});

test('the routes whose addresses hold a secret are the rows of the dataset, and the rule covers each of them and its path', function () {
    $secret = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        // Computed here from the router, without the rule: a signature middleware, a secret parameter, or the list
        $signed = collect(app('router')->gatherRouteMiddleware($route))
            ->contains(static fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, ValidateSignature::class));
        $holds = $signed
            || array_intersect($route->parameterNames(), SecretAddresses::SECRET_PARAMETERS) !== []
            || in_array($route->getName(), SecretAddresses::SECRET_QUERY_ROUTES, true);

        expect(SecretAddresses::routeHoldsASecret($route))->toBe($holds, $route->uri());
        if ($holds) {
            $secret[] = $route->uri();
        }
    }

    sort($secret);
    $rows = array_keys(SA_ROUTES);
    sort($rows);
    expect($secret)->toBe($rows);

    // The full path of each, with a value for each parameter, also in capital letters and, after a parameter, longer: an
    // address that reaches no route. A new route that holds a secret needs a pattern of its own path in PATHS, never a broad
    // entry. A longer path of a route without a parameter reaches the fallback route, which the rule counts too
    foreach ($secret as $uri) {
        $sample = (string) preg_replace('/\{[^}]+\}/', 'value', $uri);
        foreach (array_filter([$sample, strtoupper($sample), str_contains($uri, '{') ? $sample.'/x' : null]) as $path) {
            expect(Str::is(SecretAddresses::PATHS, $path, ignoreCase: true))->toBeTrue($path);
        }
    }
});

test('no code of the application checks a signature by hand: a signed route uses the signature middleware, which the rule reads', function () {
    // The checks of the request and of the URL generator, as a method or through the facade `URL::`
    $check = '/(->|::)\s*(hasValidSignature|hasValidRelativeSignature|hasValidSignatureWhileIgnoring|hasCorrectSignature|signatureHasNotExpired)\s*\(/';
    $files = [];
    foreach ([app_path(), base_path('routes')] as $folder) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && preg_match($check, (string) file_get_contents($file->getPathname())) === 1) {
                $files[] = Str::after($file->getPathname(), base_path().'/');
            }
        }
    }

    // A route that checks its signature in its code escapes the rule: use the middleware `signed`, or list the route
    expect($files)->toBe([]);
});

test('an address that the fallback route answers holds a secret: no 404 of an unknown address is kept or counted', function (string $path) {
    $request = Request::create(config()->string('app.url').$path);
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(static fn (): RoutingRoute => $route);

    expect($route->isFallback)->toBeTrue()
        ->and(SecretAddresses::holdsASecret($request))->toBeTrue();
})->with(['/no-such-page', '/reset-password-help', '/login/linked?token=x', '/login//link/abc', '/reset-password;/abc', '/index.php/login/link/abc']);

test('an address without a secret holds none, and its page view keeps the origin and the path', function (string $path) {
    $request = Request::create(url($path));
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(static fn (): RoutingRoute => $route);

    expect(SecretAddresses::holdsASecret($request))->toBeFalse()
        ->and(Analytics::pageViewUrl($request))->toBe(url(Str::before($path, '?')));
})->with(['/invite', '/', '/community/harbour/about', '/email/verify', '/login', '/forgot-password?email=ada%40example.com']);

// ---------------------------------------------------------------------------------------------------------------------
// The queued mails
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The one queued mail of the table `jobs`: its raw row, as the database stores it, its mailable, read as the worker
 * reads it, and its queue
 *
 * @return array{row: string, mailable: mixed, queue: string}
 */
function saQueuedMail(): array
{
    $row = DB::table('jobs')->where('payload', 'like', '%SendQueuedMailable%')->sole();
    $command = (string) json_decode((string) $row->payload, true)['data']['command'];
    $job = unserialize(str_starts_with($command, 'O:') ? $command : (string) app('encrypter')->decrypt($command));
    expect($job)->toBeInstanceOf(SendQueuedMailable::class);

    return [
        'row' => (string) json_encode($row, JSON_UNESCAPED_SLASHES).(string) base64_decode((string) $row->payload, true),
        'mailable' => $job instanceof SendQueuedMailable ? $job->mailable : null,
        'queue' => (string) $row->queue,
    ];
}

/**
 * Run the queued mail with a real worker, and return the text of every mail that was sent
 */
function saRunQueuedMail(string $queue): string
{
    DB::table('jobs')->where('payload', 'not like', '%SendQueuedMailable%')->delete();
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queue, '--sleep' => 0, '--tries' => 1]);
    expect(DB::table('jobs')->count())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);

    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(static fn ($message): string => (string) $message->getOriginalMessage()->getHtmlBody().(string) $message->getOriginalMessage()->getTextBody())
        ->implode("\n");
}

test('a queued sign-in mail is encrypted in the queue, and the worker still sends it with its link', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);

    app(SendsMagicLinks::class)->send(['email' => 'ada@example.com']);

    $queued = saQueuedMail();
    $mail = $queued['mailable'];
    expect($mail)->toBeInstanceOf(MagicLink::class);
    $token = $mail instanceof MagicLink ? $mail->token : '';
    expect($queued['row'])->not->toContain($token)
        ->and($queued['row'])->not->toContain('login/link');

    expect(saRunQueuedMail($queued['queue']))->toContain(route('login.link.show', ['token' => $token]));
});

test('a queued mail that confirms a new address is encrypted in the queue, and the worker still sends it with its link', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    $ada = User::factory()->withUserProfile()->create(['email' => 'ada@example.com', 'password' => Hash::make('password-of-ada')]);

    $this->actingAs($ada)->put('/user/profile-information', ['name' => $ada->name, 'email' => 'ada.new@example.com', 'current_password' => 'password-of-ada'])
        ->assertSessionHasNoErrors();

    $queued = saQueuedMail();
    $mail = $queued['mailable'];
    expect($mail)->toBeInstanceOf(ConfirmNewEmailAddress::class);
    $url = $mail instanceof ConfirmNewEmailAddress ? $mail->url : '';
    expect($url)->toContain('signature=')
        ->and($queued['row'])->not->toContain(Str::after($url, '?'))
        ->and($queued['row'])->not->toContain('user/email-change');

    expect(saRunQueuedMail($queued['queue']))->toContain(e($url));
});
