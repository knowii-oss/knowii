<?php

use App\ApiRoutes;
use App\Enums\KnowiiCommunityJoinPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AccessGroup;
use App\Models\CommunityResource;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Testing\TestResponse;

/*
 * The page without access (#1561): a signed-in person who is not a member opens a page of a community that is for its
 * members. The answer stays a 403, with the page `Communities/NoAccess`. It names the community (its public shape) only
 * when this person may see the public page of the community; else the props hold nothing of it. It never names the
 * space, the post, the page, the resource or an author. A visitor, a member and a request that expects JSON keep the
 * answer of before, and a page that does not exist still answers 404.
 */

const NO_ACCESS_COMPONENT = 'Communities/NoAccess';
const NO_ACCESS_COMMUNITY_NAME = 'Orchard Of Quiet Readers';

/**
 * What a person who is not a member may never read on the page without access
 */
const NO_ACCESS_SECRETS = [
    'SECRET-SPACE-NAME', 'SECRET-POST-TITLE', 'SECRET-PAGES-SPACE', 'SECRET-PAGE-TITLE', 'SECRET-RESOURCES-SPACE',
    'SECRET-RESOURCE-NAME', 'SECRET-PUBLIC-CHAT', 'SECRET-AUTHOR-NAME',
];

/**
 * A community of one of three kinds: "open" and "invitation" (both with a public discussion space, so a public page), and
 * "none" (no public page: only a public chat space). Each has a members-only discussion space with a post, a members-only
 * pages space with a page, and a members-only resources space with a resource.
 */
function noAccessCommunity(string $kind): void
{
    $test = test();
    $test->c = communityWithMembers();
    $community = $test->c['community'];
    $community->forceFill([
        'name' => NO_ACCESS_COMMUNITY_NAME,
        'join_policy' => $kind === 'open' ? KnowiiCommunityJoinPolicy::Open : KnowiiCommunityJoinPolicy::Invitation,
    ])->save();
    $author = $test->c['member'];
    $author->forceFill(['name' => 'SECRET-AUTHOR-NAME'])->save();

    $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Main']);
    $make = fn (KnowiiSpaceType $type, KnowiiSpaceVisibility $visibility, string $name) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => $visibility, 'name' => $name]);

    $test->space = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Members, 'SECRET-SPACE-NAME');
    $test->post = Post::factory()->inSpace($test->space)->create(['author_id' => $author->id, 'title' => 'SECRET-POST-TITLE']);
    $test->pagesSpace = $make(KnowiiSpaceType::Pages, KnowiiSpaceVisibility::Members, 'SECRET-PAGES-SPACE');
    $test->page = Page::factory()->inSpace($test->pagesSpace)->create(['author_id' => $author->id, 'title' => 'SECRET-PAGE-TITLE', 'position' => 0]);
    $test->resourcesSpace = $make(KnowiiSpaceType::Resources, KnowiiSpaceVisibility::Members, 'SECRET-RESOURCES-SPACE');
    $test->resource = CommunityResource::factory()->create(['space_id' => $test->resourcesSpace->id, 'name' => 'SECRET-RESOURCE-NAME', 'curator_id' => $author->profile->id]);

    if ($kind === 'none') {
        $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Public, 'SECRET-PUBLIC-CHAT');
    } else {
        $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Public, 'Open talk');
    }
}

/**
 * The pages of a community that answer 403 to a signed-in person who is not a member, with the kind of the page
 *
 * @return array<string, array{url: string, kind: string}>
 */
function noAccessPages(): array
{
    $test = test();
    $community = $test->c['community']->slug;

    return [
        'space' => ['url' => route('spaces.show', ['communitySlug' => $community, 'spaceSlug' => $test->space->slug]), 'kind' => 'space'],
        'post' => ['url' => route('posts.show', ['communitySlug' => $community, 'spaceSlug' => $test->space->slug, 'postSlug' => $test->post->slug]), 'kind' => 'space'],
        'page' => ['url' => route('pages.show', ['communitySlug' => $community, 'spaceSlug' => $test->pagesSpace->slug, 'pageSlug' => $test->page->slug]), 'kind' => 'space'],
        'resource' => ['url' => route('resources.show', ['communitySlug' => $community, 'spaceSlug' => $test->resourcesSpace->slug, 'resourceSlug' => $test->resource->slug]), 'kind' => 'space'],
        'about' => ['url' => route('communities.about', ['communitySlug' => $community]), 'kind' => 'community'],
        'member' => ['url' => route('members.show', ['communitySlug' => $community, 'username' => $test->c['member']->username]), 'kind' => 'community'],
    ];
}

