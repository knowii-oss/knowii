<?php

use App\Actions\Entitlements\ReceiveEntitlementEvent;
use App\Contracts\Auth\ReadsOpenIdConnectIdentities;
use App\Contracts\Entitlements\RefreshesEntitlements;
use App\Exceptions\EntitlementsReadException;
use App\Jobs\ReadEntitlements;
use App\Jobs\SyncCommunityEntitlementRules;
use App\Models\AccessGroup;
use App\Models\AccessGroupMember;
use App\Models\AccessGroupRule;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\AccessGroupMemberships;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use App\Support\OpenIdIdentity;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/*
 * The entitlements of the sign-in provider (#1386, ADR 0047): the client credentials token, the read of one subject, the
 * change call (the webhook), the sign-in, the nightly run and the feature when it is off. The provider is fake (see
 * tests/Pest.php): no real provider is ever called.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    entConfigure([$this->c['community']->slug]);
    $this->provider = entFakeProvider();
    $this->logged = entCaptureLogs();
    $this->identity = entIdentity($this->c['member']);
    $this->group = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Paid members']);
    AccessGroupRule::query()->create(['access_group_id' => $this->group->id, 'entitlement_key' => 'example.paid']);
});

function entRefresh(): void
{
    app(RefreshesEntitlements::class)->refresh(test()->identity->fresh());
}

/**
 * @return list<string>
 */
function entRefreshFails(): EntitlementsReadException
{
    try {
        entRefresh();
    } catch (EntitlementsReadException $e) {
        return $e;
    }
    test()->fail('The read should have failed');
}

/**
 * @return list<string>
 */
function entStoredKeys(?User $user = null): array
{
    return UserEntitlement::query()->where('user_id', ($user ?? test()->c['member'])->id)->orderBy('key')->pluck('key')->all();
}

function entMembership(?User $user = null): ?AccessGroupMember
{
    return AccessGroupMember::query()->where('access_group_id', test()->group->id)->where('user_id', ($user ?? test()->c['member'])->id)->first();
}

function entLogText(): string
{
    return implode("\n", test()->logged->getArrayCopy());
}

function entPost(string $body, string $contentType = 'application/jwt'): TestResponse
{
    return test()->call('POST', ENT_WEBHOOK, [], [], [], ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'], $body);
}

// ---------------------------------------------------------------------------------------------------------------------
// The client credentials token
// ---------------------------------------------------------------------------------------------------------------------

test('the token is asked as the client with the scope and the resource, and cached until a minute before it expires (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);

    entRefresh();
    entRefresh();

    expect($this->provider->tokenRequests)->toHaveCount(1);
    $request = $this->provider->tokenRequests[0];
    parse_str($request->body(), $form);
    expect($form)->toBe(['grant_type' => 'client_credentials', 'scope' => 'entitlements:read:subjects', 'resource' => ENT_ISSUER.'/v1'])
        ->and($request->header('Authorization')[0])->toBe('Basic '.base64_encode(urlencode(ENT_CLIENT_ID).':'.urlencode(ENT_CLIENT_SECRET)))
        ->and(array_column($this->provider->reads, 'authorization'))->toBe(['Bearer '.ENT_ACCESS_TOKEN.'1', 'Bearer '.ENT_ACCESS_TOKEN.'1']);
});

test('the token is not cached by the application in clear text, and a new one is asked once the old one is about to expire (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();

    $cached = Cache::get('oidc:entitlements:token:'.hash('sha256', implode('|', [ENT_ISSUER, ENT_CLIENT_ID, 'entitlements:read:subjects', ENT_ISSUER.'/v1'])));
    expect($cached)->toBeString()->and($cached)->not->toContain(ENT_ACCESS_TOKEN);

    // The token lives 600 seconds and is kept for 540
    $this->travel(539)->seconds();
    entRefresh();
    expect($this->provider->tokenRequests)->toHaveCount(1);

    $this->travel(2)->seconds();
    entRefresh();
    expect($this->provider->tokenRequests)->toHaveCount(2)
        ->and(array_column($this->provider->reads, 'authorization')[2])->toBe('Bearer '.ENT_ACCESS_TOKEN.'2');
});

test('a token that lives a minute or less is used once and not kept (#1386)', function () {
    $this->provider->tokenExpiresIn = 60;
    entGrant($this->provider, 'sub-ada', ['example.paid']);

    entRefresh();
    entRefresh();

    expect($this->provider->tokenRequests)->toHaveCount(2);
});

