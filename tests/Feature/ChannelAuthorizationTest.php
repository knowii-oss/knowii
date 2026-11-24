<?php

use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Http\Middleware\AcceptOnlyTheSessionOnPages;
use App\Http\Middleware\EndOutdatedSessions;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Jetstream\Http\Middleware\AuthenticateSession;

/*
 * The authorization of a live channel (`POST /broadcasting/auth`) asks for the same sign-in checks as the pages: a session of
 * the account (never an API token), the check of the password of the session, a verified email address. A channel of a
 * community or of a space that does not exist answers as a channel that the account may not read.
 */

/**
 * The body that the client of the live connection sends for a channel
 *
 * @return array{socket_id: string, channel_name: string}
 */
function channelBody(string $channel): array
{
    return ['socket_id' => '1234.5678', 'channel_name' => $channel];
}

/**
 * The answer for a channel, for the account with a session (null: a visitor)
 */
function channelAnswerFor(?User $user, string $channel): TestResponse
{
    return withCheckingBroadcaster(function () use ($user, $channel): TestResponse {
        newBrowser();

        return ($user === null ? test() : test()->actingAs($user))->postJson('/broadcasting/auth', channelBody($channel));
    });
}

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion]);
});

test('a member with a session gets the authorization of the channels that they may read', function () {
    $member = $this->c['member'];

    foreach ([
        'private-user.'.$member->cuid,
        'private-communities',
        'private-community.'.$this->community->cuid,
        'private-community.'.$this->community->cuid.'.space.'.$this->space->cuid,
        'presence-community.'.$this->community->cuid.'.online',
    ] as $channel) {
        $response = channelAnswerFor($member, $channel)->assertOk();
        expect($response->json('auth'))->toStartWith('test-key:');
    }

    channelAnswerFor($member, 'private-user.'.$this->c['owner']->cuid)->assertForbidden()->assertJsonMissingPath('auth');
    channelAnswerFor($this->c['outsider'], 'private-community.'.$this->community->cuid)->assertForbidden()->assertJsonMissingPath('auth');
});

test('an account whose email address is not verified gets no authorization, as on the pages and on the API', function () {
    $unverified = User::factory()->unverified()->withUserProfile()->create();
    $this->community->users()->attach($unverified, ['role' => 'member']);

    foreach ([
        'private-user.'.$unverified->cuid,
        'private-communities',
        'private-community.'.$this->community->cuid,
        'presence-community.'.$this->community->cuid.'.online',
    ] as $channel) {
        channelAnswerFor($unverified, $channel)->assertForbidden()->assertJsonMissingPath('auth');
    }
});

test('the authorization answers a POST only', function () {
    $member = $this->c['member'];

    $response = withCheckingBroadcaster(function () use ($member): TestResponse {
        newBrowser();

        return test()->actingAs($member)->getJson('/broadcasting/auth?'.http_build_query(channelBody('private-user.'.$member->cuid)));
    });

    $response->assertNotFound()->assertJsonMissingPath('auth');
    expect(app('router')->getRoutes()->match(Request::create('/broadcasting/auth', 'POST'))->methods())->toBe(['POST']);
});

test('a visitor gets a refusal in JSON, whatever the name of the channel and whatever the request accepts', function (string $channel) {
    channelAnswerFor(null, $channel)
        ->assertUnauthorized()
        ->assertJsonPath('type', KnowiiApiResponseType::AuthenticationIssue->value)
        ->assertJsonMissingPath('auth');

    // A request that does not ask for JSON gets the same answer, never a page or a redirect
    withCheckingBroadcaster(function () use ($channel): TestResponse {
        newBrowser();

        return test()->post('/broadcasting/auth', channelBody($channel));
    })->assertUnauthorized()->assertJsonPath('type', KnowiiApiResponseType::AuthenticationIssue->value);
})->with([
    'a private channel' => ['private-communities'],
    'a presence channel' => ['presence-community.aaaaaaaaaaaaaaaaaaaaaaaa.online'],
    'a name without a prefix' => ['communities'],
    'no name' => [''],
]);

test('a request with an API token gets no authorization, whatever its abilities', function (array $abilities) {
    $member = $this->c['member'];
    $token = $member->createToken('a token', $abilities)->plainTextToken;

    $response = withCheckingBroadcaster(function () use ($member, $token): TestResponse {
        newBrowser();

        return test()->withToken($token)->postJson('/broadcasting/auth', channelBody('private-user.'.$member->cuid));
    });

    $response->assertForbidden()->assertJsonMissingPath('auth');
})->with([
    'no ability' => [[]],
    'one ability' => [['chat:read']],
    'the ability "*"' => [['*']],
]);

