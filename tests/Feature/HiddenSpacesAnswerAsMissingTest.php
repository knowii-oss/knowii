<?php

use App\ApiRoutes;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiCourseMaterialKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\CourseMaterial;
use App\Models\Event as EventModel;
use App\Models\InvitationImport;
use App\Models\InviteLink;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Visus\Cuid2\Cuid2;

/*
 * A space that a reader cannot read, and every item in it, answers as one that does not exist (ADR 0044 point 4, ADR 0011):
 *
 * 1. A visitor and a signed-in person who is not a member get the same answer (the status and the body) for a space or an
 *    item that exists and that they cannot read, and for one that does not exist, on the pages and on the API, for every
 *    `Accept` header.
 * 2. A member outside the access groups of a space may learn that the space exists, and nothing of what is in it: the
 *    access to the space is checked before any item of the space is read, so the answer is the same 403 whether the item
 *    exists or not, has a quiz or not, is the marker of a deleted post or not, and whatever the type of the space.
 * 3. The accepted cases (ADR 0011): on the API, where every identifier is a random cuid, a person who is not a member gets
 *    403 for a community that exists, and a member without the right gets 403 for an item of a manager that exists.
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $community = $this->c['community'];
    $spaceGroup = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $staff = AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'Staff']);

    // A member of the access group reads every space below: the control
    $this->insider = User::factory()->withUserProfile()->create();
    $community->users()->attach($this->insider, ['role' => 'member']);
    $staff->members()->attach($this->insider);

    $hidden = static function (KnowiiSpaceType $type, string $name) use ($spaceGroup, $staff): Space {
        $space = Space::factory()->inGroup($spaceGroup)->create(['type' => $type, 'visibility' => KnowiiSpaceVisibility::Groups, 'name' => $name]);
        $space->accessGroups()->attach($staff);

        return $space;
    };
    $this->spaces = [
        'discussion' => $hidden(KnowiiSpaceType::Discussion, 'Staff room'),
        'pages' => $hidden(KnowiiSpaceType::Pages, 'Handbook'),
        'course' => $hidden(KnowiiSpaceType::Course, 'Training'),
        'resources' => $hidden(KnowiiSpaceType::Resources, 'Library'),
        'events' => $hidden(KnowiiSpaceType::Events, 'Calendar'),
    ];

    $this->post = Post::factory()->inSpace($this->spaces['discussion'])->create(['title' => 'Salary review']);
    $this->marker = Post::factory()->inSpace($this->spaces['discussion'])->create(['title' => 'Old plan', 'deleted_at' => now()]);
    $this->comment = Comment::factory()->onPost($this->post)->create(['author_id' => $this->insider->id]);
    $this->page = Page::factory()->inSpace($this->spaces['pages'])->create(['title' => 'Pay scales']);
    $this->lesson = Page::factory()->inSpace($this->spaces['course'])->create(['title' => 'First lesson', 'position' => 0]);
    $this->quizLesson = Page::factory()->inSpace($this->spaces['course'])->create(['title' => 'Second lesson', 'position' => 1]);
    Quiz::query()->create(['cuid' => (new Cuid2)->toString(), 'community_id' => $community->id, 'page_id' => $this->quizLesson->id, 'pass_mark' => 100]);
    Storage::fake('local');
    Storage::disk('local')->put('materials/pay-grid.pdf', '%PDF-1.4');
    $this->material = CourseMaterial::query()->create([
        'community_id' => $community->id,
        'space_id' => $this->spaces['course']->id,
        'page_id' => null,
        'title' => 'Pay grid',
        'kind' => KnowiiCourseMaterialKind::File,
        'disk' => 'local',
        'path' => 'materials/pay-grid.pdf',
        'file_name' => 'pay-grid.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
        'position' => 0,
    ]);
    $this->resource = CommunityResource::factory()->create(['space_id' => $this->spaces['resources']->id, 'community_id' => $community->id]);
    $this->resourceComment = Comment::factory()->create([
        'post_id' => null,
        'community_resource_id' => $this->resource->id,
        'community_id' => $community->id,
        'author_id' => $this->insider->id,
    ]);
    $this->event = EventModel::factory()->inSpace($this->spaces['events'])->hostedBy($this->c['owner'])->create(['title' => 'Board meeting']);
    $this->eventComment = Comment::factory()->onEvent($this->event)->create(['author_id' => $this->insider->id]);
});

/**
 * A cuid or a slug that names nothing
 */
