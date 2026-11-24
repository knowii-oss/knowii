<?php

use App\Actions\CourseMaterials\ReorderCourseMaterials;
use App\Actions\Pages\ReorderPages;
use App\Actions\SpaceGroups\ReorderSpaceGroups;
use App\Actions\Spaces\ReorderSpaces;
use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Http\Middleware\LimitRequestBodySize;
use App\Http\Middleware\RequireTokenAbility;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

// Every route of the API has a limit of requests: per account (reads and writes apart) or per IP address for a visitor.
// A route with a limit of its own keeps it too. The pages that visitors read have a limit per IP address

test('an account has a limit of reads a minute on the API', function () {
    $this->actingAs(User::factory()->withUserProfile()->create());

    foreach (range(1, Constants::API_READS_PER_MINUTE) as $i) {
        $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
    }

    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertStatus(429);
    // The writes have their own count
    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => false])->assertOk();
});

test('an account has a limit of writes a minute on the API, and a token shares it', function () {
    $user = User::factory()->withUserProfile()->create();
    $this->actingAs($user);

    foreach (range(1, Constants::API_WRITES_PER_MINUTE) as $i) {
        $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => $i % 2 === 0])->assertOk();
    }

    Sanctum::actingAs($user, ['user:write']);
    $this->patchJson(ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), ['show_online_status' => false])->assertStatus(429);
    // The reads have their own count
    Sanctum::actingAs($user, ['user:read']);
    $this->getJson(ApiRoutes::path(ApiRoutes::USER))->assertOk();
});

test('a visitor has a limit of requests a minute on the API, per IP address', function () {
    foreach (range(1, Constants::PUBLIC_READS_PER_MINUTE) as $i) {
        $this->getJson(ApiRoutes::path(ApiRoutes::PING))->assertOk();
    }

    $this->getJson(ApiRoutes::path(ApiRoutes::PING))->assertStatus(429);
    $this->postJson(ApiRoutes::path(ApiRoutes::NEWSLETTER), ['email' => 'ada@example.com'])->assertStatus(429);
    // Another address has its own count
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson(ApiRoutes::path(ApiRoutes::PING))->assertOk();
});

test('a visitor has a limit of page reads a minute, per IP address', function () {
    $c = communityWithMembers();
    $page = '/community/'.$c['community']->slug;

    foreach (range(1, Constants::PUBLIC_PAGES_PER_MINUTE) as $i) {
        expect($this->get($page)->status())->not->toBe(429);
    }

    $this->get($page)->assertStatus(429);
    // A member is not counted per address
    expect($this->actingAs($c['member'])->get($page)->status())->toBe(200);
});

test('a route with a limit of its own answers with it first', function () {
    Queue::fake();
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
    $this->actingAs($owner);

    // Adding a resource starts a capture: it counts in the limit of the captures
    foreach (range(1, Constants::CAPTURES_PER_MINUTE) as $i) {
        $this->postJson($path, ['url' => "https://example.com/article-{$i}", 'level' => KnowiiResourceLevel::Beginner->value])->assertCreated();
    }

    $this->postJson($path, ['url' => 'https://example.com/one-more', 'level' => KnowiiResourceLevel::Beginner->value])
        ->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', (string) Constants::CAPTURES_PER_MINUTE);
});

/**
 * The middleware of an API route, as the router runs it (the groups expanded)
 *
 * @return list<string>
 */
function apiRouteMiddleware(string $method, string $path): array
{
    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $path);

    return $route === null ? [] : array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
}

test('the edit of a message, a post and a comment counts in the limit of their creation', function (string $path, string $limiter) {
    expect(apiRouteMiddleware('PATCH', ApiRoutes::BASE_PATH.'/'.$path))->toContain(ThrottleRequests::class.':'.$limiter);
})->with([
    'a chat message' => [ApiRoutes::COMMUNITY_SPACE_MESSAGE, 'messages'],
    'a direct message' => [ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, 'messages'],
    'a post' => [ApiRoutes::COMMUNITY_SPACE_POST, 'posts'],
    'a comment of a post' => [ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, 'comments'],
    'a comment of a resource' => [ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, 'comments'],
    'a comment of an event' => [ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT, 'comments'],
]);

