<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The OpenAPI document that Scramble generates (served on /docs/api).
 *
 * @return array{paths: array<string, array<string, array<string, mixed>>>}
 */
function openApiDocument(): array
{
    $path = tempnam(sys_get_temp_dir(), 'openapi');
    test()->artisan('scramble:export', ['--path' => $path])->assertSuccessful();
    /** @var array{paths: array<string, array<string, array<string, mixed>>>} $document */
    $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    unlink($path);

    return $document;
}

/**
 * The API routes of the application, as "METHOD /path" relative to /api (the server URL of the document).
 *
 * @return list<string>
 */
function applicationApiRoutes(): array
{
    $routes = [];
    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        $uri = $route->uri();
        // Sanctum's CSRF cookie route is not part of the documented API
        if (! str_starts_with($uri, 'api/') || $uri === 'api/v1/csrf-cookie') {
            continue;
        }
        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }
            $routes[] = $method.' '.substr($uri, strlen('api'));
        }
    }

    return $routes;
}

test('the OpenAPI document describes every API route', function () {
    $document = openApiDocument();

    $documented = [];
    foreach ($document['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' '.$path;
        }
    }

    $routes = applicationApiRoutes();
    expect($routes)->not->toBeEmpty()
        ->and(array_values(array_diff($routes, $documented)))->toBe([]);
});

test('every documented operation documents its success response', function () {
    foreach (openApiDocument()['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            $responses = array_keys($operation['responses'] ?? []);
            $success = array_filter($responses, fn ($code) => str_starts_with((string) $code, '2'));
            expect($success)->not->toBeEmpty(strtoupper($method)." {$path} documents no success response");
        }
    }
});

test('every write operation documents its request body', function () {
    // Validation lives in the actions (ADR 0006), so a body needs #[BodyParameter] on the controller method
    // Operations that take no body at all
    $noBody = [
        'POST /v1/communities/{community}/spaces/{space}/resources/{resource}/capture',
        // Block a member (#1186): the member is in the path
        'PUT /v1/user/blocks/{userCuid}',
        // Mark a notification as read, or open it again (#1474): the notification is in the path
        'PUT /v1/notifications/{notification}/read',
        'POST /v1/notifications/{notification}/unread',
        // Mark a lesson as done (#1304): the lesson is in the path
        'PUT /v1/communities/{community}/spaces/{space}/pages/{page}/progress',
        // Join an open community (#1486): the person is the signed-in account, the community is in the path
        'POST /v1/communities/{community}/membership',
        // Follow or mute a thread (#1475): the thread is in the path
        'PUT /v1/communities/{community}/spaces/{space}/posts/{post}/follow',
        'PUT /v1/communities/{community}/spaces/{space}/posts/{post}/mute',
        'PUT /v1/communities/{community}/spaces/{space}/messages/{message}/follow',
        'PUT /v1/communities/{community}/spaces/{space}/messages/{message}/mute',
        // Visit a space (#1476): the space is in the path
        'PUT /v1/communities/{community}/spaces/{space}/visit',
        // Duplicate an event (#1513): the event is in the path
        'POST /v1/communities/{community}/spaces/{space}/events/{event}/duplicate',
        // Send the link of a change of the email address again (#1706): the change waits on the account
        'POST /v1/user/email-change/resend',
        // Send a preview of invitations from a file (#1390): the import is in the path
        'POST /v1/communities/{community}/invitation-imports/{invitationImport}/confirm',
        // Join through an invite link (#1391): the person is the signed-in account, the link is in the path
        'POST /v1/invite-links/{inviteLinkToken}/membership',
    ];
    $withoutBody = [];
    foreach (openApiDocument()['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            $name = strtoupper($method).' '.$path;
            if (in_array($method, ['post', 'put', 'patch'], true) && empty($operation['requestBody']) && ! in_array($name, $noBody, true)) {
                $withoutBody[] = $name;
            }
        }
    }

    expect($withoutBody)->toBe([]);
});

