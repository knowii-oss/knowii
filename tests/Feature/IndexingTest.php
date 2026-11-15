<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiModeratorPermission;
use App\Enums\KnowiiPageKind;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\Robots;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * A community decides if search engines index its public spaces (#1487, ADR 0044): the setting, and the directive of
 * every page (header and meta tag, from Robots::forRequest).
 */

const INDEXING_INDEX = 'index, follow, max-image-preview:large';
const INDEXING_NOINDEX = 'noindex, nofollow';

beforeEach(function () {
    $this->c = communityWithMembers();
});

function indexingPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_INDEXING, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

/**
 * A community with public, members and chat spaces, and the page of each kind of content. Returns the urls by name.
 *
 * @return array<string, string>
 */
function indexingPages(Community $community): array
{
    $author = test()->c['member'];
    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $make = fn (KnowiiSpaceType $type, KnowiiSpaceVisibility $visibility) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => $visibility]);
    $slug = $community->slug;

    $discussion = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Public);
    $post = Post::factory()->inSpace($discussion)->create(['author_id' => $author->id]);
    $marker = Post::factory()->inSpace($discussion)->create(['author_id' => $author->id, 'deleted_at' => now()]);

    $pagesSpace = $make(KnowiiSpaceType::Pages, KnowiiSpaceVisibility::Public);
    $page = Page::factory()->inSpace($pagesSpace)->create(['author_id' => $author->id, 'title' => 'Start here', 'position' => 1]);
    $section = Page::factory()->inSpace($pagesSpace)->create(['author_id' => $author->id, 'title' => 'A section', 'kind' => KnowiiPageKind::Section, 'position' => 2]);

    $course = $make(KnowiiSpaceType::Course, KnowiiSpaceVisibility::Public);
    $lesson = Page::factory()->inSpace($course)->create(['author_id' => $author->id, 'title' => 'Lesson one', 'position' => 1]);

    $resources = $make(KnowiiSpaceType::Resources, KnowiiSpaceVisibility::Public);
    $resource = CommunityResource::factory()->create(['space_id' => $resources->id, 'curator_id' => $author->profile->id]);

    $members = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Members);
    $membersPost = Post::factory()->inSpace($members)->create(['author_id' => $author->id]);
    $groups = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Groups);
    $chat = $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Public);
    $link = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Link, 'visibility' => KnowiiSpaceVisibility::Public, 'url' => 'https://example.com']);

    return [
        'community home' => "/community/{$slug}",
        'public discussion space' => "/community/{$slug}/space/{$discussion->slug}",
        'public post' => "/community/{$slug}/space/{$discussion->slug}/post/{$post->slug}",
        'public marker post' => "/community/{$slug}/space/{$discussion->slug}/post/{$marker->slug}",
        'public pages space' => "/community/{$slug}/space/{$pagesSpace->slug}",
        'public page' => "/community/{$slug}/space/{$pagesSpace->slug}/page/{$page->slug}",
        'public section' => "/community/{$slug}/space/{$pagesSpace->slug}/page/{$section->slug}",
        'public course' => "/community/{$slug}/space/{$course->slug}",
        'public lesson' => "/community/{$slug}/space/{$course->slug}/page/{$lesson->slug}",
        'public resources space' => "/community/{$slug}/space/{$resources->slug}",
        'public resource' => "/community/{$slug}/space/{$resources->slug}/resource/{$resource->slug}",
        'members space' => "/community/{$slug}/space/{$members->slug}",
        'members post' => "/community/{$slug}/space/{$members->slug}/post/{$membersPost->slug}",
        'groups space' => "/community/{$slug}/space/{$groups->slug}",
        'public chat space' => "/community/{$slug}/space/{$chat->slug}",
        'public link space' => "/community/{$slug}/space/{$link->slug}",
        'unknown space' => "/community/{$slug}/space/nothing-here",
        'about' => "/community/{$slug}/about",
        'settings' => "/community/{$slug}/settings",
    ];
}