test('every route of the API has the limit of the API, after the sign-in and the token ability', function () {
    $missing = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        // The CSRF cookie of Sanctum is a route of the web group
        if (! str_starts_with($route->uri(), 'api/') || $route->uri() === 'api/v1/csrf-cookie') {
            continue;
        }
        $middleware = apiRouteMiddleware($route->methods()[0], $route->uri());
        $limit = array_search(ThrottleRequests::class.':api', $middleware, true);
        $before = array_keys(array_filter($middleware, fn (string $name) => str_starts_with($name, RequireTokenAbility::class) || is_subclass_of(explode(':', $name)[0], AuthenticatesRequests::class)));
        if ($limit === false || ($before !== [] && max($before) > $limit)) {
            $missing[] = $route->methods()[0].' '.$route->uri();
        }
    }

    expect($missing)->toBe([]);
});

test('the read markers count in the reads of the API: a busy chat never takes the writes of its readers', function () {
    $c = communityWithMembers();
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))->create(['type' => KnowiiSpaceType::Chat]);
    $params = [ApiRoutes::PARAM_COMMUNITY => $c['community']->cuid, ApiRoutes::PARAM_SPACE => $space->cuid];
    $message = ChatMessage::factory()->inSpace($space)->create(['author_id' => $c['admin']->id]);
    $this->actingAs($c['member']);

    // The open chat page marks each new message of the others as read
    foreach (range(1, Constants::API_WRITES_PER_MINUTE + 30) as $i) {
        $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ, $params), ['message_cuid' => $message->cuid])->assertOk();
    }

    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MESSAGES, $params), ['body' => 'Hello'])->assertCreated();
});

test('every route that marks something as read or seen counts in the reads of the API', function (string $method, string $path) {
    $user = User::factory()->withUserProfile()->create();
    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === ApiRoutes::BASE_PATH.'/'.$path);
    expect($route)->not->toBeNull();

    $request = Request::create('/'.ApiRoutes::BASE_PATH.'/'.$path, $method);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $user);
    $limit = RateLimiter::limiter('api')($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->key)->toBe('read:user:'.$user->id)
        ->and($limit->maxAttempts)->toBe(Constants::API_READS_PER_MINUTE);
})->with([
    'the read marker of a chat' => ['POST', ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ],
    'the read marker of a conversation' => ['POST', ApiRoutes::COMMUNITY_CONVERSATION_READ],
    'the visit of a space' => ['PUT', ApiRoutes::COMMUNITY_SPACE_VISIT],
    'the notifications seen' => ['POST', ApiRoutes::NOTIFICATIONS_SEEN],
    'the notifications caught up' => ['POST', ApiRoutes::NOTIFICATIONS_CAUGHT_UP],
    'a notification read' => ['PUT', ApiRoutes::NOTIFICATION_READ],
    'a notification unread' => ['POST', ApiRoutes::NOTIFICATION_UNREAD],
]);

test('every other write counts in the writes of the API', function () {
    $user = User::factory()->withUserProfile()->create();
    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => in_array('POST', $route->methods(), true) && $route->uri() === ApiRoutes::BASE_PATH.'/'.ApiRoutes::COMMUNITY_SPACE_MESSAGES);
    $request = Request::create('/'.ApiRoutes::BASE_PATH.'/'.ApiRoutes::COMMUNITY_SPACE_MESSAGES, 'POST');
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $user);

    expect(RateLimiter::limiter('api')($request)->key)->toBe('write:user:'.$user->id);
});

// A request body is limited to 1 MB, except for the upload routes