test('a token that the provider stopped early is replaced once; a second refusal is a configuration problem (#1386)', function () {
    $this->provider->answers['sub-ada'] = fn (int $reads) => $reads === 1 ? 401 : ['status' => 200, 'entitlements' => ['example.paid']];

    entRefresh();

    expect($this->provider->tokenRequests)->toHaveCount(2)
        ->and(entStoredKeys())->toBe(['example.paid']);

    // Always refused: one fresh token, then the refusal is final
    UserEntitlement::query()->delete();
    $this->provider->answers['sub-ada'] = 401;
    $before = count($this->provider->tokenRequests);
    $failure = entRefreshFails();
    expect($failure->temporary)->toBeFalse()->and($failure->reason)->toBe('provider_refused_client')
        ->and($this->provider->tokenRequests)->toHaveCount($before + 1)
        ->and(entStoredKeys())->toBe([]);
});

test('the token endpoint refusing the client changes nothing and is a configuration problem; a provider that is down is temporary (#1386)', function (int $status, bool $temporary) {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $this->travel(11)->minutes();
    $this->provider->tokenStatus = $status;

    expect(entRefreshFails()->temporary)->toBe($temporary)->and(entStoredKeys())->toBe(['example.paid']);
})->with([
    'invalid client (401)' => [401, false],
    'bad request (400)' => [400, false],
    'forbidden (403)' => [403, false],
    'the provider is down (503)' => [503, true],
    'too many requests (429)' => [429, true],
]);

test('no token, no client secret and no subject is ever logged (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $this->provider->answers['sub-ada'] = 500;
    entRefreshFails();
    $this->provider->answers['sub-ada'] = 401;
    entRefreshFails();
    $this->travel(11)->minutes();
    $this->provider->tokenStatus = 401;
    entRefreshFails();

    $job = new ReadEntitlements($this->identity->id);
    $job->withFakeQueueInteractions()->handle(app(RefreshesEntitlements::class));

    $text = entLogText();
    expect($text)->not->toContain('ENT-ACCESS-TOKEN')
        ->and($text)->not->toContain(ENT_CLIENT_SECRET)
        ->and($text)->not->toContain('sub-ada')
        ->and($text)->not->toContain('example.paid')
        ->and($text)->not->toContain('secret-source');
});

// ---------------------------------------------------------------------------------------------------------------------
// The read of one subject
// ---------------------------------------------------------------------------------------------------------------------

test('a 200 stores the active keys only: valid, unique, no grant, no source, no date of the provider (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid', 'b-key', 'example.paid', 'has space', 'has/slash', 'semi;colon', str_repeat('a', 101), '']);

    entRefresh();

    expect(entStoredKeys())->toBe(['b-key', 'example.paid'])
        ->and(Schema::getColumnListing('user_entitlements'))->toBe(['id', 'user_id', 'issuer', 'key'])
        ->and($this->identity->fresh()->entitlements_checked_at)->not->toBeNull()
        ->and(UserEntitlement::query()->pluck('issuer')->unique()->all())->toBe([ENT_ISSUER]);
});

test('the keys are replaced as a whole at each read (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid', 'example.old']);
    entRefresh();
    expect(entStoredKeys())->toBe(['example.old', 'example.paid']);

    entGrant($this->provider, 'sub-ada', ['example.new', 'example.paid']);
    entRefresh();
    expect(entStoredKeys())->toBe(['example.new', 'example.paid']);

    entGrant($this->provider, 'sub-ada', []);
    entRefresh();
    expect(entStoredKeys())->toBe([])->and($this->identity->fresh()->entitlements_checked_at)->not->toBeNull();
});

test('a 200 runs the sync: the member gets the group of the rule, and loses it when the key goes (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $row = entMembership();
    expect($row?->by_rule)->toBeTrue()->and($row?->manual)->toBeFalse();

    entGrant($this->provider, 'sub-ada', ['something.else']);
    entRefresh();
    expect(entMembership())->toBeNull();
});

test('a 404 deletes the stored keys and removes the rule memberships, and keeps a membership added by hand (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $other = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Hand']);
    $this->c['member']->accessGroups()->attach($other);
    AccessGroupMember::query()->where('access_group_id', $this->group->id)->where('user_id', $this->c['member']->id)->update(['manual' => true]);

    unset($this->provider->answers['sub-ada']);
    entRefresh();

    $row = entMembership();
    expect(entStoredKeys())->toBe([])
        ->and($this->identity->fresh()->entitlements_checked_at)->toBeNull()
        ->and($row?->manual)->toBeTrue()
        ->and($row?->by_rule)->toBeFalse()
        ->and(AccessGroupMember::query()->where('access_group_id', $other->id)->count())->toBe(1);
});

