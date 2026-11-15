<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCourseMaterialKind;
use App\Enums\KnowiiQuizQuestionKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\CourseMaterial;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Visus\Cuid2\Cuid2;

/*
 * Visitors read the public spaces (#1387, ADR 0044). Three readers: visitor (not signed in), outsider (signed in, not a
 * member), member. One matrix per web page and per API route, against a public, a members and a groups space, and a
 * public chat space. Everything that reaches a visitor is checked for member data.
 */

const PUBLIC_SPACES_VISIBILITIES = ['public', 'members', 'groups'];

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->author = $this->c['member'];
    // Data of the author that no visitor and no outsider may ever get
    $this->author->profile->forceFill(['bio' => 'SECRET-BIO-OF-THE-AUTHOR', 'phone' => 'SECRET-PHONE-OF-THE-AUTHOR'])->save();

    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $visibilities = ['public' => KnowiiSpaceVisibility::Public, 'members' => KnowiiSpaceVisibility::Members, 'groups' => KnowiiSpaceVisibility::Groups];
    $make = fn (KnowiiSpaceType $type, KnowiiSpaceVisibility $visibility) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => $visibility]);

    $this->discussion = $this->pagesSpace = $this->resourcesSpace = $this->course = $this->post = $this->comment = $this->page = $this->resource = $this->lesson = $this->material = [];
    foreach ($visibilities as $key => $visibility) {
        $discussion = $make(KnowiiSpaceType::Discussion, $visibility);
        $post = Post::factory()->inSpace($discussion)->create(['author_id' => $this->author->id]);
        $comment = Comment::factory()->onPost($post)->create(['author_id' => $this->author->id]);
        $post->reactions()->create(['community_id' => $post->community_id, 'user_id' => $this->c['moderator']->id, 'emoji' => '👍']);
        $this->discussion[$key] = $discussion;
        $this->post[$key] = $post;
        $this->comment[$key] = $comment;

        $pages = $make(KnowiiSpaceType::Pages, $visibility);
        $this->pagesSpace[$key] = $pages;
        $this->page[$key] = Page::factory()->inSpace($pages)->create(['author_id' => $this->author->id, 'title' => 'Start here', 'position' => 1]);

        $resources = $make(KnowiiSpaceType::Resources, $visibility);
        $this->resourcesSpace[$key] = $resources;
        $resource = CommunityResource::factory()->create(['space_id' => $resources->id, 'curator_id' => $this->author->profile->id]);
        ResourceCapture::factory()->create(['community_resource_id' => $resource->id, 'markdown' => 'SECRET-CAPTURED-ARTICLE', 'word_count' => 1200, 'reading_time' => 6]);
        Comment::create(['cuid' => (new Cuid2)->toString(), 'community_id' => $resources->community_id, 'community_resource_id' => $resource->id, 'author_id' => $this->author->id, 'body' => 'A comment on the resource', 'body_html' => '<p>A comment on the resource</p>']);
        $this->resource[$key] = $resource;

        $course = $make(KnowiiSpaceType::Course, $visibility);
        $this->course[$key] = $course;
        $lesson = Page::factory()->inSpace($course)->create(['author_id' => $this->author->id, 'title' => 'Lesson one', 'position' => 1]);
        $this->lesson[$key] = $lesson;
        $quiz = Quiz::create(['cuid' => (new Cuid2)->toString(), 'community_id' => $course->community_id, 'page_id' => $lesson->id, 'pass_mark' => 70]);
        QuizQuestion::create([
            'cuid' => (new Cuid2)->toString(), 'quiz_id' => $quiz->id, 'position' => 1, 'kind' => KnowiiQuizQuestionKind::Single,
            'prompt' => 'Which one?', 'prompt_html' => '<p>Which one?</p>', 'explanation' => 'SECRET-EXPLANATION', 'explanation_html' => '<p>SECRET-EXPLANATION</p>',
        ]);
        $this->material[$key] = publicLinkMaterial($course, $lesson);
    }

    $this->chat = [
        'public' => $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Public),
        'members' => $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Members),
    ];
    foreach ($this->chat as $chat) {
        ChatMessage::factory()->inSpace($chat)->create(['author_id' => $this->author->id, 'body' => 'SECRET-CHAT-LINE']);
    }
    $this->link = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Link, 'visibility' => KnowiiSpaceVisibility::Public, 'url' => 'https://example.com/book']);
});