test('a request body over 1 MB is refused with 413 before it is read', function (string $method, string $path, bool $json) {
    $body = $json
        ? (string) json_encode(['email' => 'ada@example.com', 'password' => 'x', 'padding' => str_repeat('a', Constants::MAX_REQUEST_BODY_BYTES)])
        : 'email=ada%40example.com&padding='.str_repeat('a', Constants::MAX_REQUEST_BODY_BYTES);
    $server = ['CONTENT_TYPE' => $json ? 'application/json' : 'application/x-www-form-urlencoded', 'CONTENT_LENGTH' => (string) strlen($body), 'HTTP_ACCEPT' => 'application/json'];

    $response = $this->call($method, $path, [], [], [], $server, $body);

    $response->assertStatus(413);
    expect($response->json('type'))->toBe('httpIssue');
})->with([
    'the sign-in of the API' => ['POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), true],
    'a write of the API' => ['PATCH', ApiRoutes::path(ApiRoutes::USER_ONLINE_STATUS), true],
    'the sign-in form' => ['POST', '/login', false],
]);

test('a request body over 1 MB without a length is refused with 413', function () {
    $body = (string) json_encode(['email' => 'ada@example.com', 'padding' => str_repeat('a', Constants::MAX_REQUEST_BODY_BYTES)]);

    $this->call('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body)
        ->assertStatus(413);
});

test('a request body under 1 MB goes on', function () {
    $body = (string) json_encode(['email' => 'ada@example.com', 'password' => 'x', 'padding' => str_repeat('a', 1000)]);

    $response = $this->call('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) strlen($body), 'HTTP_ACCEPT' => 'application/json'], $body);

    expect($response->status())->not->toBe(413);
});

/**
 * The body of the case: a JSON body of about 2.9 MB that decodes to 1.5 million items
 */
function largeJsonBody(): string
{
    return '{"email":"ada@example.com","password":"x","x":['.rtrim(str_repeat('0,', 1_500_000), ',').']}';
}

test('a body over 1 MB is refused from its length before the framework reads or decodes it', function () {
    $body = largeJsonBody();
    $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'.ApiRoutes::path(ApiRoutes::AUTH_LOGIN), 'CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) strlen($body), 'HTTP_ACCEPT' => 'application/json'];
    $read = false;

    memory_reset_peak_usage();
    $before = memory_get_usage();
    $answer = LimitRequestBodySize::answerBeforeTheFramework($server, function () use (&$read) {
        $read = true;

        return false;
    });
    $used = memory_get_peak_usage() - $before;

    expect($answer['status'] ?? null)->toBe(413)
        ->and(json_decode((string) ($answer['body'] ?? ''), true)['type'] ?? null)->toBe('httpIssue')
        // The body was never read, so never decoded
        ->and($read)->toBeFalse()
        ->and($used)->toBeLessThan(256 * 1024);
});

test('public/index.php checks the body before the framework makes the request', function () {
    $index = (string) file_get_contents(base_path('public/index.php'));

    expect(strpos($index, 'LimitRequestBodySize::answerBeforeTheFramework('))->toBeInt()
        ->and(strpos($index, 'LimitRequestBodySize::answerBeforeTheFramework('))->toBeLessThan(strpos($index, 'Request::capture()'));
});

test('before the framework: a body without a length is read only up to the limit', function (int $size, ?int $status) {
    $stream = fopen('php://temp', 'r+b');
    fwrite($stream, str_repeat('a', $size));
    rewind($stream);
    $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'.ApiRoutes::path(ApiRoutes::AUTH_LOGIN), 'CONTENT_TYPE' => 'application/json'];

    $answer = LimitRequestBodySize::answerBeforeTheFramework($server, fn () => $stream);

    expect($answer['status'] ?? null)->toBe($status)
        ->and(ftell($stream))->toBeLessThanOrEqual(Constants::MAX_REQUEST_BODY_BYTES + 65536);
})->with([
    'over the limit' => [3 * Constants::MAX_REQUEST_BODY_BYTES, 413],
    'under the limit' => [1000, null],
]);

test('before the framework: an upload route takes a large body only as a form with files', function (string $contentType, ?int $status) {
    $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'.ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS, [ApiRoutes::PARAM_COMMUNITY => 'nope']), 'CONTENT_TYPE' => $contentType, 'CONTENT_LENGTH' => (string) (2 * Constants::MAX_REQUEST_BODY_BYTES)];

    expect(LimitRequestBodySize::answerBeforeTheFramework($server, fn () => false)['status'] ?? null)->toBe($status);
})->with([
    'a form with files' => ['multipart/form-data; boundary=x', null],
    'JSON' => ['application/json', 413],
    'a plain form' => ['application/x-www-form-urlencoded', 413],
]);

test('a chunked body without a length is refused, except on the upload routes', function () {
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_TRANSFER_ENCODING' => 'chunked', 'HTTP_ACCEPT' => 'application/json'];

    $this->call('POST', ApiRoutes::path(ApiRoutes::AUTH_LOGIN), [], [], [], $server, '{"email":"ada@example.com"}')->assertStatus(411);
    expect(LimitRequestBodySize::answerBeforeTheFramework(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'.ApiRoutes::path(ApiRoutes::AUTH_LOGIN)] + $server, fn () => false)['status'] ?? null)->toBe(411);
});

test('a visitor with a large JSON body on an upload route gets 413', function () {
    $body = largeJsonBody();

    $this->call('POST', ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS, [ApiRoutes::PARAM_COMMUNITY => 'nope']), [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) strlen($body), 'HTTP_ACCEPT' => 'application/json'], $body)
        ->assertStatus(413);
});

test('the upload routes accept a body over 1 MB', function (string $path) {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $this->actingAs($user);
    $path = str_replace('{'.ApiRoutes::PARAM_COMMUNITY.'}', $user->ownedCommunities()->first()->cuid, $path);

    $response = $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'multipart/form-data; boundary=x', 'CONTENT_LENGTH' => (string) (2 * Constants::MAX_REQUEST_BODY_BYTES), 'HTTP_ACCEPT' => 'application/json']);

    expect($response->status())->not->toBe(413);
})->with([
    'the avatar' => [ApiRoutes::path(ApiRoutes::USER_AVATAR)],
    'an image of a community' => [ApiRoutes::path(ApiRoutes::COMMUNITY_UPLOADS)],
    'a file of invitations' => [ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATION_IMPORTS)],
]);