function hiddenMissingCuid(): string
{
    return (new Cuid2)->toString();
}

const HIDDEN_MISSING_SLUG = 'nothing-has-this-name';

/**
 * The address of an API route of the community of the test
 *
 * @param  array<string, string>  $params
 */
function hiddenApi(string $route, array $params): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid, ...$params]);
}

/**
 * The address of a page of a space of the community of the test
 */
function hiddenWeb(string $space, string $rest = ''): string
{
    return '/community/'.test()->c['community']->slug.'/space/'.$space.$rest;
}

/**
 * The kinds of routes under a space: the method, whether it is a page (web) or the API, and the address for each case.
 * The cases: `hidden` (an item that exists, in a space that the reader cannot read), `missing item` (no such item in that
 * space), `missing space` (no such space) and `other` (an item that exists and differs in one fact: no quiz, a marker,
 * another type of space). A case that does not apply to the kind is missing from its list.
 *
 * @return array<string, array{method: string, web: bool, urls: array<string, string>}>
 */
function hiddenKinds(): array
{
    $t = test();
    $s = $t->spaces;
    $missing = hiddenMissingCuid();
    $nowhere = hiddenMissingCuid();
    $space = static fn (string $kind, array $more = []): array => [ApiRoutes::PARAM_SPACE => $s[$kind]->cuid, ...$more];
    $noSpace = static fn (array $more = []): array => [ApiRoutes::PARAM_SPACE => $nowhere, ...$more];
    $api = static fn (string $method, string $route, array $hidden, ?array $missingItem, array $missingSpace, ?array $other = null): array => [
        'method' => $method,
        'web' => false,
        'urls' => array_filter([
            'hidden' => hiddenApi($route, $hidden),
            'missing item' => $missingItem === null ? null : hiddenApi($route, $missingItem),
            'missing space' => hiddenApi($route, $missingSpace),
            'other' => $other === null ? null : hiddenApi($route, $other),
        ]),
    ];
    $web = static fn (string $kind, string $hidden, ?string $missingItem, string $missingSpace, ?string $other = null): array => [
        'method' => 'GET',
        'web' => true,
        'urls' => array_filter([
            'hidden' => hiddenWeb($s[$kind]->slug, $hidden),
            'missing item' => $missingItem === null ? null : hiddenWeb($s[$kind]->slug, $missingItem),
            'missing space' => hiddenWeb(HIDDEN_MISSING_SLUG, $missingSpace),
            'other' => $other,
        ]),
    ];
    $post = [ApiRoutes::PARAM_POST => $t->post->cuid];
    $page = [ApiRoutes::PARAM_PAGE => $t->page->cuid];
    $quiz = [ApiRoutes::PARAM_PAGE => $t->quizLesson->cuid];
    $noQuiz = [ApiRoutes::PARAM_PAGE => $t->lesson->cuid];
    $material = [ApiRoutes::PARAM_MATERIAL => $t->material->cuid];
    $resource = [ApiRoutes::PARAM_RESOURCE => $t->resource->cuid];
    $event = [ApiRoutes::PARAM_EVENT => $t->event->cuid];

    return [
        // The API: the space itself
        'api: read the space' => $api('GET', ApiRoutes::COMMUNITY_SPACE, $space('discussion'), null, $noSpace()),
        'api: change the space' => $api('PATCH', ApiRoutes::COMMUNITY_SPACE, $space('discussion'), null, $noSpace()),
        'api: visit the space' => $api('PUT', ApiRoutes::COMMUNITY_SPACE_VISIT, $space('discussion'), null, $noSpace()),
        // Posts and their comments
        'api: list the posts' => $api('GET', ApiRoutes::COMMUNITY_SPACE_POSTS, $space('discussion'), null, $noSpace()),
        'api: read a post' => $api('GET', ApiRoutes::COMMUNITY_SPACE_POST, $space('discussion', $post), $space('discussion', [ApiRoutes::PARAM_POST => $missing]), $noSpace([ApiRoutes::PARAM_POST => $missing])),
        'api: change a post' => $api('PATCH', ApiRoutes::COMMUNITY_SPACE_POST, $space('discussion', $post), $space('discussion', [ApiRoutes::PARAM_POST => $missing]), $noSpace([ApiRoutes::PARAM_POST => $missing])),
        'api: follow a post' => $api('PUT', ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, $space('discussion', $post), $space('discussion', [ApiRoutes::PARAM_POST => $missing]), $noSpace([ApiRoutes::PARAM_POST => $missing]), $space('discussion', [ApiRoutes::PARAM_POST => $t->marker->cuid])),
        'api: list the comments of a post' => $api('GET', ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, $space('discussion', $post), $space('discussion', [ApiRoutes::PARAM_POST => $missing]), $noSpace([ApiRoutes::PARAM_POST => $missing])),
        'api: change a comment of a post' => $api('PATCH', ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, $space('discussion', [...$post, ApiRoutes::PARAM_COMMENT => $t->comment->cuid]), $space('discussion', [...$post, ApiRoutes::PARAM_COMMENT => $missing]), $noSpace([ApiRoutes::PARAM_POST => $missing, ApiRoutes::PARAM_COMMENT => $missing])),
        // Pages, lessons, quizzes and materials
        'api: read a page' => $api('GET', ApiRoutes::COMMUNITY_SPACE_PAGE, $space('pages', $page), $space('pages', [ApiRoutes::PARAM_PAGE => $missing]), $noSpace([ApiRoutes::PARAM_PAGE => $missing])),
        'api: add a page' => $api('POST', ApiRoutes::COMMUNITY_SPACE_PAGES, $space('pages'), null, $noSpace(), $space('discussion')),
        'api: read a quiz' => $api('GET', ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, $space('course', $quiz), $space('course', [ApiRoutes::PARAM_PAGE => $missing]), $noSpace([ApiRoutes::PARAM_PAGE => $missing]), $space('course', $noQuiz)),
        'api: delete a quiz' => $api('DELETE', ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, $space('course', $quiz), $space('course', [ApiRoutes::PARAM_PAGE => $missing]), $noSpace([ApiRoutes::PARAM_PAGE => $missing]), $space('course', $noQuiz)),
        'api: answer a quiz' => $api('POST', ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS, $space('course', $quiz), $space('course', [ApiRoutes::PARAM_PAGE => $missing]), $noSpace([ApiRoutes::PARAM_PAGE => $missing]), $space('course', $noQuiz)),
        'api: list the materials' => $api('GET', ApiRoutes::COMMUNITY_SPACE_MATERIALS, $space('course'), null, $noSpace()),
        'api: download a material' => $api('GET', ApiRoutes::COMMUNITY_SPACE_MATERIAL_DOWNLOAD, $space('course', $material), $space('course', [ApiRoutes::PARAM_MATERIAL => $missing]), $noSpace([ApiRoutes::PARAM_MATERIAL => $missing])),
        'api: change a material' => $api('PATCH', ApiRoutes::COMMUNITY_SPACE_MATERIAL, $space('course', $material), $space('course', [ApiRoutes::PARAM_MATERIAL => $missing]), $noSpace([ApiRoutes::PARAM_MATERIAL => $missing])),
        // Resources
        'api: read a resource' => $api('GET', ApiRoutes::COMMUNITY_SPACE_RESOURCE, $space('resources', $resource), $space('resources', [ApiRoutes::PARAM_RESOURCE => $missing]), $noSpace([ApiRoutes::PARAM_RESOURCE => $missing])),
        'api: list the comments of a resource' => $api('GET', ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, $space('resources', $resource), $space('resources', [ApiRoutes::PARAM_RESOURCE => $missing]), $noSpace([ApiRoutes::PARAM_RESOURCE => $missing])),
        'api: delete a comment of a resource' => $api('DELETE', ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, $space('resources', [...$resource, ApiRoutes::PARAM_RESOURCE_COMMENT => $t->resourceComment->cuid]), $space('resources', [...$resource, ApiRoutes::PARAM_RESOURCE_COMMENT => $missing]), $noSpace([ApiRoutes::PARAM_RESOURCE => $missing, ApiRoutes::PARAM_RESOURCE_COMMENT => $missing])),
        // Events
        'api: read an event' => $api('GET', ApiRoutes::COMMUNITY_SPACE_EVENT, $space('events', $event), $space('events', [ApiRoutes::PARAM_EVENT => $missing]), $noSpace([ApiRoutes::PARAM_EVENT => $missing])),
        'api: list the comments of an event' => $api('GET', ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENTS, $space('events', $event), $space('events', [ApiRoutes::PARAM_EVENT => $missing]), $noSpace([ApiRoutes::PARAM_EVENT => $missing])),
        'api: answer an event' => $api('PUT', ApiRoutes::COMMUNITY_SPACE_EVENT_ANSWER, $space('events', $event), $space('events', [ApiRoutes::PARAM_EVENT => $missing]), $noSpace([ApiRoutes::PARAM_EVENT => $missing])),
        'api: delete a comment of an event' => $api('DELETE', ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENT, $space('events', [...$event, ApiRoutes::PARAM_COMMENT => $t->eventComment->cuid]), $space('events', [...$event, ApiRoutes::PARAM_COMMENT => $missing]), $noSpace([ApiRoutes::PARAM_EVENT => $missing, ApiRoutes::PARAM_COMMENT => $missing])),

        // The pages
        'web: the space' => $web('discussion', '', null, ''),
        'web: a post' => $web('discussion', '/post/'.$t->post->slug, '/post/'.HIDDEN_MISSING_SLUG, '/post/'.$t->post->slug),
        'web: a page' => $web('pages', '/page/'.$t->page->slug, '/page/'.HIDDEN_MISSING_SLUG, '/page/'.$t->page->slug, hiddenWeb($s['discussion']->slug, '/page/'.$t->page->slug)),
        'web: a lesson' => $web('course', '/page/'.$t->lesson->slug, '/page/'.HIDDEN_MISSING_SLUG, '/page/'.$t->lesson->slug),
        'web: a resource' => $web('resources', '/resource/'.$t->resource->slug, '/resource/'.HIDDEN_MISSING_SLUG, '/resource/'.$t->resource->slug, hiddenWeb($s['pages']->slug, '/resource/'.$t->resource->slug)),
        'web: an event' => $web('events', '/event/'.$t->event->slug, '/event/'.HIDDEN_MISSING_SLUG, '/event/'.$t->event->slug, hiddenWeb($s['resources']->slug, '/event/'.$t->event->slug)),
        'web: the form of a new event' => $web('events', '/events/new', null, '/events/new', hiddenWeb($s['discussion']->slug, '/events/new')),
        'web: the form of an event' => $web('events', '/event/'.$t->event->slug.'/edit', '/event/'.HIDDEN_MISSING_SLUG.'/edit', '/event/'.$t->event->slug.'/edit'),
    ];
}