function publicLinkMaterial(Space $course, Page $lesson): CourseMaterial
{
    return CourseMaterial::query()->create([
        'community_id' => $course->community_id,
        'space_id' => $course->id,
        'page_id' => $lesson->id,
        'title' => 'Template',
        'kind' => KnowiiCourseMaterialKind::Link,
        'url' => 'https://example.com/template',
        'position' => 0,
    ]);
}

/**
 * @return array<string, string> The web address of each kind of page, for a visibility
 */
function publicWebUrls(string $visibility): array
{
    $test = test();
    $slug = $test->c['community']->slug;

    return [
        'space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->discussion[$visibility]->slug]),
        'post' => route('posts.show', ['communitySlug' => $slug, 'spaceSlug' => $test->discussion[$visibility]->slug, 'postSlug' => $test->post[$visibility]->slug]),
        'pages space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->pagesSpace[$visibility]->slug]),
        'page' => route('pages.show', ['communitySlug' => $slug, 'spaceSlug' => $test->pagesSpace[$visibility]->slug, 'pageSlug' => $test->page[$visibility]->slug]),
        'course' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->course[$visibility]->slug]),
        'lesson' => route('pages.show', ['communitySlug' => $slug, 'spaceSlug' => $test->course[$visibility]->slug, 'pageSlug' => $test->lesson[$visibility]->slug]),
        'resources space' => route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $test->resourcesSpace[$visibility]->slug]),
        'resource' => route('resources.show', ['communitySlug' => $slug, 'spaceSlug' => $test->resourcesSpace[$visibility]->slug, 'resourceSlug' => $test->resource[$visibility]->slug]),
    ];
}

/**
 * Get an address as a reader: visitor, outsider, member or owner
 */
function publicReadAs(string $reader, string $url): TestResponse
{
    $test = test();

    if ($reader === 'visitor') {
        // Nobody is signed in, also after an earlier request of the test as someone else
        app('auth')->forgetGuards();

        return $test->get($url);
    }

    return $test->actingAs($test->c[$reader])->get($url);
}

dataset('web pages', ['space', 'post', 'pages space', 'page', 'course', 'lesson', 'resources space', 'resource']);

// ---------------------------------------------------------------------------------------------------------------------
// Web pages: the matrix
// ---------------------------------------------------------------------------------------------------------------------

test('web pages: a public space is read by a visitor, an outsider and a member', function (string $kind) {
    $url = publicWebUrls('public')[$kind];

    publicReadAs('visitor', $url)->assertOk();
    publicReadAs('outsider', $url)->assertOk();
    publicReadAs('member', $url)->assertOk();
})->with('web pages');

test('web pages: a members space sends a visitor to the sign-in page, refuses an outsider, and opens for a member', function (string $kind) {
    $url = publicWebUrls('members')[$kind];

    publicReadAs('visitor', $url)->assertRedirect(route('login'));
    publicReadAs('outsider', $url)->assertForbidden();
    publicReadAs('member', $url)->assertOk();
})->with('web pages');

test('web pages: a groups space sends a visitor to the sign-in page, refuses an outsider and a member outside the groups, and opens for the owner', function (string $kind) {
    $url = publicWebUrls('groups')[$kind];

    publicReadAs('visitor', $url)->assertRedirect(route('login'));
    publicReadAs('outsider', $url)->assertForbidden();
    publicReadAs('member', $url)->assertForbidden();
    publicReadAs('owner', $url)->assertOk();
})->with('web pages');