test('a list to reorder holds at most the cap of its items', function (string $list, int $cap) {
    $c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $c['community']->id]);
    $items = array_map(fn (int $i) => 'item-'.$i, range(1, $cap + 1));
    $space = fn (KnowiiSpaceType $type) => Space::factory()->inGroup($group)->create(['type' => $type]);

    $reorder = match ($list) {
        'groups' => fn () => app(ReorderSpaceGroups::class)->reorder($c['owner'], $c['community'], ['space_group_cuids' => $items]),
        'spaces' => fn () => app(ReorderSpaces::class)->reorder($c['owner'], $group, ['space_cuids' => $items]),
        'pages' => fn () => app(ReorderPages::class)->reorder($c['owner'], $space(KnowiiSpaceType::Pages), ['pages' => $items]),
        'materials' => fn () => app(ReorderCourseMaterials::class)->reorder($c['owner'], $space(KnowiiSpaceType::Course), ['materials' => $items]),
    };

    expect($reorder)->toThrow(ValidationException::class, 'must not have more than '.$cap.' items');
})->with([
    'the space groups' => ['groups', Constants::MAX_SPACE_GROUPS_PER_COMMUNITY],
    'the spaces of a group' => ['spaces', Constants::MAX_SPACES_PER_COMMUNITY],
    'the pages of a space' => ['pages', Constants::MAX_PAGES_PER_SPACE],
    'the materials of a course' => ['materials', Constants::MAX_COURSE_MATERIALS_PER_TARGET],
]);