/**
 * The page of a community: the same pages, and the community home (which has a public page in an "open" and an
 * "invitation" community, so it answers 403 only in a community without one)
 *
 * @return array<string, array{url: string, kind: string}>
 */
function noAccessPagesWithHome(): array
{
    return [...noAccessPages(), 'home' => ['url' => route('communities.show', ['communitySlug' => test()->c['community']->slug]), 'kind' => 'community']];
}

/**
 * No name of a space, a post, a page, a resource or an author in the response
 */
function noAccessAssertNoSecret(TestResponse $response): void
{
    $content = (string) $response->getContent();
    foreach (NO_ACCESS_SECRETS as $secret) {
        expect($content)->not->toContain($secret);
    }
}

/**
 * The props of the page, without the shared props of every page
 *
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function noAccessPageProps(array $props): array
{
    return array_diff_key($props, array_flip(['auth', 'userProfile', 'jetstream', 'errors', 'errorBags', 'ziggy', 'newsletter', 'oidc', 'catchUp', 'communityCount']));
}

dataset('no access communities with a public page', ['open', 'invitation']);

test('an outsider of a community with a public page gets 403, the page without access, the public shape of the community and its join policy', function (string $kind) {
    noAccessCommunity($kind);
    $community = $this->c['community'];

    foreach (noAccessPages() as $name => $page) {
        $response = $this->actingAs($this->c['outsider'])->get($page['url']);

        $response->assertForbidden()->assertInertia(fn ($inertia) => $inertia->component(NO_ACCESS_COMPONENT));
        $props = $response->viewData('page')['props'];
        expect(noAccessPageProps($props))->toBe([
            'kind' => $page['kind'],
            'community' => [
                'cuid' => $community->cuid,
                'name' => NO_ACCESS_COMMUNITY_NAME,
                'slug' => $community->slug,
                'description' => $community->description ?? '',
                'visibility' => $community->visibility->value,
                'join_policy' => $kind,
                'is_member' => false,
            ],
        ], "the props of the page {$name}");
        noAccessAssertNoSecret($response);
    }
})->with('no access communities with a public page');

test('an outsider of a community without a public page gets 403, the page without access, and nothing of the community', function () {
    noAccessCommunity('none');
    $community = $this->c['community'];

    foreach (noAccessPagesWithHome() as $name => $page) {
        $response = $this->actingAs($this->c['outsider'])->get($page['url']);

        $response->assertForbidden()->assertInertia(fn ($inertia) => $inertia->component(NO_ACCESS_COMPONENT));
        $props = $response->viewData('page')['props'];
        // The props of the page: the kind, and no community at all (no name, no slug, no cuid, no picture)
        expect(noAccessPageProps($props))->toBe(['kind' => $page['kind'], 'community' => null], "the props of the page {$name}");

        // The whole props, the shared ones too: the address of the request (ziggy.location) holds the slug, as the address
        // bar of the browser does
        $scanned = $props;
        unset($scanned['ziggy']['location']);
        $json = (string) json_encode($scanned);
        foreach ([$community->cuid, $community->slug, NO_ACCESS_COMMUNITY_NAME] as $secret) {
            expect($json)->not->toContain($secret, "the page {$name} holds {$secret}");
        }
        expect((string) $response->getContent())->not->toContain(NO_ACCESS_COMMUNITY_NAME);
        noAccessAssertNoSecret($response);
    }
});

test('a member still reads each page', function (string $kind) {
    noAccessCommunity($kind);

    foreach (noAccessPages() as $page) {
        $this->actingAs($this->c['member'])->get($page['url'])->assertOk();
    }
    $this->actingAs($this->c['member'])->get(route('communities.show', ['communitySlug' => $this->c['community']->slug]))
        ->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Communities/Show'));
})->with(['open', 'invitation', 'none']);

test('a visitor keeps the answer of before: the sign-in page', function (string $kind) {
    noAccessCommunity($kind);

    foreach (noAccessPagesWithHome() as $name => $page) {
        if ($name === 'home' && $kind !== 'none') {
            continue;
        }
        app('auth')->forgetGuards();
        $response = $this->get($page['url']);

        $response->assertRedirect(route('login'));
        noAccessAssertNoSecret($response);
    }
})->with(['open', 'invitation', 'none']);

test('an outsider of a community with a public page still gets the public page of the community home', function () {
    noAccessCommunity('open');

    $this->actingAs($this->c['outsider'])->get(route('communities.show', ['communitySlug' => $this->c['community']->slug]))
        ->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Communities/Public'));
});

test('a page that does not exist still answers 404, never the page without access', function (string $kind) {
    noAccessCommunity($kind);
    $community = $this->c['community']->slug;

    foreach ([
        route('spaces.show', ['communitySlug' => $community, 'spaceSlug' => 'no-such-space']),
        route('posts.show', ['communitySlug' => $community, 'spaceSlug' => 'no-such-space', 'postSlug' => 'no-such-post']),
        route('posts.show', ['communitySlug' => $community, 'spaceSlug' => $this->space->slug, 'postSlug' => 'no-such-post']),
        route('pages.show', ['communitySlug' => $community, 'spaceSlug' => 'no-such-space', 'pageSlug' => 'no-such-page']),
        route('resources.show', ['communitySlug' => $community, 'spaceSlug' => 'no-such-space', 'resourceSlug' => 'no-such-resource']),
        route('spaces.show', ['communitySlug' => 'no-such-community', 'spaceSlug' => $this->space->slug]),
        route('communities.about', ['communitySlug' => 'no-such-community']),
        route('communities.show', ['communitySlug' => 'no-such-community']),
    ] as $url) {
        $response = $this->actingAs($this->c['outsider'])->get($url);

        $response->assertNotFound();
        expect((string) $response->getContent())->not->toContain(NO_ACCESS_COMPONENT);
    }
})->with(['open', 'none']);

test('a member who may not read a space of access groups keeps the answer of before', function () {
    noAccessCommunity('open');
    $groups = Space::factory()->inGroup($this->space->spaceGroup)->create(['visibility' => KnowiiSpaceVisibility::Groups, 'name' => 'SECRET-GROUPS-SPACE']);
    $groups->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $this->c['community']->id, 'name' => 'Readers']));

    $response = $this->actingAs($this->c['member'])->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $groups->slug]));

    $response->assertForbidden();
    expect((string) $response->getContent())->not->toContain(NO_ACCESS_COMPONENT)->not->toContain('SECRET-GROUPS-SPACE');
});

test('a request that expects JSON gets the answer of before', function () {
    noAccessCommunity('open');

    $response = $this->actingAs($this->c['outsider'])->getJson(noAccessPages()['space']['url']);

    $response->assertForbidden()->assertJsonPath('type', 'authorizationIssue')->assertJsonPath('message', 'This action is unauthorized.');
    expect((string) $response->getContent())->not->toContain(NO_ACCESS_COMPONENT)->not->toContain(NO_ACCESS_COMMUNITY_NAME);
});

test('an Inertia visit gets the page without access as an Inertia page, with the status 403', function () {
    noAccessCommunity('open');
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];

    $response = $this->actingAs($this->c['outsider'])->get(noAccessPages()['space']['url'], $headers);

    $response->assertForbidden()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', NO_ACCESS_COMPONENT)
        ->assertJsonPath('props.community.join_policy', 'open');
});

test('the page without access shows outside the local and testing environments, and the other errors keep the error page', function () {
    noAccessCommunity('none');
    $groups = Space::factory()->inGroup($this->space->spaceGroup)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs($this->c['outsider'])->get(noAccessPages()['space']['url'])
        ->assertForbidden()->assertInertia(fn ($inertia) => $inertia->component(NO_ACCESS_COMPONENT)->where('community', null));
    $this->actingAs($this->c['member'])->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $groups->slug]))
        ->assertForbidden()->assertInertia(fn ($inertia) => $inertia->component('Error')->where('status', 403));
    $this->actingAs($this->c['outsider'])->get(route('spaces.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => 'no-such-space']))
        ->assertNotFound()->assertInertia(fn ($inertia) => $inertia->component('Error')->where('status', 404));
});

test('after a join, the same address answers the content', function () {
    noAccessCommunity('open');
    $url = noAccessPages()['space']['url'];

    $this->actingAs($this->c['outsider'])->get($url)->assertForbidden();
    $this->actingAs($this->c['outsider'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERSHIP, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]))
        ->assertCreated();

    $this->actingAs($this->c['outsider'])->get($url)->assertOk()->assertInertia(fn ($inertia) => $inertia->component('Spaces/Show'));
});