test('web pages: a chat space is never read by a visitor, also when it is public (an outsider keeps what they have)', function () {
    $slug = $this->c['community']->slug;
    $public = route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $this->chat['public']->slug]);
    $members = route('spaces.show', ['communitySlug' => $slug, 'spaceSlug' => $this->chat['members']->slug]);

    publicReadAs('visitor', $public)->assertRedirect(route('login'));
    publicReadAs('outsider', $public)->assertOk();
    publicReadAs('member', $public)->assertOk();
    publicReadAs('visitor', $members)->assertRedirect(route('login'));
    publicReadAs('outsider', $members)->assertForbidden();
});

test('web pages: a link space is read by a visitor when it is public', function () {
    $url = route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->link->slug]);

    publicReadAs('visitor', $url)->assertOk()->assertInertia(fn ($page) => $page->component('Spaces/Show')->where('space.url', 'https://example.com/book'));
});

test('web pages: the other pages of a community stay for members, a visitor goes to the sign-in page', function (string $route, array $parameters) {
    $parameters = ['communitySlug' => $this->c['community']->slug, ...$parameters];

    $this->get(route($route, $parameters))->assertRedirect(route('login'));
    $this->actingAs($this->c['outsider'])->get(route($route, $parameters))->assertForbidden();
})->with([
    'community home' => ['communities.show', []],
    'about' => ['communities.about', []],
    'settings' => ['communities.settings', []],
    'messages' => ['messages.index', []],
]);

test('web pages: a member page is not shown to a visitor', function () {
    $this->get(route('members.show', ['communitySlug' => $this->c['community']->slug, 'username' => $this->author->username]))->assertRedirect(route('login'));
});

test('web pages: a space that does not exist answers a visitor like a private one', function () {
    $this->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => 'nothing-here']))->assertRedirect(route('login'));
    $this->get(route('spaces.show', ['communitySlug' => 'no-such-community', 'spaceSlug' => 'nothing-here']))->assertRedirect(route('login'));
    $this->actingAs($this->c['outsider'])->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => 'nothing-here']))->assertNotFound();
});

test('web pages: an unverified account is refused, like everywhere else', function () {
    $unverified = User::factory()->unverified()->withUserProfile()->create();

    $this->actingAs($unverified)->get(publicWebUrls('public')['space'])->assertRedirect(route('verification.notice'));
});

// ---------------------------------------------------------------------------------------------------------------------
// Web pages: what a visitor gets
// ---------------------------------------------------------------------------------------------------------------------

/**
 * The keys that must not appear anywhere in what reaches a visitor or an outsider, and the values that must not either
 */
const PUBLIC_SPACES_FORBIDDEN_KEYS = [
    'email', 'phone', 'bio', 'access_groups', 'moderator_permissions', 'member_count', 'users_count',
    'direct_messages_enabled', 'unread_direct_messages', 'userProfile', 'field_visibility', 'online_status', 'show_online_status',
];

/**
 * @param  array<mixed>  $data
 * @return list<string>
 */
function publicKeysOf(array $data): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        $keys[] = (string) $key;
        if (is_array($value)) {
            $keys = [...$keys, ...publicKeysOf($value)];
        }
    }

    return $keys;
}

/**
 * @return array<string, mixed>
 */