test('a network error, a timeout, a 5xx, a 429 or an answer that is not what the provider documents changes nothing (#1386)', function (mixed $answer) {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $checked = $this->identity->fresh()->entitlements_checked_at;
    $this->travel(5)->minutes();
    $this->provider->answers['sub-ada'] = $answer;

    expect(entRefreshFails()->temporary)->toBeTrue();

    expect(entStoredKeys())->toBe(['example.paid'])
        ->and(entMembership()?->by_rule)->toBeTrue()
        ->and($this->identity->fresh()->entitlements_checked_at?->equalTo($checked))->toBeTrue();
})->with([
    'a timeout' => ['timeout'],
    '500' => [500],
    '502' => [502],
    '503' => [503],
    '429' => [429],
    'not JSON' => ['garbage'],
    'a status that is not documented (418)' => [418],
    'an answer without entitlements' => [['status' => 200, 'entitlements' => null]],
]);

test('an answer about another subject is not used (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $this->provider->answers['sub-ada'] = ['status' => 200, 'sub' => 'someone-else', 'entitlements' => []];

    expect(entRefreshFails()->temporary)->toBeTrue();

    expect(entStoredKeys())->toBe(['example.paid']);
});

test('a 401 or a 403 from the provider changes nothing and is logged once without any token (#1386)', function (int $status) {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    $second = entIdentity($this->c['moderator'], 'sub-grace');
    $this->provider->answers['sub-ada'] = $status;
    $this->provider->answers['sub-grace'] = $status;

    (new ReadEntitlements($this->identity->id))->withFakeQueueInteractions()->handle(app(RefreshesEntitlements::class));
    $job = (new ReadEntitlements($second->id))->withFakeQueueInteractions();
    $job->handle(app(RefreshesEntitlements::class));

    $problems = array_filter($this->logged->getArrayCopy(), fn (string $line) => str_contains($line, 'refused this client'));
    expect($problems)->toHaveCount(1)
        ->and(entStoredKeys())->toBe(['example.paid']);
    $job->assertNotReleased();
})->with([401, 403]);