/** The pages that a visitor reads in a public space, and the public community page */
const INDEXING_INDEXABLE = [
    'community home', 'public discussion space', 'public post', 'public pages space', 'public page', 'public course', 'public lesson',
    'public resources space', 'public resource',
];

function indexingGet(string $reader, string $url): TestResponse
{
    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return test()->get($url);
    }

    return test()->actingAs(test()->c[$reader])->get($url);
}

/**
 * The pages for members only send a visitor to the sign-in page from the route middleware, before any page: that
 * redirect has no header of its own (as before this change) and never holds a page to index.
 */
function indexingAssertHeader(TestResponse $response, string $expected, string $name): void
{
    $header = $response->headers->get('X-Robots-Tag');
    if ($header === null) {
        expect($response->isRedirect())->toBeTrue("{$name} has no header only when it redirects to the sign-in page")
            ->and((string) $response->headers->get('Location'))->toContain('/login');

        return;
    }

    expect($header)->toBe($expected, "header of {$name}");
}

function indexingMeta(TestResponse $response): ?string
{
    preg_match('/<meta name="robots" content="([^"]*)"/', (string) $response->getContent(), $matches);

    return $matches[1] ?? null;
}

// ---------------------------------------------------------------------------------------------------------------------
// The setting
// ---------------------------------------------------------------------------------------------------------------------