function publicPropsOf(TestResponse $response): array
{
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

/**
 * What reaches a reader holds no data of the members. The signed-in reader has their own account in the shared props
 * (`auth`, `userProfile`): their own data is theirs. A visitor has none of it.
 *
 * @param  array<string, mixed>  $props
 */
function publicAssertNoMemberData(array $props, string $reader = 'visitor'): void
{
    $test = test();
    $scanned = $reader === 'visitor' ? $props : array_diff_key($props, array_flip(['auth', 'userProfile']));
    $json = (string) json_encode($scanned);

    expect(array_intersect(publicKeysOf($scanned), PUBLIC_SPACES_FORBIDDEN_KEYS))->toBe([], 'a field of the "never" list reaches the reader');
    foreach ([$test->author->email, $test->author->profile->email, 'SECRET-BIO-OF-THE-AUTHOR', 'SECRET-PHONE-OF-THE-AUTHOR', 'SECRET-CAPTURED-ARTICLE', 'SECRET-EXPLANATION', 'SECRET-CHAT-LINE'] as $secret) {
        expect($json)->not->toContain($secret);
    }
    // Nobody is named but the authors of what is read
    expect($json)->not->toContain($test->c['owner']->email)->not->toContain($test->c['admin']->email)->not->toContain($test->c['moderator']->email)
        ->not->toContain($test->c['admin']->username);
    if ($reader === 'visitor') {
        expect($props['auth']['user'] ?? null)->toBeNull()
            ->and($props)->not->toHaveKey('userProfile')
            ->and($json)->not->toContain($test->c['outsider']->email);
    }
}

test('web pages: the props of a visitor and of an outsider hold no member data, and nothing of the reader', function (string $reader, string $kind) {
    $response = publicReadAs($reader, publicWebUrls('public')[$kind])->assertOk();
    $props = publicPropsOf($response);

    publicAssertNoMemberData($props, $reader);

    // The community is cut for someone who is not a member
    expect($props['community'])->toBe([
        'cuid' => $this->c['community']->cuid,
        'name' => $this->c['community']->name,
        'slug' => $this->c['community']->slug,
        'description' => $this->c['community']->description ?? '',
        'visibility' => $this->c['community']->visibility->value,
        'is_member' => false,
    ]);
    // No right, no progress, no result, no article
    foreach ($props['permissions'] as $name => $value) {
        expect($value)->toBeFalse("permissions.{$name} must be false for a {$reader}");
    }
    expect($props['progress'] ?? null)->toBeNull()
        ->and($props['chat'] ?? null)->toBeNull()
        ->and($props['article'] ?? null)->toBeNull();
    if ($reader === 'visitor') {
        expect($props['quiz'] ?? null)->toBeNull();
    }
    // Reactions are counts: nobody reacted as the reader
    $json = (string) json_encode($props);
    expect($json)->not->toContain('"reacted":true');
})->with(['visitor', 'outsider'])->with('web pages');

test('web pages: a visitor sees the authors of what is public, with their name, username and avatar only', function () {
    $post = publicPropsOf(publicReadAs('visitor', publicWebUrls('public')['post']))['post'];

    expect($post['author'])->toBe(['cuid' => $this->author->cuid, 'name' => $this->author->name, 'username' => $this->author->username, 'avatar_url' => $this->author->avatar_url])
        ->and($post['reactions'])->toBe([['emoji' => '👍', 'count' => 1, 'reacted' => false]]);

    $comments = publicPropsOf(publicReadAs('visitor', publicWebUrls('public')['post']))['comments'];
    expect($comments[0]['author'])->toBe(['cuid' => $this->author->cuid, 'name' => $this->author->name, 'username' => $this->author->username, 'avatar_url' => $this->author->avatar_url]);
});

test('web pages: the navigation of a visitor holds the public spaces only, without chat, and without group names', function () {
    $tree = publicPropsOf(publicReadAs('visitor', publicWebUrls('public')['space']))['spaceGroups'];
    $spaces = collect($tree)->flatMap(fn (array $group) => $group['spaces'])->pluck('cuid');

    expect($spaces)->toContain($this->discussion['public']->cuid)->toContain($this->link->cuid)
        ->not->toContain($this->discussion['members']->cuid)
        ->not->toContain($this->discussion['groups']->cuid)
        ->not->toContain($this->chat['public']->cuid)
        ->not->toContain($this->chat['members']->cuid);
});

test('web pages: the captured article of a resource is for members of the community only', function () {
    $url = publicWebUrls('public')['resource'];

    expect(publicPropsOf(publicReadAs('visitor', $url))['article'])->toBeNull()
        ->and(publicPropsOf(publicReadAs('outsider', $url))['article'])->toBeNull()
        ->and(publicPropsOf(publicReadAs('member', $url))['article'])->not->toBeNull();
});

test('web pages: a member keeps the full community and their rights', function () {
    $props = publicPropsOf(publicReadAs('member', publicWebUrls('public')['space']));

    expect($props['community'])->toHaveKeys(['moderator_permissions', 'direct_messages_enabled', 'is_member'])
        ->and($props['community']['is_member'])->toBeTrue()
        ->and($props['permissions']['canContribute'])->toBeTrue();
});

test('web pages: the shared props of a visitor hold no profile', function () {
    $props = publicPropsOf(publicReadAs('visitor', publicWebUrls('public')['space']));

    expect($props)->not->toHaveKey('userProfile')
        ->and($props['auth']['user'])->toBeNull();
});

test('web pages: only a manager gets the access groups of the spaces', function () {
    $treeOf = fn (string $reader) => collect(publicPropsOf(publicReadAs($reader, publicWebUrls('public')['space']))['spaceGroups'])
        ->flatMap(fn (array $group) => $group['spaces']);

    expect($treeOf('owner')->contains(fn (array $space) => array_key_exists('access_groups', $space)))->toBeTrue();
    foreach (['visitor', 'outsider', 'member'] as $reader) {
        expect($treeOf($reader)->contains(fn (array $space) => array_key_exists('access_groups', $space)))->toBeFalse();
    }
});

test('web pages: after the sign-in the visitor comes back to the address that sent them away', function () {
    $url = publicWebUrls('members')['space'];

    $this->get($url)->assertRedirect(route('login'));
    $this->post('/login', ['email' => $this->c['member']->email, 'password' => 'password'])->assertRedirect($url);
});

test('web pages: "Sign in" on a public page comes back to that page', function () {
    $url = publicWebUrls('public')['post'];

    $this->get($url)->assertOk();
    $this->post('/login', ['email' => $this->c['outsider']->email, 'password' => 'password'])->assertRedirect($url);
});

// ---------------------------------------------------------------------------------------------------------------------
// Search engines: unchanged in this pull request
// ---------------------------------------------------------------------------------------------------------------------

test('a public space page stays noindex for a visitor, and robots.txt still blocks the communities (#1387)', function () {
    config(['robots.allow_indexing' => true]);

    $response = $this->get(publicWebUrls('public')['space'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow"');
    expect((string) $this->get('/robots.txt')->getContent())->toContain('Disallow: /community/');
    // The home page of the site stays indexable: only the communities are out
    $this->get('/')->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
});

// ---------------------------------------------------------------------------------------------------------------------
// API: the matrix
// ---------------------------------------------------------------------------------------------------------------------

/**
 * @return array<string, string> The address of each public read, for a visibility
 */
function publicApiUrls(string $visibility): array
{
    $t = test();
    $community = $t->c['community']->cuid;
    $space = fn (Space $space) => [ApiRoutes::PARAM_COMMUNITY => $community, ApiRoutes::PARAM_SPACE => $space->cuid];

    return [
        'space' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, $space($t->discussion[$visibility])),
        'posts' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, $space($t->discussion[$visibility])),
        'post' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST, [...$space($t->discussion[$visibility]), ApiRoutes::PARAM_POST => $t->post[$visibility]->cuid]),
        'comments' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [...$space($t->discussion[$visibility]), ApiRoutes::PARAM_POST => $t->post[$visibility]->cuid]),
        'pages' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGES, $space($t->pagesSpace[$visibility])),
        'page' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_PAGE, [...$space($t->pagesSpace[$visibility]), ApiRoutes::PARAM_PAGE => $t->page[$visibility]->cuid]),
        'materials' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_MATERIALS, $space($t->course[$visibility])),
        'resources' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, $space($t->resourcesSpace[$visibility])),
        'resource' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [...$space($t->resourcesSpace[$visibility]), ApiRoutes::PARAM_RESOURCE => $t->resource[$visibility]->cuid]),
        'resource comments' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [...$space($t->resourcesSpace[$visibility]), ApiRoutes::PARAM_RESOURCE => $t->resource[$visibility]->cuid]),
    ];
}