test('a temporary failure is tried again after a growing delay, then left to the nightly read (#1386)', function () {
    $this->provider->answers['sub-ada'] = 503;
    $delays = [];
    foreach ([1, 2, 3, 4, 5] as $attempt) {
        $job = (new ReadEntitlements($this->identity->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;
        $job->handle(app(RefreshesEntitlements::class));
        $job->assertReleased(ReadEntitlements::BACKOFF_SECONDS[$attempt - 1]);
        $delays[] = $job->job->releaseDelay;
    }
    expect($delays)->toBe([60, 300, 1800, 7200, 21600]);

    $last = (new ReadEntitlements($this->identity->id))->withFakeQueueInteractions();
    $last->job->attempts = 6;
    $last->handle(app(RefreshesEntitlements::class));
    $last->assertNotReleased();
    expect(entLogText())->toContain('the nightly read is the next try');
});

test('a read of an identity that is gone, or of another issuer, calls nothing (#1386)', function () {
    $other = UserIdentity::factory()->create(['user_id' => $this->c['moderator']->id, 'issuer' => 'https://other.example.test', 'subject' => 'sub-other']);
    $gone = $this->identity->id;
    $this->identity->delete();

    (new ReadEntitlements($gone))->handle(app(RefreshesEntitlements::class));
    (new ReadEntitlements($other->id))->handle(app(RefreshesEntitlements::class));
    app(RefreshesEntitlements::class)->refresh($other);

    expect($this->provider->reads)->toBe([])->and($this->provider->tokenRequests)->toBe([]);
});

test('an account that is disconnected while the provider answers gets nothing stored (#1386)', function () {
    $this->provider->answers['sub-ada'] = function () {
        UserIdentity::query()->whereKey($this->identity->id)->delete();

        return ['status' => 200, 'entitlements' => ['example.paid']];
    };

    entRefresh();

    expect(entStoredKeys())->toBe([])->and(entMembership())->toBeNull();
});

test('there is no refresh token anywhere and the scope offline_access is never asked (#1386)', function () {
    config(['services.oidc.scopes' => 'openid profile email offline_access', 'services.oidc.entitlements_sign_in_scope' => 'entitlements:read offline_access']);

    expect(OpenIdConnect::scopes())->toBe(['openid', 'profile', 'email', 'entitlements:read']);
    foreach (['users', 'user_identities', 'user_entitlements'] as $table) {
        expect(Schema::getColumnListing($table))->not->toContain('refresh_token');
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// The change call
// ---------------------------------------------------------------------------------------------------------------------

test('a valid call answers 202 with an empty body and queues the read of the subject, without any session (#1386)', function () {
    Queue::fake();

    $response = entPost(entEventToken($this->provider, entEventClaims()));

    $response->assertStatus(202);
    expect($response->getContent())->toBe('')
        ->and($response->headers->getCookies())->toBe([]);
    Queue::assertPushed(ReadEntitlements::class, 1);
    Queue::assertPushed(ReadEntitlements::class, fn (ReadEntitlements $job) => $job->identityId === $this->identity->id);
});

test('the call is never trusted for more than the subject: the keys come from the read of the truth (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    $claims = entEventClaims(['entitlements' => ['example.paid', 'admin.everything'], 'email' => 'x@example.com', 'name' => 'X']);

    entPost(entEventToken($this->provider, $claims))->assertStatus(202);
    expect(entStoredKeys())->toBe(['example.paid']);

    // The provider says "nothing" now: the call that claims otherwise changes nothing
    entGrant($this->provider, 'sub-ada', []);
    entPost(entEventToken($this->provider, entEventClaims(['entitlements' => ['example.paid']])))->assertStatus(202);
    expect(entStoredKeys())->toBe([])->and(entMembership())->toBeNull();
});

test('a call whose subject is gone at the provider removes the keys and the rule memberships (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    expect(entMembership())->not->toBeNull();

    unset($this->provider->answers['sub-ada']);
    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(202);

    expect(entStoredKeys())->toBe([])->and(entMembership())->toBeNull();
});

test('a subject that nobody here knows gets the same 202 and nothing else (#1386)', function () {
    Queue::fake();

    $unknown = entPost(entEventToken($this->provider, entEventClaims(['sub' => 'nobody-knows-me'])));
    $known = entPost(entEventToken($this->provider, entEventClaims()));

    expect($unknown->status())->toBe($known->status())->toBe(202)
        ->and($unknown->getContent())->toBe($known->getContent());
    Queue::assertPushed(ReadEntitlements::class, 1);
    expect($this->provider->reads)->toBe([]);
});

test('a subject of another issuer is not an identity of this one (#1386)', function () {
    Queue::fake();
    UserIdentity::query()->whereKey($this->identity->id)->update(['issuer' => 'https://other.example.test']);

    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(202);

    Queue::assertNothingPushed();
});

test('a call that repeats a jti answers 202 and is not handled twice, and a refused call does not use up the jti (#1386)', function () {
    Queue::fake();
    $claims = entEventClaims(['jti' => 'jti-once']);

    // Refused first (wrong audience): the jti is not used up
    entPost(entEventToken($this->provider, [...$claims, 'aud' => 'another-client']))->assertStatus(401);
    entPost(entEventToken($this->provider, $claims))->assertStatus(202);
    Queue::assertPushed(ReadEntitlements::class, 1);

    // The provider retries with the same jti: 202, nothing more
    $repeat = entPost(entEventToken($this->provider, [...$claims, 'iat' => now()->getTimestamp()]));
    $repeat->assertStatus(202);
    expect($repeat->getContent())->toBe('');
    Queue::assertPushed(ReadEntitlements::class, 1);

    // Another jti is a new event
    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(202);
    Queue::assertPushed(ReadEntitlements::class, 2);
});

test('a jti is kept for 24 hours, then forgotten (#1386)', function () {
    Queue::fake();
    $claims = entEventClaims(['jti' => 'jti-day']);
    entPost(entEventToken($this->provider, $claims))->assertStatus(202);

    $this->travel(ReceiveEntitlementEvent::JTI_HOURS * 60 - 1)->minutes();
    entPost(entEventToken($this->provider, entEventClaims(['jti' => 'jti-day'])))->assertStatus(202);
    Queue::assertPushed(ReadEntitlements::class, 1);

    $this->travel(2)->minutes();
    entPost(entEventToken($this->provider, entEventClaims(['jti' => 'jti-day'])))->assertStatus(202);
    Queue::assertPushed(ReadEntitlements::class, 2);
});

test('every invalid call answers 401 with an empty body and queues nothing (#1386)', function (Closure $make) {
    Queue::fake();

    $response = $make($this->provider);

    $response->assertStatus(401);
    expect($response->getContent())->toBe('')
        ->and($response->headers->getCookies())->toBe([]);
    Queue::assertNothingPushed();
    expect($this->provider->reads)->toBe([]);
})->with([
    'a bad signature (another key, same key id)' => [fn ($p) => entPost(entEventToken($p, entEventClaims(), ENT_EVENT_TYPE, entEdKey('x')['secret']))],
    'a signature that was changed' => [function ($p) {
        $parts = explode('.', entEventToken($p, entEventClaims()));
        $parts[2] = strrev($parts[2]);

        return entPost(implode('.', $parts));
    }],
    'claims that were changed after the signature' => [function ($p) {
        $parts = explode('.', entEventToken($p, entEventClaims(['sub' => 'sub-ada'])));
        $parts[1] = entB64((string) json_encode(entEventClaims(['sub' => 'sub-grace'])));

        return entPost(implode('.', $parts));
    }],
    'an unknown key id' => [fn ($p) => entPost(entEventToken($p, entEventClaims(), ENT_EVENT_TYPE, null, 'unknown-key'))],
    'HS256 with the public key as the secret' => [function ($p) {
        $header = ['alg' => 'HS256', 'typ' => ENT_EVENT_TYPE, 'kid' => 'ed-1'];
        $claims = entEventClaims();
        $signing = entB64((string) json_encode($header)).'.'.entB64((string) json_encode($claims));

        return entPost($signing.'.'.entB64(hash_hmac('sha256', $signing, $p->ed['public'], true)));
    }],
    'HS256 with the encoded public key as the secret' => [function ($p) {
        $header = ['alg' => 'HS256', 'typ' => ENT_EVENT_TYPE, 'kid' => 'ed-1'];
        $signing = entB64((string) json_encode($header)).'.'.entB64((string) json_encode(entEventClaims()));

        return entPost($signing.'.'.entB64(hash_hmac('sha256', $signing, $p->ed['jwk']['x'], true)));
    }],
    'alg none' => [fn ($p) => entPost(entForge(['alg' => 'none', 'typ' => ENT_EVENT_TYPE], entEventClaims()))],
    'alg None with a signature' => [fn ($p) => entPost(entForge(['alg' => 'None', 'typ' => ENT_EVENT_TYPE, 'kid' => 'ed-1'], entEventClaims(), 'x'))],
    'no alg' => [fn ($p) => entPost(entForge(['typ' => ENT_EVENT_TYPE], entEventClaims()))],
    'the wrong typ' => [fn ($p) => entPost(entEventToken($p, entEventClaims(), 'JWT'))],
    'the typ of an ID token with another case' => [fn ($p) => entPost(entEventToken($p, entEventClaims(), 'Entitlements-Event+JWT'))],
    'the wrong issuer' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['iss' => 'https://evil.example.test'])))],
    'no issuer' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['iss'])))],
    'the wrong audience' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['aud' => 'another-client'])))],
    'an audience list without this client' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['aud' => ['a', 'b']])))],
    'no audience' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['aud'])))],
    'a token that expired a moment ago' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['iat' => now()->getTimestamp() - 100, 'exp' => now()->getTimestamp() - 10])))],
    'a token that expired long ago' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['iat' => now()->getTimestamp() - 1000, 'exp' => now()->getTimestamp() - 700])))],
    'no expiry' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['exp'])))],
    'an iat that is more than 5 minutes old' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['iat' => now()->getTimestamp() - 301, 'exp' => now()->getTimestamp() + 600])))],
    'an iat in the future' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['iat' => now()->getTimestamp() + 200, 'exp' => now()->getTimestamp() + 600])))],
    'no iat' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['iat'])))],
    'the wrong event' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['event' => 'entitlements.deleted'])))],
    'no event' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['event'])))],
    'no subject' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['sub'])))],
    'a subject that is not text' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['sub' => ['sub-ada']])))],
    'a subject with a control character' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['sub' => "sub-ada\n"])))],
    'a subject that is too long' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['sub' => str_repeat('a', 256)])))],
    'no jti' => [fn ($p) => entPost(entEventToken($p, entEventClaims([], ['jti'])))],
    'an empty jti' => [fn ($p) => entPost(entEventToken($p, entEventClaims(['jti' => ''])))],
    'an ID token of this client (no typ, no event)' => [fn ($p) => entPost(JWT::encode(['iss' => ENT_ISSUER, 'aud' => ENT_CLIENT_ID, 'sub' => 'sub-ada', 'iat' => now()->getTimestamp(), 'exp' => now()->getTimestamp() + 60], $p->ed['secret'], 'EdDSA', 'ed-1'))],
    'an empty body' => [fn ($p) => entPost('')],
    'garbage' => [fn ($p) => entPost('not.a.token')],
    'a JSON body' => [fn ($p) => entPost('{"sub":"sub-ada"}')],
    'a body that is too large' => [fn ($p) => entPost(str_repeat('a', 20000))],
    'the content type application/json' => [fn ($p) => entPost(entEventToken($p, entEventClaims()), 'application/json')],
    'the content type text/plain' => [fn ($p) => entPost(entEventToken($p, entEventClaims()), 'text/plain')],
    'the content type application/jwt-extra' => [fn ($p) => entPost(entEventToken($p, entEventClaims()), 'application/jwt-extra')],
    'no content type' => [fn ($p) => entPost(entEventToken($p, entEventClaims()), '')],
]);