const HIDDEN_KIND_NAMES = [
    'api: read the space', 'api: change the space', 'api: visit the space', 'api: list the posts', 'api: read a post',
    'api: change a post', 'api: follow a post', 'api: list the comments of a post', 'api: change a comment of a post',
    'api: read a page', 'api: add a page', 'api: read a quiz', 'api: delete a quiz', 'api: answer a quiz',
    'api: list the materials', 'api: download a material', 'api: change a material', 'api: read a resource',
    'api: list the comments of a resource', 'api: delete a comment of a resource', 'api: read an event',
    'api: list the comments of an event', 'api: answer an event', 'api: delete a comment of an event', 'web: the space',
    'web: a post', 'web: a page', 'web: a lesson', 'web: a resource', 'web: an event', 'web: the form of a new event',
    'web: the form of an event',
];

/**
 * The readers of the matrix: who sends the request, and whether it asks for JSON
 *
 * @return array<string, array{user: string|null, json: bool}>
 */
function hiddenReaders(): array
{
    return [
        'a visitor' => ['user' => null, 'json' => false],
        'a visitor who asks for JSON' => ['user' => null, 'json' => true],
        'a person who is not a member' => ['user' => 'outsider', 'json' => false],
        'a person who is not a member who asks for JSON' => ['user' => 'outsider', 'json' => true],
        'a member outside the groups' => ['user' => 'member', 'json' => false],
        'a member outside the groups who asks for JSON' => ['user' => 'member', 'json' => true],
    ];
}