function publicReadApiAs(string $reader, string $url): TestResponse
{
    $test = test();

    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return $test->getJson($url);
    }

    return $test->actingAs($test->c[$reader])->getJson($url);
}

dataset('public api reads', ['space', 'posts', 'post', 'comments', 'pages', 'page', 'materials', 'resources', 'resource', 'resource comments']);

test('API: a public space is read by a visitor, an outsider and a member', function (string $route) {
    $url = publicApiUrls('public')[$route];

    publicReadApiAs('visitor', $url)->assertOk();
    publicReadApiAs('outsider', $url)->assertOk();
    publicReadApiAs('member', $url)->assertOk();
})->with('public api reads');

test('API: a members space answers a visitor 401, an outsider 403, a member 200', function (string $route) {
    $url = publicApiUrls('members')[$route];

    publicReadApiAs('visitor', $url)->assertUnauthorized();
    publicReadApiAs('outsider', $url)->assertForbidden();
    publicReadApiAs('member', $url)->assertOk();
})->with('public api reads');

test('API: a groups space answers a visitor 401, an outsider and a member outside the groups 403, the owner 200', function (string $route) {
    $url = publicApiUrls('groups')[$route];

    publicReadApiAs('visitor', $url)->assertUnauthorized();
    publicReadApiAs('outsider', $url)->assertForbidden();
    publicReadApiAs('member', $url)->assertForbidden();
    publicReadApiAs('owner', $url)->assertOk();
})->with('public api reads');