test('the content type application/jwt is accepted with a parameter and in any case (#1386)', function (string $type) {
    Queue::fake();

    entPost(entEventToken($this->provider, entEventClaims()), $type)->assertStatus(202);

    Queue::assertPushed(ReadEntitlements::class, 1);
})->with(['application/jwt', 'application/jwt; charset=utf-8', 'Application/JWT']);

test('a refused call is logged with its reason and the issuer, never the token, the subject or a key (#1386)', function () {
    $token = entEventToken($this->provider, entEventClaims(['aud' => 'another-client', 'sub' => 'sub-secret-person']));

    entPost($token)->assertStatus(401);

    $text = entLogText();
    expect($text)->toContain('wrong_audience')->toContain(ENT_ISSUER)
        ->and($text)->not->toContain($token)
        ->and($text)->not->toContain('sub-secret-person')
        ->and($text)->not->toContain(explode('.', $token)[2]);
});

test('the keys of the issuer are read again once when the key id of a call is unknown (#1386)', function () {
    Queue::fake();
    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(202);

    // The provider rotated its keys: the cached keys do not hold the new one
    $new = entEdKey('ed-2');
    $this->provider->jwks = [$new['jwk']];
    entPost(entEventToken($this->provider, entEventClaims(), ENT_EVENT_TYPE, $new['secret'], 'ed-2'))->assertStatus(202);
    Queue::assertPushed(ReadEntitlements::class, 2);
});