test('indexing is off by default, and the owner and the admins turn it on and off (#1487)', function (string $role) {
    expect($this->c['community']->fresh()->indexing_enabled)->toBeFalse();

    $this->actingAs($this->c[$role])->patchJson(indexingPath($this->c['community']), ['indexing_enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.indexing_enabled', true);
    expect($this->c['community']->fresh()->indexing_enabled)->toBeTrue();

    $this->actingAs($this->c[$role])->patchJson(indexingPath($this->c['community']), ['indexing_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.indexing_enabled', false);
    expect($this->c['community']->fresh()->indexing_enabled)->toBeFalse();
})->with(['owner', 'admin']);

test('moderators, members and outsiders cannot change indexing, whatever the rights of the moderators (#1487)', function (string $role) {
    $this->c['community']->update(['moderator_permissions' => array_map(fn () => true, KnowiiModeratorPermission::defaults())]);

    $this->actingAs($this->c[$role])->patchJson(indexingPath($this->c['community']), ['indexing_enabled' => true])->assertForbidden();

    expect($this->c['community']->fresh()->indexing_enabled)->toBeFalse();
})->with(['moderator', 'member', 'outsider']);

test('a visitor cannot change indexing (#1487)', function () {
    app('auth')->forgetGuards();

    $this->patchJson(indexingPath($this->c['community']), ['indexing_enabled' => true])->assertUnauthorized();
});

test('indexing needs a boolean (#1487)', function (array $input) {
    $this->actingAs($this->c['owner'])
        ->patchJson(indexingPath($this->c['community']), $input)
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    expect($this->c['community']->fresh()->indexing_enabled)->toBeFalse();
})->with([
    'missing' => [[]],
    'a word' => [['indexing_enabled' => 'yes']],
    'a string that looks like a boolean' => [['indexing_enabled' => 'true']],
    'null' => [['indexing_enabled' => null]],
    'an array' => [['indexing_enabled' => [true]]],
]);

test('a personal community refuses the setting (#1487)', function () {
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $personal = $user->ownedCommunities()->where('visibility', KnowiiCommunityVisibility::Personal)->firstOrFail();

    $this->actingAs($user)
        ->patchJson(indexingPath($personal), ['indexing_enabled' => true])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    expect($personal->fresh()->indexing_enabled)->toBeFalse()
        // Whatever the stored value
        ->and($personal->forceFill(['indexing_enabled' => true])->allowsIndexing())->toBeFalse();
});

test('the setting is read by the members, never in the public shape, and changed with the abilities of the other settings (#1487, ADR 0022)', function () {
    $community = $this->c['community'];
    $url = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);

    Sanctum::actingAs($this->c['owner'], ['communities:read']);
    $this->getJson($url)->assertOk()->assertJsonPath('data.indexing_enabled', false);
    $this->patchJson(indexingPath($community), ['indexing_enabled' => true])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['communities:write']);
    $this->patchJson(indexingPath($community), ['indexing_enabled' => true])->assertOk();

    // A community with a public space: the outsider gets the public shape, without the setting
    indexingPages($community);
    $this->actingAs($this->c['outsider'])->getJson($url)->assertOk()->assertJsonMissingPath('data.indexing_enabled');
    $this->actingAs($this->c['outsider'])->get("/community/{$community->slug}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/Public')->missing('community.indexing_enabled'));
});

test('the settings page tells owners and admins whether they may change indexing (#1487)', function (string $role, bool $allowed) {
    $this->c['community']->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_spaces' => true]]);

    $this->actingAs($this->c[$role])
        ->get(route('communities.settings', ['communitySlug' => $this->c['community']->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Communities/Settings')
            ->where('permissions.canManageIndexing', $allowed)
            ->where('community.indexing_enabled', false));
})->with([['owner', true], ['admin', true], ['moderator', false]]);

// ---------------------------------------------------------------------------------------------------------------------
// The directive: header and meta tag
// ---------------------------------------------------------------------------------------------------------------------

test('a visitor gets index on exactly the pages of a public space, with the setting and the instance on (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);
    $pages = indexingPages($this->c['community']);

    foreach ($pages as $name => $url) {
        $expected = in_array($name, INDEXING_INDEXABLE, true) ? INDEXING_INDEX : INDEXING_NOINDEX;
        $response = indexingGet('visitor', $url);

        indexingAssertHeader($response, $expected, $name);
        // The header and the meta tag agree, on every page that has a meta tag
        if ($response->isSuccessful()) {
            expect(indexingMeta($response))->toBe($expected, "meta tag of {$name}");
        }
    }
});

test('the marker of a deleted post, a section, a chat space and a members space are never indexed (#1344, #1487)', function () {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);
    $pages = indexingPages($this->c['community']);

    foreach (['public marker post', 'public section', 'public chat space', 'members space', 'members post', 'groups space', 'public link space', 'unknown space'] as $name) {
        expect(indexingGet('visitor', $pages[$name])->headers->get('X-Robots-Tag'))->toBe(INDEXING_NOINDEX, $name);
    }
});

test('the setting off: every community page is noindex for a visitor (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $pages = indexingPages($this->c['community']);

    foreach ($pages as $name => $url) {
        $response = indexingGet('visitor', $url);
        indexingAssertHeader($response, INDEXING_NOINDEX, $name);
        if ($response->isSuccessful()) {
            expect(indexingMeta($response))->toBe(INDEXING_NOINDEX, $name);
        }
    }
});

test('the instance off: every page is noindex, whatever the community decides (#1487)', function () {
    config(['robots.allow_indexing' => false]);
    $this->c['community']->update(['indexing_enabled' => true]);
    $pages = indexingPages($this->c['community']);

    foreach ($pages as $name => $url) {
        $response = indexingGet('visitor', $url);
        indexingAssertHeader($response, INDEXING_NOINDEX, $name);
        if ($response->isSuccessful()) {
            expect(indexingMeta($response))->toBe(INDEXING_NOINDEX, $name);
        }
    }
});

test('a signed-in reader gets noindex on every page, member or outsider (#1487)', function (string $role) {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);
    $pages = indexingPages($this->c['community']);

    foreach ($pages as $name => $url) {
        $response = indexingGet($role, $url);
        indexingAssertHeader($response, INDEXING_NOINDEX, $name);
        if ($response->isSuccessful()) {
            expect(indexingMeta($response))->toBe(INDEXING_NOINDEX, $name);
        }
    }
})->with(['owner', 'member', 'outsider']);