test('API: a public chat space is not given to a visitor, the space of an outsider is unchanged', function () {
    $url = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid, ApiRoutes::PARAM_SPACE => $this->chat['public']->cuid]);

    publicReadApiAs('visitor', $url)->assertUnauthorized();
    publicReadApiAs('outsider', $url)->assertOk();
});

test('API: what a visitor gets holds no member data', function (string $route) {
    $response = publicReadApiAs('visitor', publicApiUrls('public')[$route])->assertOk();
    $data = (array) $response->json();
    $json = (string) json_encode($data);

    expect(array_intersect(publicKeysOf($data), PUBLIC_SPACES_FORBIDDEN_KEYS))->toBe([]);
    foreach ([$this->author->email, 'SECRET-BIO-OF-THE-AUTHOR', 'SECRET-PHONE-OF-THE-AUTHOR', 'SECRET-CAPTURED-ARTICLE', $this->c['owner']->email, $this->c['moderator']->email] as $secret) {
        expect($json)->not->toContain($secret);
    }
    expect($json)->not->toContain('"reacted":true');
})->with('public api reads');

test('API: the resource of a visitor has no captured article, a member has it', function () {
    $url = publicApiUrls('public')['resource'];

    expect(publicReadApiAs('visitor', $url)->json('data.capture'))->toBeNull()
        ->and(publicReadApiAs('outsider', $url)->json('data.capture'))->toBeNull()
        ->and(publicReadApiAs('member', $url)->json('data.capture.markdown'))->toBe('SECRET-CAPTURED-ARTICLE');
});

test('API: the access groups of a space are for the people who manage spaces', function () {
    $url = publicApiUrls('public')['space'];

    foreach (['visitor', 'outsider', 'member'] as $reader) {
        expect(publicReadApiAs($reader, $url)->json('data'))->not->toHaveKey('access_groups');
    }
    expect(publicReadApiAs('owner', $url)->json('data'))->toHaveKey('access_groups');
});