test('the older endpoints document their required body fields', function (string $path, array $required) {
    $schema = openApiDocument()['paths'][$path]['post']['requestBody']['content']['application/json']['schema'] ?? [];

    expect($schema['required'] ?? [])->toEqualCanonicalizing($required);
})->with([
    'communities' => ['/v1/communities', ['name', 'visibility']],
    'resources' => ['/v1/communities/{community}/spaces/{space}/resources', ['url', 'level']],
    'username availability' => ['/v1/users/is-username-available', ['usernameToCheck']],
    'newsletter' => ['/v1/newsletter', ['email', 'turnstileToken']],
]);

test('the join routes are documented (#1486)', function () {
    $paths = openApiDocument()['paths'];

    $policy = $paths['/v1/communities/{community}/join-policy']['patch'] ?? [];
    expect($policy['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['join_policy']);

    expect($paths['/v1/communities/{community}/membership'])->toHaveKeys(['post', 'delete'])
        ->and($paths['/v1/communities/{community}/join-blocks'])->toHaveKey('get')
        ->and($paths['/v1/communities/{community}/join-blocks/{member}'])->toHaveKey('delete');
});

test('the indexing route is documented (#1487)', function () {
    $indexing = openApiDocument()['paths']['/v1/communities/{community}/indexing']['patch'] ?? [];

    expect($indexing['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['indexing_enabled']);
});

test('the follows, the levels and "I am caught up" are documented (#1475)', function () {
    $paths = openApiDocument()['paths'];
    $space = '/v1/communities/{community}/spaces/{space}';

    expect($paths[$space.'/posts/{post}/follow'])->toHaveKeys(['get', 'put', 'delete'])
        ->and($paths[$space.'/posts/{post}/mute'])->toHaveKeys(['put', 'delete'])
        ->and($paths[$space.'/messages/{message}/follow'])->toHaveKeys(['get', 'put', 'delete'])
        ->and($paths[$space.'/messages/{message}/mute'])->toHaveKeys(['put', 'delete'])
        ->and($paths[$space.'/notification-level'])->toHaveKeys(['get', 'put', 'delete'])
        ->and($paths['/v1/communities/{community}/notification-levels'])->toHaveKey('get')
        ->and($paths['/v1/notifications/caught-up'])->toHaveKey('post');

    $level = $paths[$space.'/notification-level']['put'];
    expect($level['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['level']);

    $names = array_column($paths['/v1/notifications']['get']['parameters'] ?? [], 'name');
    expect($names)->toContain('level')->toContain('state');
    $post = $paths[$space.'/posts']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? [];
    expect($post)->toHaveKey('notify_now');
    $update = $paths[$space]['patch']['requestBody']['content']['application/json']['schema']['properties'] ?? [];
    expect($update)->toHaveKey('default_notification_level');
});

test('the deletes of a comment and of a post document their option and both answers (#1565)', function () {
    $paths = openApiDocument()['paths'];
    $post = '/v1/communities/{community}/spaces/{space}/posts/{post}';

    foreach ([
        $post.'/comments/{comment}' => 'delete_replies',
        '/v1/communities/{community}/spaces/{space}/resources/{resource}/comments/{comment}' => 'delete_replies',
        '/v1/communities/{community}/spaces/{space}/events/{event}/comments/{comment}' => 'delete_replies',
        $post => 'delete_comments',
    ] as $path => $option) {
        $operation = $paths[$path]['delete'] ?? [];
        // Scramble lists the body fields of a DELETE as parameters, like the ones of DELETE /v1/user
        expect(array_column($operation['parameters'] ?? [], 'name'))->toContain($option)
            ->and(array_keys($operation['responses'] ?? []))->toContain(200)->toContain(204);
    }
});

test('the events routes are documented with their required fields and their query parameters (#1513)', function () {
    $paths = openApiDocument()['paths'];
    $events = '/v1/communities/{community}/spaces/{space}/events';

    expect($paths[$events]['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toEqualCanonicalizing(['title', 'starts_at', 'ends_at', 'time_zone'])
        ->and($paths[$events.'/{event}/state']['put']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['state'])
        ->and(array_column($paths[$events]['get']['parameters'] ?? [], 'name'))->toContain('scope', 'limit', 'offset')
        ->and($paths[$events.'/{event}'])->toHaveKeys(['get', 'patch', 'delete'])
        ->and($paths[$events.'/{event}/duplicate'])->toHaveKey('post')
        ->and($paths['/v1/communities/{community}/events'])->toHaveKey('get');
});

test('the routes of the answers to events are documented, with the limit of places and the replay choice of an event (#1515)', function () {
    $paths = openApiDocument()['paths'];
    $events = '/v1/communities/{community}/spaces/{space}/events';
    $answer = $paths[$events.'/{event}/answer'] ?? [];
    $answers = $paths[$events.'/{event}/answers'] ?? [];

    expect($answer)->toHaveKeys(['get', 'put', 'delete'])
        ->and($answer['put']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['answer'])
        ->and(array_keys($answer['delete']['responses'] ?? []))->toContain(204)
        ->and($answers)->toHaveKey('get')
        ->and(array_column($answers['get']['parameters'] ?? [], 'name'))->toContain('answer', 'limit', 'offset');

    foreach ([$paths[$events]['post'], $paths[$events.'/{event}']['patch']] as $operation) {
        $properties = $operation['requestBody']['content']['application/json']['schema']['properties'] ?? [];
        expect($properties)->toHaveKeys(['capacity', 'replay_expected', 'location_visibility'])
            ->and($properties['location_visibility']['description'] ?? '')->toContain('the members who answer Yes');
    }
});

test('the change of an event documents the replay link and the notes, and its delete the refusal "cancel it first" (#1516)', function () {
    $paths = openApiDocument()['paths'];
    $event = '/v1/communities/{community}/spaces/{space}/events/{event}';
    $update = $paths[$event]['patch']['requestBody']['content']['application/json']['schema']['properties'] ?? [];
    $create = $paths['/v1/communities/{community}/spaces/{space}/events']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? [];

    expect($update)->toHaveKeys(['replay_url', 'notes'])
        ->and($update['replay_url']['description'] ?? '')->toContain('never a visitor')
        ->and($create)->not->toHaveKey('replay_url')
        ->and($create)->not->toHaveKey('notes')
        ->and(array_keys($paths[$event]['delete']['responses'] ?? []))->toContain(204, 422);
});

test('the routes of the comments of an event are documented, with their body and no reaction on the event itself (#1516)', function () {
    $paths = openApiDocument()['paths'];
    $event = '/v1/communities/{community}/spaces/{space}/events/{event}';
    $comments = $paths[$event.'/comments'] ?? [];
    $comment = $paths[$event.'/comments/{comment}'] ?? [];
    $reactions = $paths[$event.'/comments/{comment}/reactions'] ?? [];
    $body = $comments['post']['requestBody']['content']['application/json']['schema'] ?? [];

    expect($comments)->toHaveKeys(['get', 'post'])
        ->and($body['required'] ?? [])->toBe(['body'])
        ->and(array_keys($body['properties'] ?? []))->toContain('body', 'parent_cuid')
        ->and($body['properties']['body']['description'] ?? '')->toContain('anyone on the internet can read it')
        ->and(array_keys($comments['post']['responses'] ?? []))->toContain(201)
        ->and($comment)->toHaveKeys(['patch', 'delete'])
        ->and($comment['patch']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['body'])
        ->and($reactions)->toHaveKeys(['get', 'post'])
        ->and($reactions['post']['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['emoji'])
        ->and($paths)->not->toHaveKey($event.'/reactions');
});

test('the calendar file of an event and "My events" are documented, the file as text/calendar with its refusals (#1515)', function () {
    $paths = openApiDocument()['paths'];
    $file = $paths['/v1/communities/{community}/spaces/{space}/events/{event}/calendar-file']['get'] ?? [];
    $mine = $paths['/v1/user/events']['get'] ?? [];

    expect(array_keys($file['responses'][200]['content'] ?? []))->toBe(['text/calendar; charset=utf-8'])
        ->and($file['responses'][200]['content']['text/calendar; charset=utf-8']['schema']['type'] ?? null)->toBe('string')
        ->and(array_keys($file['responses'][200]['headers'] ?? []))->toContain('Content-Disposition', 'X-Content-Type-Options', 'Cache-Control')
        ->and(array_keys($file['responses'] ?? []))->toContain(401, 403, 404, 422, 429)
        ->and(array_column($mine['parameters'] ?? [], 'name'))->toContain('scope', 'answer', 'limit', 'offset')
        ->and(array_keys($mine['responses'] ?? []))->toContain(200, 400, 401, 403);
});

test('the routes of the invitations from a file are documented, the upload as a multipart body with its file (#1390)', function () {
    $paths = openApiDocument()['paths'];
    $base = '/v1/communities/{community}/invitation-imports';
    $upload = $paths[$base]['post'] ?? [];
    $content = $upload['requestBody']['content'] ?? [];
    $schema = $content['multipart/form-data']['schema'] ?? ($content['application/json']['schema'] ?? []);

    expect(array_keys($paths[$base] ?? []))->toContain('get', 'post')
        ->and(array_keys($paths[$base.'/{invitationImport}'] ?? []))->toContain('get', 'delete')
        ->and($paths)->toHaveKeys([$base.'/{invitationImport}/rows', $base.'/{invitationImport}/confirm', $base.'/{invitationImport}/resend'])
        ->and(array_keys($content))->toContain('multipart/form-data')
        ->and($schema['properties']['file']['format'] ?? null)->toBe('binary')
        ->and($schema['required'] ?? [])->toContain('file')
        ->and(array_column($paths[$base.'/{invitationImport}/rows']['get']['parameters'] ?? [], 'name'))->toContain('outcome', 'limit', 'offset')
        // The confirm and the send again answer 202: the mails go out after the answer
        ->and(array_keys($paths[$base.'/{invitationImport}/confirm']['post']['responses'] ?? []))->toContain(202)->not->toContain(200)
        ->and(array_keys($paths[$base.'/{invitationImport}/resend']['post']['responses'] ?? []))->toContain(202)->not->toContain(200);
});

test('the routes of the invite links are documented, the create with its required name and the list of who joined with its paging (#1391)', function () {
    $paths = openApiDocument()['paths'];
    $base = '/v1/communities/{community}/invite-links';
    $create = $paths[$base]['post'] ?? [];

    expect(array_keys($paths[$base] ?? []))->toContain('get', 'post')
        ->and(array_keys($paths[$base.'/{inviteLink}'] ?? []))->toContain('get', 'patch', 'delete')
        ->and($create['requestBody']['content']['application/json']['schema']['required'] ?? [])->toBe(['name'])
        ->and(array_keys($create['requestBody']['content']['application/json']['schema']['properties'] ?? []))->toContain('name', 'active', 'expires_at', 'max_uses', 'access_groups', 'member_tags')
        ->and(array_column($paths[$base.'/{inviteLink}/uses']['get']['parameters'] ?? [], 'name'))->toContain('limit', 'offset')
        ->and(array_keys($paths['/v1/invite-links/{inviteLinkToken}'] ?? []))->toContain('get')
        ->and(array_keys($paths['/v1/invite-links/{inviteLinkToken}/membership'] ?? []))->toContain('post');
});

test('the deletion of a community documents the name that it needs', function () {
    $operation = openApiDocument()['paths']['/v1/communities/{community}']['delete'] ?? [];

    // Scramble lists the body fields of a DELETE as parameters, like the ones of DELETE /v1/user
    $name = collect($operation['parameters'] ?? [])->firstWhere('name', 'name');
    expect($name)->not->toBeNull()
        ->and($name['required'] ?? false)->toBeTrue()
        ->and(array_keys($operation['responses'] ?? []))->toContain(204);
});

test('the add of a member to an access group documents both answers: the member, and no body for an email address', function () {
    $operation = openApiDocument()['paths']['/v1/communities/{community}/access-groups/{accessGroup}/members']['post'] ?? [];

    // The three selectors of the body, wherever the schema of the body holds them
    $body = (string) json_encode($operation['requestBody'] ?? []);
    expect($body)->toContain('"member"')->toContain('"username"')->toContain('"email"')
        ->and(array_keys($operation['responses'] ?? []))->toContain(200)->toContain(204);
});

test('every operation documents the 429 of the limits, and every write but an upload the 413 of a large body', function () {
    $paths = openApiDocument()['paths'];
    $codes = fn (string $path, string $method) => array_keys($paths[$path][$method]['responses'] ?? []);

    expect($codes('/v1/user/online-status', 'patch'))->toContain(429, 413)
        ->and($codes('/v1/ping', 'get'))->toContain(429)->not->toContain(413)
        ->and($codes('/v1/user/avatar', 'post'))->toContain(429)->not->toContain(413);
});