test('a community or a space that does not exist answers as one that the account may not read', function () {
    $missing = 'aaaaaaaaaaaaaaaaaaaaaaaa';
    $outsider = $this->c['outsider'];
    $member = $this->c['member'];

    // The channel of a community
    $refused = channelAnswerFor($outsider, 'private-community.'.$this->community->cuid);
    $unknown = channelAnswerFor($outsider, 'private-community.'.$missing);
    expect($unknown->getStatusCode())->toBe(403)
        ->and($unknown->getStatusCode())->toBe($refused->getStatusCode())
        ->and($unknown->getContent())->toBe($refused->getContent());

    // The channel of a space: a community that does not exist, and a space that does not exist in a community of the member
    $refused = channelAnswerFor($outsider, 'private-community.'.$this->community->cuid.'.space.'.$this->space->cuid);
    foreach ([
        [$outsider, 'private-community.'.$missing.'.space.'.$this->space->cuid],
        [$outsider, 'private-community.'.$this->community->cuid.'.space.'.$missing],
        [$member, 'private-community.'.$this->community->cuid.'.space.'.$missing],
        [$member, 'private-community.'.$missing.'.space.'.$missing],
    ] as [$user, $channel]) {
        $unknown = channelAnswerFor($user, $channel);
        expect($unknown->getStatusCode())->toBe(403)
            ->and($unknown->getContent())->toBe($refused->getContent());
    }
});

test('a member who sends a bad value gets a validation error, never a server error', function (array $body, array $fields) {
    $member = $this->c['member'];
    $body = array_map(fn (mixed $value): mixed => $value === '{own}' ? 'private-user.'.$member->cuid : ($value === ['{own}'] ? ['private-user.'.$member->cuid] : $value), $body);

    $response = withCheckingBroadcaster(function () use ($member, $body): TestResponse {
        newBrowser();

        return test()->actingAs($member)->postJson('/broadcasting/auth', $body);
    });

    $response->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value)->assertJsonMissingPath('auth');
    expect(array_keys($response->json('errors')))->toBe($fields);

    // A visitor gets the refusal of a visitor for the same body
    $visitor = withCheckingBroadcaster(function () use ($body): TestResponse {
        newBrowser();

        return test()->postJson('/broadcasting/auth', $body);
    });
    $visitor->assertUnauthorized()->assertJsonMissingPath('auth');
})->with([
    'no socket_id' => [['channel_name' => '{own}'], ['socket_id']],
    'a socket_id that is not valid' => [['socket_id' => 'abc', 'channel_name' => '{own}'], ['socket_id']],
    'a socket_id that is a list' => [['socket_id' => ['1234.5678'], 'channel_name' => '{own}'], ['socket_id']],
    'no channel_name' => [['socket_id' => '1234.5678'], ['channel_name']],
    'a channel_name that is a list' => [['socket_id' => '1234.5678', 'channel_name' => ['{own}']], ['channel_name']],
    'a channel_name that is too long' => [['socket_id' => '1234.5678', 'channel_name' => 'private-user.'.str_repeat('a', 300)], ['channel_name']],
    'an empty body' => [[], ['socket_id', 'channel_name']],
]);

test('a session whose password is not the one of the account any more gets no authorization', function () {
    $member = $this->c['member'];
    $cookies = signInWithForm($member)['session'];
    // The session opens a page: it holds the password of now
    browserWith($cookies)->get('/user/profile')->assertOk();

    // Only the stored password changes here: the check of the password of the session ends the session
    $member->forceFill(['password' => Hash::make('A-new-password-of-2026!')])->save();

    withCheckingBroadcaster(function () use ($cookies, $member): TestResponse {
        return browserWith($cookies)->postJson('/broadcasting/auth', channelBody('private-user.'.$member->cuid));
    })->assertUnauthorized()->assertJsonMissingPath('auth');
    $this->assertGuest('web');
});

test('the route has the sign-in checks of the pages, in their order', function () {
    $route = app('router')->getRoutes()->match(Request::create('/broadcasting/auth', 'POST'));
    $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
    $position = static fn (string $entry): int|false => array_search($entry, $middleware, true);

    expect($position(EndOutdatedSessions::class))->toBeInt()
        ->and($position(Authenticate::class.':sanctum'))->toBeGreaterThan($position(EndOutdatedSessions::class))
        ->and($position(AcceptOnlyTheSessionOnPages::class))->toBeGreaterThan($position(Authenticate::class.':sanctum'))
        ->and($position(AuthenticateSession::class))->toBeGreaterThan($position(AcceptOnlyTheSessionOnPages::class))
        ->and($position(EnsureEmailIsVerified::class))->toBeGreaterThan($position(AcceptOnlyTheSessionOnPages::class));

    // The same checks as a page of a member
    $page = array_values(array_filter(app('router')->gatherRouteMiddleware(app('router')->getRoutes()->match(Request::create('/dashboard', 'GET'))), 'is_string'));
    foreach ([Authenticate::class.':sanctum', AuthenticateSession::class, EnsureEmailIsVerified::class] as $entry) {
        expect($page)->toContain($entry)
            ->and($middleware)->toContain($entry);
    }
});