test('API: everything else stays for signed-in people', function (string $method, string $route, array $parameters) {
    $space = $this->discussion['public'];
    $community = $this->c['community'];
    $path = ApiRoutes::path($route, [
        ApiRoutes::PARAM_COMMUNITY => $community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_POST => $this->post['public']->cuid,
        ApiRoutes::PARAM_PAGE => $this->lesson['public']->cuid,
        ApiRoutes::PARAM_MATERIAL => $this->material['public']->cuid,
        ...$parameters,
    ]);

    $this->json($method, $path)->assertUnauthorized();
})->with([
    'who reacted' => ['GET', ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, []],
    'react' => ['POST', ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, []],
    'chat messages' => ['GET', ApiRoutes::COMMUNITY_SPACE_MESSAGES, []],
    'online members of a space' => ['GET', ApiRoutes::COMMUNITY_SPACE_ONLINE_MEMBERS, []],
    'online members' => ['GET', ApiRoutes::COMMUNITY_ONLINE_MEMBERS, []],
    'member search' => ['GET', ApiRoutes::COMMUNITY_MEMBERS, []],
    'the community' => ['GET', ApiRoutes::COMMUNITY, []],
    'the space groups' => ['GET', ApiRoutes::COMMUNITY_SPACE_GROUPS, []],
    'the quiz of a lesson' => ['GET', ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, []],
    'the progress in a course' => ['GET', ApiRoutes::COMMUNITY_SPACE_PROGRESS, []],
    'the download of a course material' => ['GET', ApiRoutes::COMMUNITY_SPACE_MATERIAL_DOWNLOAD, []],
    'write a post' => ['POST', ApiRoutes::COMMUNITY_SPACE_POSTS, []],
    'write a comment' => ['POST', ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, []],
    'change a post' => ['PATCH', ApiRoutes::COMMUNITY_SPACE_POST, []],
    'delete a post' => ['DELETE', ApiRoutes::COMMUNITY_SPACE_POST, []],
    'change a space' => ['PATCH', ApiRoutes::COMMUNITY_SPACE, []],
    'markdown preview' => ['POST', ApiRoutes::MARKDOWN_PREVIEW, []],
]);

test('API: a token keeps its abilities on the public reads', function () {
    $urls = publicApiUrls('public');

    Sanctum::actingAs($this->c['outsider'], ['posts:read']);
    $this->getJson($urls['posts'])->assertOk();
    $this->getJson($urls['pages'])->assertForbidden();
    $this->getJson($urls['resources'])->assertForbidden();
    $this->getJson($urls['space'])->assertForbidden();

    Sanctum::actingAs($this->c['outsider'], ['pages:read', 'resources:read', 'communities:read']);
    $this->getJson($urls['pages'])->assertOk();
    $this->getJson($urls['resources'])->assertOk();
    $this->getJson($urls['space'])->assertOk();
    $this->getJson($urls['posts'])->assertForbidden();
});

test('API: a token that is not accepted is a 401, never a visitor', function () {
    $this->withHeader('Authorization', 'Bearer 123|this-token-does-not-exist')->getJson(publicApiUrls('public')['posts'])->assertUnauthorized();

    $token = $this->c['member']->createToken('revoked', ['posts:read']);
    $this->c['member']->tokens()->delete();
    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)->getJson(publicApiUrls('public')['posts'])->assertUnauthorized();
});

test('API: an unverified account is refused on the public reads', function () {
    $unverified = User::factory()->unverified()->withUserProfile()->create();

    $this->actingAs($unverified)->getJson(publicApiUrls('public')['posts'])->assertForbidden();

    Sanctum::actingAs($unverified, ['*']);
    $this->getJson(publicApiUrls('public')['posts'])->assertForbidden();
});

test('API: visitors are limited per IP address, signed-in people are not', function () {
    RateLimiter::clear('ip:127.0.0.1');
    $url = publicApiUrls('public')['space'];

    for ($i = 0; $i < Constants::PUBLIC_READS_PER_MINUTE; $i++) {
        $this->getJson($url)->assertOk();
    }
    $this->getJson($url)->assertStatus(429);

    // Another address has its own count
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson($url)->assertOk();
    // A signed-in member is not counted with the visitors
    $this->actingAs($this->c['member'])->getJson($url)->assertOk();
});