test('unknown key ids make the server read the keys again once a minute at most, whoever calls (#1386)', function () {
    Queue::fake();
    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(202);
    $keyReads = fn () => Http::recorded(fn ($request) => str_ends_with($request->url(), '/jwks'))->count();
    $before = $keyReads();

    foreach (range(1, 5) as $i) {
        entPost(entEventToken($this->provider, entEventClaims(), ENT_EVENT_TYPE, null, 'unknown-'.$i))->assertStatus(401);
    }
    expect($keyReads())->toBe($before + 1);

    $this->travel(61)->seconds();
    entPost(entEventToken($this->provider, entEventClaims(), ENT_EVENT_TYPE, null, 'unknown-later'))->assertStatus(401);
    expect($keyReads())->toBe($before + 2);
});

test('the webhook is public: no session, no cookie, no CSRF token, no token ability, and it is limited per address (#1386)', function () {
    $route = Route::getRoutes()->getByName('oidc.entitlements.webhook');
    expect($route)->not->toBeNull();
    $middleware = app('router')->gatherRouteMiddleware($route);

    expect($route->methods())->toBe(['POST'])
        ->and($route->uri())->toBe('webhooks/oidc/entitlements')
        ->and($route->gatherMiddleware())->toContain('throttle:oidc-entitlements')
        ->and($middleware)->not->toContain(PreventRequestForgery::class)
        ->and($middleware)->not->toContain(StartSession::class);
    foreach ($middleware as $name) {
        expect(is_string($name) ? $name : '')->not->toStartWith('auth')->not->toStartWith('ability')->not->toStartWith('verified');
    }
    // A signed-in browser session does not change the answer
    $this->actingAs($this->c['owner']);
    entPost('x')->assertStatus(401);
});

test('the change address is limited per IP address (#1386)', function () {
    for ($i = 0; $i < 120; $i++) {
        entPost('x', 'text/plain')->assertStatus(401);
    }

    entPost('x', 'text/plain')->assertStatus(429);
    entPost(entEventToken($this->provider, entEventClaims()))->assertStatus(429);
});

// ---------------------------------------------------------------------------------------------------------------------
// The sign-in
// ---------------------------------------------------------------------------------------------------------------------

function entFakeSignIn(string $subject, string $email): void
{
    app()->instance(ReadsOpenIdConnectIdentities::class, new class($subject, $email) implements ReadsOpenIdConnectIdentities
    {
        public function __construct(private readonly string $subject, private readonly string $email) {}

        public function authorizationUrl(string $state, string $nonce, string $codeChallenge, bool $freshSignIn = false): string
        {
            return ENT_ISSUER.'/authorize?state='.$state;
        }

        public function identity(string $code, string $codeVerifier, string $nonce): OpenIdIdentity
        {
            return new OpenIdIdentity(ENT_ISSUER, $this->subject, $this->email, true, 'Ada Lovelace');
        }
    });
}

/**
 * The browser goes to the provider: the state of the flow
 */
function entStartFlow(?User $member = null): string
{
    $response = $member === null ? test()->get('/auth/oidc/redirect') : test()->actingAs($member->fresh())->post('/auth/oidc/connect');
    $response->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    return (string) ($query['state'] ?? '');
}

