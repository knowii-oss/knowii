<?php

use App\Actions\Posts\CreatePost;
use App\ApiRoutes;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Jobs\CaptureCommunityResource;
use App\Models\AccessGroup;
use App\Models\CommunityResource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\SafeUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * Simple links (#1294, ADR 0026): link spaces in the navigation, and resources kept without capture.
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    Http::preventStrayRequests();
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id, 'name' => 'Start']);
});

function linkSpacesPath(): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, [ApiRoutes::PARAM_COMMUNITY => test()->community->cuid]);
}

function linkSpacePath(Space $space): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [ApiRoutes::PARAM_COMMUNITY => test()->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
}

function linkSpace(array $attributes = []): Space
{
    return Space::factory()->inGroup(test()->group)->create([
        'name' => 'Book a call',
        'type' => KnowiiSpaceType::Link,
        'url' => 'https://example.com/book',
        ...$attributes,
    ]);
}

test('admins add a link to the navigation: no posting policy needed, the URL comes back', function () {
    $this->actingAs($this->c['admin'])
        ->postJson(linkSpacesPath(), [
            'space_group_cuid' => $this->group->cuid,
            'name' => 'Our shop',
            'type' => 'link',
            'visibility' => 'members',
            'url' => ' https://shop.example.com/knowii ',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'link')
        ->assertJsonPath('data.url', 'https://shop.example.com/knowii')
        ->assertJsonPath('data.posting_policy', 'members');

    Event::assertDispatched(SpaceTreeChanged::class);
});

test('a link space needs an http or https URL, and only a link space has one', function (array $input, string $field) {
    $this->actingAs($this->c['owner'])
        ->postJson(linkSpacesPath(), [
            'space_group_cuid' => $this->group->cuid,
            'name' => 'Somewhere',
            'visibility' => 'members',
            ...$input,
        ])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => [$field]]);
})->with([
    'no URL' => [['type' => 'link'], 'url'],
    'a script' => [['type' => 'link', 'url' => 'javascript:alert(1)'], 'url'],
    'an FTP address' => [['type' => 'link', 'url' => 'ftp://example.com/file'], 'url'],
    'a URL on a discussion space' => [['type' => 'discussion', 'posting_policy' => 'members', 'url' => 'https://example.com'], 'url'],
    'no posting policy on a discussion space' => [['type' => 'discussion'], 'posting_policy'],
]);

test('the URL of a link space changes; another space refuses one', function () {
    $link = linkSpace();
    $discussion = Space::factory()->inGroup($this->group)->create();

    $this->actingAs($this->c['admin']);

    $this->patchJson(linkSpacePath($link), ['url' => 'https://example.com/new'])
        ->assertOk()
        ->assertJsonPath('data.url', 'https://example.com/new');

    $this->patchJson(linkSpacePath($discussion), ['url' => 'https://example.com'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['url']]);

    expect($discussion->refresh()->url)->toBeNull();
});

test('the database refuses a link space without a URL and another space with one', function (KnowiiSpaceType $type, ?string $url) {
    expect(fn () => Space::factory()->inGroup($this->group)->create(['type' => $type, 'url' => $url]))
        ->toThrow(QueryException::class);
})->with([
    'link without URL' => [KnowiiSpaceType::Link, null],
    'discussion with URL' => [KnowiiSpaceType::Discussion, 'https://example.com'],
]);