test('the setting of another community does not count (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $other = communityWithMembers();
    $other['community']->update(['indexing_enabled' => true]);
    $pages = indexingPages($this->c['community']);

    expect(indexingGet('visitor', $pages['public discussion space'])->headers->get('X-Robots-Tag'))->toBe(INDEXING_NOINDEX);
});

test('turning the setting off takes effect at the next request (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);
    $url = indexingPages($this->c['community'])['public discussion space'];

    expect(indexingGet('visitor', $url)->headers->get('X-Robots-Tag'))->toBe(INDEXING_INDEX);

    $this->actingAs($this->c['owner'])->patchJson(indexingPath($this->c['community']), ['indexing_enabled' => false])->assertOk();

    expect(indexingGet('visitor', $url)->headers->get('X-Robots-Tag'))->toBe(INDEXING_NOINDEX);
});

test('the community home of a community without a public space is not indexed, and sends the visitor to sign in (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);

    $response = indexingGet('visitor', "/community/{$this->c['community']->slug}");

    $response->assertRedirect();
    expect($response->headers->get('X-Robots-Tag'))->toBe(INDEXING_NOINDEX);
});

test('the API is never indexed, also a public space of a community that allows indexing (#1487)', function () {
    config(['robots.allow_indexing' => true]);
    $this->c['community']->update(['indexing_enabled' => true]);
    indexingPages($this->c['community']);

    app('auth')->forgetGuards();
    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', INDEXING_NOINDEX);
});

test('the directive: a community page needs a yes, any other page keeps the rule of the instance (#1487)', function () {
    config(['robots.allow_indexing' => true]);

    expect(Robots::directive(false, false))->toBe(INDEXING_INDEX)
        ->and(Robots::directive(false, false, true))->toBe(INDEXING_INDEX)
        ->and(Robots::directive(false, false, false))->toBe(INDEXING_NOINDEX)
        ->and(Robots::directive(true, false, true))->toBe(INDEXING_NOINDEX)
        ->and(Robots::directive(false, true, true))->toBe(INDEXING_NOINDEX);

    config(['robots.allow_indexing' => false]);

    expect(Robots::directive(false, false, true))->toBe(INDEXING_NOINDEX);
});

// ---------------------------------------------------------------------------------------------------------------------
// robots.txt
// ---------------------------------------------------------------------------------------------------------------------

test('robots.txt no longer blocks the community pages as a whole, and still blocks what is never public (#1487)', function () {
    config(['robots.allow_indexing' => true]);

    $body = (string) $this->get('/robots.txt')->assertOk()->getContent();
    $lines = explode("\n", $body);

    expect($lines)->not->toContain('Disallow: /community/');
    foreach (['/api/', '/dashboard', '/user/', '/community/*/about', '/community/*/settings', '/community/*/member/', '/community/*/messages', '/login', '/register', '/auth/', '/invitations/', '/community-invitations/'] as $path) {
        expect($lines)->toContain("Disallow: {$path}");
    }
    expect($lines)->toContain('Allow: /');
});

test('robots.txt keeps refusing the AI crawlers everywhere (#1487)', function () {
    config(['robots.allow_indexing' => true]);

    $body = (string) $this->get('/robots.txt')->getContent();

    foreach ((array) config('robots.ai_crawlers') as $crawler) {
        expect($body)->toContain("User-agent: {$crawler}\n");
    }
    expect($body)->toContain("Disallow: /\n\nUser-agent: *");
});

test('robots.txt refuses everything when the instance does not index, whatever the communities decide (#1487)', function () {
    config(['robots.allow_indexing' => false]);
    $this->c['community']->update(['indexing_enabled' => true]);

    expect((string) $this->get('/robots.txt')->getContent())->toContain("User-agent: *\nDisallow: /\n")->not->toContain('Allow: /');
});