function entSignInThroughTheProvider(): TestResponse
{
    return test()->get('/auth/oidc/callback?code=c&state='.entStartFlow());
}

test('the sign-in asks the scope that links this client to the person when the feature is on, and only then (#1386)', function () {
    expect(OpenIdConnect::scopes())->toBe(['openid', 'profile', 'email', 'entitlements:read']);

    config(['services.oidc.entitlements_sign_in_scope' => 'custom:link']);
    expect(OpenIdConnect::scopes())->toBe(['openid', 'profile', 'email', 'custom:link']);

    config(['services.oidc.entitlements_url' => null]);
    expect(OpenIdConnect::scopes())->toBe(['openid', 'profile', 'email']);
});

test('a sign-in through the provider reads the entitlements and gives the groups of the rules (#1386)', function () {
    $ada = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
    $this->c['community']->users()->attach($ada, ['role' => 'member']);
    entGrant($this->provider, 'sub-new', ['example.paid']);
    entFakeSignIn('sub-new', 'ada@example.com');

    entSignInThroughTheProvider()->assertRedirect();

    $this->assertAuthenticatedAs($ada);
    expect(UserIdentity::query()->where('user_id', $ada->id)->first()?->entitlements_checked_at)->not->toBeNull()
        ->and(entStoredKeys($ada))->toBe(['example.paid'])
        ->and(entMembership($ada)?->by_rule)->toBeTrue();
});

test('a sign-in still works when the provider cannot answer the read (#1386)', function () {
    $ada = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
    $this->provider->answers['sub-new'] = 503;
    entFakeSignIn('sub-new', 'ada@example.com');

    entSignInThroughTheProvider()->assertRedirect();

    $this->assertAuthenticatedAs($ada);
    expect(entStoredKeys($ada))->toBe([]);
});

test('connecting the account from the profile settings reads the entitlements too (#1386)', function () {
    $ada = User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com', 'password' => null]);
    $this->c['community']->users()->attach($ada, ['role' => 'member']);
    entGrant($this->provider, 'sub-new', ['example.paid']);
    entFakeSignIn('sub-new', 'ada@example.com');

    $state = entStartFlow($ada);
    $this->get('/auth/oidc/callback?code=c&state='.$state)->assertRedirect(route('profile.show'));

    expect(entStoredKeys($ada))->toBe(['example.paid'])->and(entMembership($ada)?->by_rule)->toBeTrue();
});

test('a sign-in without the entitlements feature reads nothing (#1386)', function () {
    config(['services.oidc.entitlements_url' => null]);
    User::factory()->withUserProfile()->withPersonalCommunity()->create(['email' => 'ada@example.com']);
    entFakeSignIn('sub-new', 'ada@example.com');

    entSignInThroughTheProvider()->assertRedirect();

    expect($this->provider->reads)->toBe([])->and($this->provider->tokenRequests)->toBe([]);
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------------------------------------------------
// The nightly run
// ---------------------------------------------------------------------------------------------------------------------

test('the nightly run queues a read for every connected account of the provider, one a second, and none for another issuer (#1386)', function () {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
    $second = entIdentity($this->c['moderator'], 'sub-grace');
    $third = entIdentity($this->c['admin'], 'sub-alan');
    UserIdentity::factory()->create(['user_id' => $this->c['owner']->id, 'issuer' => 'https://other.example.test']);

    $this->artisan('entitlements:sync')->assertSuccessful();

    Queue::assertPushed(ReadEntitlements::class, 3);
    $delays = [];
    Queue::assertPushed(ReadEntitlements::class, function (ReadEntitlements $job) use (&$delays) {
        $delays[$job->identityId] = $job->delay->getTimestamp() - now()->getTimestamp();

        return true;
    });
    ksort($delays);
    expect(array_keys($delays))->toBe([$this->identity->id, $second->id, $third->id])
        ->and(array_values($delays))->toBe([0, 1, 2]);
});

test('the nightly run reads again what changed without any call, and removes what the provider took away (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    $this->artisan('entitlements:sync')->assertSuccessful();
    expect(entMembership()?->by_rule)->toBeTrue();

    // The change call was lost: the provider has nothing for the member now
    entGrant($this->provider, 'sub-ada', []);
    $this->artisan('entitlements:sync')->assertSuccessful();
    expect(entMembership())->toBeNull();
});

test('the nightly run is scheduled once a night (#1386)', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('entitlements:sync')->assertSuccessful();
});