test('link spaces follow the visibility of spaces, access groups included (ADR 0025)', function () {
    linkSpace(['name' => 'For everyone', 'visibility' => KnowiiSpaceVisibility::Public]);
    linkSpace(['name' => 'For members', 'visibility' => KnowiiSpaceVisibility::Members]);
    $paid = linkSpace(['name' => 'For buyers', 'visibility' => KnowiiSpaceVisibility::Groups]);
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Buyers']);
    $paid->accessGroups()->attach($group);
    $treePath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]);

    $names = fn ($user) => collect($this->actingAs($user)->getJson($treePath)->assertOk()->json('data.0.spaces'))->pluck('name')->all();

    expect($names($this->c['member']))->toBe(['For everyone', 'For members'])
        ->and($names($this->c['owner']))->toBe(['For everyone', 'For members', 'For buyers']);

    $group->members()->attach($this->c['member']);
    // The user model keeps its loaded access groups
    $this->c['member']->unsetRelation('accessGroups');
    expect($names($this->c['member']))->toContain('For buyers');

    // The URL of a restricted link stays hidden: the space itself is refused
    $this->actingAs($this->c['outsider'])->getJson(linkSpacePath($paid))->assertForbidden();
});

test('nobody posts in a link space', function () {
    $link = linkSpace();

    expect($this->c['owner']->can('contribute', $link))->toBeFalse()
        ->and(fn () => (new CreatePost)->create($this->c['owner'], $link, ['title' => 'Hello', 'body' => 'World']))
        ->toThrow(AuthorizationException::class);
});

test('the page of a link space shows where it goes, without a redirect', function () {
    $link = linkSpace();

    $this->actingAs($this->c['member'])
        ->get(route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $link->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Spaces/Show')
            ->where('space.type', 'link')
            ->where('space.url', 'https://example.com/book')
            ->where('permissions.canContribute', false));
});

function resourcesSpace(): Space
{
    return Space::factory()->inGroup(test()->group)->create(['type' => KnowiiSpaceType::Resources]);
}

function resourcesOf(Space $space): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
}

test('a resource kept without capture makes no request at all: no capture, no DNS lookup', function () {
    Queue::fake();
    $lookups = 0;
    SafeUrl::resolveUsing(function () use (&$lookups) {
        $lookups++;

        return ['10.0.0.5'];
    });
    $space = resourcesSpace();

    $this->actingAs($this->c['member'])
        ->postJson(resourcesOf($space), ['url' => 'https://intranet.example.com/team-handbook', 'level' => 'unknown', 'capture' => false])
        ->assertCreated()
        ->assertJsonPath('data.capture_status', 'skipped')
        ->assertJsonPath('data.name', 'Team Handbook');

    expect($lookups)->toBe(0);
    Queue::assertNotPushed(CaptureCommunityResource::class);
});

test('capturing a link kept without capture later checks the address like any capture', function () {
    Queue::fake();
    $space = resourcesSpace();
    $id = $this->actingAs($this->c['member'])
        ->postJson(resourcesOf($space), ['url' => 'https://example.com/smart-notes', 'level' => 'unknown', 'capture' => false])
        ->json('data.cuid');
    $resource = CommunityResource::where('cuid', $id)->firstOrFail();
    $capturePath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE_CAPTURE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);

    // A private address is refused, and the link stays as it was
    SafeUrl::resolveUsing(fn () => ['192.168.1.10']);
    $this->postJson($capturePath)->assertStatus(422);
    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Skipped);

    // A public one starts the capture; the name came from the URL, so the title of the page replaces it
    SafeUrl::resolveUsing(fn () => ['93.184.215.14']);
    $this->postJson($capturePath)->assertOk()->assertJsonPath('data.capture_status', 'pending');
    Queue::assertPushed(CaptureCommunityResource::class, fn (CaptureCommunityResource $job) => $job->nameFromPage);
});

test('a name from the curator stays when a link kept without capture is captured later', function () {
    Queue::fake();
    $space = resourcesSpace();
    $id = $this->actingAs($this->c['member'])
        ->postJson(resourcesOf($space), ['url' => 'https://example.com/smart-notes', 'name' => 'My favorite guide', 'level' => 'unknown', 'capture' => false])
        ->json('data.cuid');

    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE_CAPTURE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $id,
    ]))->assertOk();

    Queue::assertPushed(CaptureCommunityResource::class, fn (CaptureCommunityResource $job) => ! $job->nameFromPage);
});