/**
 * One request of a reader, in a new browser. A request of the API always sends a JSON body; without JSON it accepts
 * anything (`*\/*`), as curl does
 */
function hiddenAsk(?User $user, string $method, string $url, bool $json, bool $web): TestResponse
{
    newBrowser();
    if ($user !== null) {
        test()->actingAs(User::query()->findOrFail($user->id));
    }

    if (! $web) {
        return test()->call($method, $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => $json ? 'application/json' : '*/*'], '{}');
    }

    return $json ? test()->json($method, $url) : test()->call($method, $url);
}

/**
 * What an answer tells: the status, the address of a redirect, and the body (the JSON, or the Inertia page without the
 * address of the request). The plain error page of the test environment is compared by its status
 *
 * @return array{status: int, location: string|null, body: mixed}
 */
function hiddenAnswer(TestResponse $response): array
{
    $content = (string) $response->getContent();
    $body = json_decode($content, true);
    if (! is_array($body)) {
        try {
            $body = $response->inertiaPage();
            unset($body['url'], $body['props']['ziggy']['location']);
        } catch (Throwable) {
            $body = null;
        }
    }

    return ['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'), 'body' => $body];
}

test('a reader who cannot read a space gets the answer of a space or an item that does not exist, before any item is read', function (string $reader, string $kind) {
    $who = hiddenReaders()[$reader];
    $route = hiddenKinds()[$kind];
    $user = $who['user'] === null ? null : $this->c[$who['user']];

    $answers = [];
    foreach ($route['urls'] as $case => $url) {
        $answers[$case] = hiddenAnswer(hiddenAsk($user, $route['method'], $url, $who['json'], $route['web']));
    }

    if ($who['user'] === 'member') {
        // Rule 2: the member may learn that the space exists, and nothing of what is in it
        expect($answers['hidden']['status'])->toBe(403, "{$kind}: the refusal of the space");
        foreach (array_diff_key($answers, ['missing space' => true]) as $case => $answer) {
            expect($answer['status'])->toBe(403, "{$kind}, {$case}: the same refusal as for the item that exists");
        }

        return;
    }

    // Rule 1: a visitor and a person who is not a member never learn that the space or the item exists
    expect($answers['hidden']['status'])->not->toBe(200, "{$kind}: the reader does not read it");
    foreach ($answers as $case => $answer) {
        expect($answer)->toBe($answers['hidden'], "{$kind}, {$case}: the same answer as for the item that exists");
    }
})->with(array_keys(hiddenReaders()))->with(HIDDEN_KIND_NAMES);