test('removing a community from the list clears its rule memberships at the next nightly run, and keeps the ones added by hand (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    $hand = AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Hand']);
    AccessGroupRule::query()->create(['access_group_id' => $hand->id, 'entitlement_key' => 'example.paid']);
    entRefresh();
    AccessGroupMemberships::addManual($hand, $this->c['member']);
    // Another community that stays listed
    $other = communityWithMembers();
    $otherGroup = AccessGroup::query()->create(['community_id' => $other['community']->id, 'name' => 'Other']);
    AccessGroupMember::query()->create(['access_group_id' => $otherGroup->id, 'user_id' => $other['member']->id, 'manual' => false, 'by_rule' => true]);
    expect(AccessGroupMember::query()->where('user_id', $this->c['member']->id)->where('by_rule', true)->count())->toBe(2);

    entConfigure([$other['community']->slug]);
    Queue::fake();
    $this->artisan('entitlements:sync')->assertSuccessful();

    expect(entMembership())->toBeNull();
    $row = AccessGroupMember::query()->where('access_group_id', $hand->id)->first();
    expect($row?->manual)->toBeTrue()->and($row?->by_rule)->toBeFalse()
        ->and(AccessGroupMember::query()->where('access_group_id', $otherGroup->id)->first()?->by_rule)->toBeTrue();

    // Nothing listed at all: every community is outside
    entConfigure([]);
    $this->artisan('entitlements:sync')->assertSuccessful();
    expect(AccessGroupMember::query()->where('by_rule', true)->count())->toBe(0);
});

test('with the feature off the nightly run clears every rule membership and deletes the stored keys (#1386)', function () {
    entGrant($this->provider, 'sub-ada', ['example.paid']);
    entRefresh();
    expect(entStoredKeys())->toBe(['example.paid']);

    config(['services.oidc.entitlements_url' => null]);
    Queue::fake();
    $this->artisan('entitlements:sync')->assertSuccessful();

    expect(entMembership())->toBeNull()
        ->and(UserEntitlement::query()->count())->toBe(0)
        ->and($this->identity->fresh()->entitlements_checked_at)->toBeNull();
    Queue::assertNotPushed(ReadEntitlements::class);
});

// ---------------------------------------------------------------------------------------------------------------------
// The feature off
// ---------------------------------------------------------------------------------------------------------------------

test('without the address template there is no route, no job, no scope and no screen (#1386)', function (?string $url) {
    config(['services.oidc.entitlements_url' => $url]);
    Queue::fake();
    $owner = $this->c['owner'];

    expect(OidcEntitlements::enabled())->toBeFalse()->and(OidcEntitlements::allows($this->c['community']))->toBeFalse();
    entPost(entEventToken($this->provider, entEventClaims()))->assertNotFound();
    $this->actingAs($owner->fresh())->get('/user/profile')->assertInertia(fn ($page) => $page->missing('entitlements'));
    $this->get('/community/'.$this->c['community']->slug.'/settings')->assertInertia(fn ($page) => $page->where('permissions.canManageEntitlementRules', false));
    $this->getJson('/api/v1/user/entitlements')->assertNotFound();
    $rules = '/api/v1/communities/'.$this->c['community']->cuid.'/access-groups/'.$this->group->cuid.'/rules';
    $this->getJson($rules)->assertNotFound();
    $this->putJson($rules, ['entitlement_keys' => ['example.paid']])->assertNotFound();
    expect(OpenIdConnect::scopes())->toBe(['openid', 'profile', 'email']);

    Queue::assertNotPushed(ReadEntitlements::class);
    Queue::assertNotPushed(SyncCommunityEntitlementRules::class);
    expect($this->provider->reads)->toBe([]);
})->with([
    'no address' => [null],
    'an empty address' => [''],
    'an address without the subject' => [ENT_ISSUER.'/v1/entitlements'],
    'an address with the subject twice' => [ENT_ISSUER.'/v1/{subject}/{subject}'],
    'an address over http' => ['http://auth.example.test/v1/subjects/{subject}/entitlements'],
    'a subject in the host name' => ['https://{subject}.example.test/v1/entitlements'],
    'a subject in the scheme and host' => ['https://{subject}/entitlements'],
    'a subject in the query' => [ENT_ISSUER.'/v1/entitlements?sub={subject}'],
    'an address with a user name' => ['https://user:pass@auth.example.test/v1/{subject}'],
]);

test('without the sign-in feature the entitlements feature is off too (#1386)', function () {
    config(['services.oidc.client_secret' => null]);

    expect(OidcEntitlements::enabled())->toBeFalse();
    entPost(entEventToken($this->provider, entEventClaims()))->assertNotFound();
});

test('the subject is encoded in the address of the read (#1386)', function () {
    expect(OidcEntitlements::urlFor('a/b c?d#e'))->toBe(ENT_ISSUER.'/v1/subjects/a%2Fb%20c%3Fd%23e/entitlements');
});