test('the matrix walks every kind of route', function () {
    expect(array_keys(hiddenKinds()))->toBe(HIDDEN_KIND_NAMES);
});

test('a member of the groups of the space keeps the answers of before', function (string $kind) {
    $route = hiddenKinds()[$kind];
    $response = hiddenAsk($this->insider, $route['method'], $route['urls']['hidden'], true, $route['web']);

    // The address names the item: a read answers it, a write gets its own answer (a missing right, a body that is not valid)
    if ($route['method'] === 'GET' && ! str_contains($kind, 'form')) {
        expect($response->getStatusCode())->toBe(200, $kind);
    } else {
        expect($response->getStatusCode())->not->toBeIn([401, 404], $kind);
    }
})->with(HIDDEN_KIND_NAMES);

test('in a public space, a reader who cannot see an event gets the answer of an event that does not exist', function (?string $reader) {
    $community = $this->c['community'];
    $community->update(['visibility' => KnowiiCommunityVisibility::Public]);
    $public = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
        ->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'Meetups']);
    $draft = EventModel::factory()->inSpace($public)->hostedBy($this->c['owner'])->draft()->create();
    $published = EventModel::factory()->inSpace($public)->hostedBy($this->c['owner'])->create();
    $user = $reader === null ? null : $this->c[$reader];
    $url = static fn (string $route, string $event): string => hiddenApi($route, [ApiRoutes::PARAM_SPACE => $public->cuid, ApiRoutes::PARAM_EVENT => $event]);

    foreach ([ApiRoutes::COMMUNITY_SPACE_EVENT, ApiRoutes::COMMUNITY_SPACE_EVENT_COMMENTS] as $route) {
        // The control: the published event is read
        expect(hiddenAsk($user, 'GET', $url($route, $published->cuid), true, false)->getStatusCode())->toBe(200);

        $hidden = hiddenAnswer(hiddenAsk($user, 'GET', $url($route, $draft->cuid), true, false));
        expect($hidden['status'])->toBe(404)
            ->and(hiddenAnswer(hiddenAsk($user, 'GET', $url($route, hiddenMissingCuid()), true, false)))->toBe($hidden);
    }
})->with(['a visitor' => null, 'a person who is not a member' => 'outsider', 'a member' => 'member']);

test('a visitor gets the answer of a space that does not exist for a public chat space', function () {
    $chat = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Chat, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'Lounge']);

    $hidden = hiddenAnswer(hiddenAsk(null, 'GET', hiddenApi(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_SPACE => $chat->cuid]), true, false));
    expect($hidden['status'])->toBe(404)
        ->and(hiddenAnswer(hiddenAsk(null, 'GET', hiddenApi(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_SPACE => hiddenMissingCuid()]), true, false)))->toBe($hidden)
        ->and(hiddenAnswer(hiddenAsk(null, 'GET', hiddenWeb($chat->slug), true, true)))->toBe(hiddenAnswer(hiddenAsk(null, 'GET', hiddenWeb(HIDDEN_MISSING_SLUG), true, true)));
});

/*
 * Rule 3, accepted in writing (ADR 0011): every identifier of these routes is a random cuid. The answers below are a
 * choice: a change of one of them is a change of that decision
 */
test('on the API, an item of the community that exists and that the reader may not use answers 403, one that does not exist 404', function (string $reader, string $route, Closure $params) {
    // The item of the route, else the community itself
    $existing = [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ...$params($this)];
    $missing = $existing;
    $missing[array_key_last($existing)] = hiddenMissingCuid();
    $method = $route === ApiRoutes::COMMUNITY_INVITATION ? 'DELETE' : 'GET';
    $path = static fn (array $values): string => ApiRoutes::path($route, $values);

    expect(hiddenAsk($this->c[$reader], $method, $path($existing), true, false)->getStatusCode())->toBe(403)
        ->and(hiddenAsk($this->c[$reader], $method, $path($missing), true, false)->getStatusCode())->toBe(404);
})->with([
    'a person who is not a member, the community' => ['outsider', ApiRoutes::COMMUNITY, fn () => []],
    'a person who is not a member, the space groups of the community' => ['outsider', ApiRoutes::COMMUNITY_SPACE_GROUPS, fn () => []],
    'a member, the access groups of a space group' => ['member', ApiRoutes::COMMUNITY_SPACE_GROUP_ACCESS_GROUPS, fn ($test) => [
        ApiRoutes::PARAM_SPACE_GROUP => $test->spaces['discussion']->spaceGroup->cuid,
    ]],
    'a member, an invitation' => ['member', ApiRoutes::COMMUNITY_INVITATION, fn ($test) => [
        ApiRoutes::PARAM_INVITATION => $test->c['community']->communityInvitations()->create(['email' => 'grace@example.com', 'role' => 'member'])->cuid,
    ]],
    'a member, an import of invitations' => ['member', ApiRoutes::COMMUNITY_INVITATION_IMPORT, fn ($test) => [
        ApiRoutes::PARAM_INVITATION_IMPORT => InvitationImport::factory()->create(['community_id' => $test->c['community']->id, 'user_id' => $test->c['owner']->id])->cuid,
    ]],
    'a member, an invite link' => ['member', ApiRoutes::COMMUNITY_INVITE_LINK, fn ($test) => [
        ApiRoutes::PARAM_INVITE_LINK => InviteLink::factory()->create(['community_id' => $test->c['community']->id])->cuid,
    ]],
]);
