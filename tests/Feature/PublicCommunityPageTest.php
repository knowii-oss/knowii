<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Community;
use App\Models\Event as EventModel;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\PublicCommunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * The public page of a community (#1485, ADR 0044): the web page and the three API reads, for a visitor (not signed in),
 * an outsider (signed in, not a member) and a member, against a community with a public discussion space, with only a
 * public chat space, with no public space, and a community that does not exist. Anything of a member that reaches a visitor
 * or an outsider is a defect: the full props and the full JSON are scanned.
 */

const PUBLIC_PAGE_FORBIDDEN_KEYS = [
    'email', 'phone', 'bio', 'headline', 'access_groups', 'moderator_permissions', 'member_count', 'users_count', 'team', 'members',
    'direct_messages_enabled', 'unread_direct_messages', 'userProfile', 'field_visibility', 'online_status', 'show_online_status',
    'unread_count',
];

/**
 * Build a community of one of the kinds of the matrix: "discussion" (a public discussion space, and everything that must
 * stay hidden around it), "chat" (a public chat space only) and "none" (no public space).
 */
function publicPageCommunity(string $kind): void
{
    $test = test();
    $test->c = communityWithMembers();
    $test->author = $test->c['member'];
    $test->author->profile->forceFill(['bio' => 'SECRET-BIO-OF-THE-AUTHOR', 'phone' => 'SECRET-PHONE-OF-THE-AUTHOR', 'headline' => 'SECRET-HEADLINE-OF-THE-AUTHOR'])->save();
    $test->kind = $kind;
    $community = $test->c['community'];

    $group = SpaceGroup::factory()->create(['community_id' => $community->id, 'name' => 'Main']);
    $make = fn (KnowiiSpaceType $type, KnowiiSpaceVisibility $visibility, string $name) => Space::factory()->inGroup($group)->create(['type' => $type, 'visibility' => $visibility, 'name' => $name]);

    // What stays hidden in every kind of community
    $test->hiddenMembers = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Members, 'SECRET-MEMBERS-SPACE');
    $test->hiddenGroups = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Groups, 'SECRET-GROUPS-SPACE');
    $test->hiddenGroups->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $community->id, 'name' => 'SECRET-ACCESS-GROUP']));
    $test->hiddenChat = $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Members, 'SECRET-MEMBERS-CHAT');
    foreach ([$test->hiddenMembers, $test->hiddenGroups] as $space) {
        Post::factory()->inSpace($space)->create(['author_id' => $test->author->id, 'title' => 'SECRET-POST-OF-'.$space->name]);
    }
    ChatMessage::factory()->inSpace($test->hiddenChat)->create(['author_id' => $test->author->id, 'body' => 'SECRET-CHAT-LINE']);

    if ($kind === 'chat') {
        $test->publicChat = $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Public, 'SECRET-PUBLIC-CHAT');
        ChatMessage::factory()->inSpace($test->publicChat)->create(['author_id' => $test->author->id, 'body' => 'SECRET-CHAT-LINE']);
    }

    if ($kind === 'discussion') {
        $test->publicChat = $make(KnowiiSpaceType::Chat, KnowiiSpaceVisibility::Public, 'SECRET-PUBLIC-CHAT');
        ChatMessage::factory()->inSpace($test->publicChat)->create(['author_id' => $test->author->id, 'body' => 'SECRET-CHAT-LINE']);
        $test->publicSpace = $make(KnowiiSpaceType::Discussion, KnowiiSpaceVisibility::Public, 'Open talk');
        $test->publicPost = Post::factory()->inSpace($test->publicSpace)->create(['author_id' => $test->author->id, 'title' => 'An open post', 'body' => "# Hello\n\nA **bold** [link](https://example.com) text"]);
    }
}

function publicPageUrl(): string
{
    return route('communities.show', ['communitySlug' => test()->c['community']->slug]);
}

function publicPageAs(string $reader, string $url): TestResponse
{
    $test = test();

    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return $test->get($url);
    }

    return $test->actingAs($test->c[$reader])->get($url);
}

function publicPageApiAs(string $reader, string $url): TestResponse
{
    $test = test();

    if ($reader === 'visitor') {
        app('auth')->forgetGuards();

        return $test->getJson($url);
    }

    return $test->actingAs($test->c[$reader])->getJson($url);
}

/**
 * @return array<string, string> The three reads of the public page, for the community of the test
 */
function publicPageApiUrls(): array
{
    $parameters = [ApiRoutes::PARAM_COMMUNITY => test()->c['community']->cuid];

    return [
        'community' => ApiRoutes::path(ApiRoutes::COMMUNITY, $parameters),
        'space groups' => ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, $parameters),
        'newest posts' => ApiRoutes::path(ApiRoutes::COMMUNITY_NEWEST_POSTS, $parameters),
    ];
}

/**
 * @param  array<mixed>  $data
 * @return list<string>
 */
function publicPageKeysOf(array $data): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        $keys[] = (string) $key;
        if (is_array($value)) {
            $keys = [...$keys, ...publicPageKeysOf($value)];
        }
    }

    return $keys;
}

/**
 * Nothing of the members and of what is not public in what reaches the reader. The signed-in reader has their own account in
 * the shared props (`auth`, `userProfile`): their own data is theirs.
 *
 * @param  array<mixed>  $data
 */
function publicPageAssertNoLeak(array $data, string $reader): void
{
    $test = test();
    $scanned = $reader === 'visitor' ? $data : array_diff_key($data, array_flip(['auth', 'userProfile']));
    $json = (string) json_encode($scanned);

    expect(array_intersect(publicPageKeysOf($scanned), PUBLIC_PAGE_FORBIDDEN_KEYS))->toBe([], 'a field of the "never" list reaches the reader');
    foreach ([
        $test->author->email, $test->author->profile->email, 'SECRET-BIO-OF-THE-AUTHOR', 'SECRET-PHONE-OF-THE-AUTHOR', 'SECRET-HEADLINE-OF-THE-AUTHOR',
        'SECRET-CHAT-LINE',
        'SECRET-MEMBERS-SPACE', 'SECRET-GROUPS-SPACE', 'SECRET-MEMBERS-CHAT', 'SECRET-PUBLIC-CHAT', 'SECRET-ACCESS-GROUP', 'SECRET-POST-OF-',
        $test->c['owner']->email, $test->c['admin']->email, $test->c['moderator']->email, $test->c['owner']->username, $test->c['admin']->username,
        $test->c['moderator']->username, $test->c['owner']->name, $test->c['admin']->name, $test->c['moderator']->name,
    ] as $secret) {
        expect($json)->not->toContain($secret);
    }
    if ($reader === 'visitor') {
        expect($json)->not->toContain($test->c['outsider']->email);
    }
}

dataset('readers', ['visitor', 'outsider', 'member']);
dataset('non-members', ['visitor', 'outsider']);
dataset('communities without a public page', ['chat', 'none']);

// ---------------------------------------------------------------------------------------------------------------------
// The web page: the matrix
// ---------------------------------------------------------------------------------------------------------------------

test('page: a community with a public discussion space shows its public page to a visitor and an outsider, and its home to a member', function () {
    publicPageCommunity('discussion');

    publicPageAs('visitor', publicPageUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Public'));
    publicPageAs('outsider', publicPageUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Public'));
    publicPageAs('member', publicPageUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
    publicPageAs('owner', publicPageUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
});

test('page: a community with no public space for a visitor (only a public chat, or none) goes to the sign-in page, refuses an outsider, and opens for a member', function (string $kind) {
    publicPageCommunity($kind);

    publicPageAs('visitor', publicPageUrl())->assertRedirect(route('login'));
    publicPageAs('outsider', publicPageUrl())->assertForbidden();
    publicPageAs('member', publicPageUrl())->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
})->with('communities without a public page');

test('page: a community that does not exist answers a visitor like one without a public space, and an outsider as before', function () {
    publicPageCommunity('discussion');
    $url = route('communities.show', ['communitySlug' => 'no-such-community']);

    publicPageAs('visitor', $url)->assertRedirect(route('login'));
    publicPageAs('outsider', $url)->assertNotFound();
});

test('page: an unverified account is still refused', function () {
    publicPageCommunity('discussion');
    $unverified = User::factory()->unverified()->withUserProfile()->create();

    $this->actingAs($unverified)->get(publicPageUrl())->assertRedirect(route('verification.notice'));
});

test('page: the sign-in of a visitor comes back to the public page', function () {
    publicPageCommunity('discussion');

    $this->get(publicPageUrl())->assertOk();
    $this->post('/login', ['email' => $this->c['outsider']->email, 'password' => 'password'])->assertRedirect(publicPageUrl());
});

test('page: the about page, the settings, the messages and the member pages stay for members', function () {
    publicPageCommunity('discussion');
    $slug = $this->c['community']->slug;

    foreach ([
        route('communities.about', ['communitySlug' => $slug]),
        route('communities.settings', ['communitySlug' => $slug]),
        route('messages.index', ['communitySlug' => $slug]),
        route('members.show', ['communitySlug' => $slug, 'username' => $this->author->username]),
    ] as $url) {
        publicPageAs('visitor', $url)->assertRedirect(route('login'));
        publicPageAs('outsider', $url)->assertForbidden();
    }
});

test('page: the public page stays noindex', function () {
    config(['robots.allow_indexing' => true]);
    publicPageCommunity('discussion');

    publicPageAs('visitor', publicPageUrl())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

// ---------------------------------------------------------------------------------------------------------------------
// The web page: what the reader gets
// ---------------------------------------------------------------------------------------------------------------------

test('page: the props of a visitor and of an outsider hold no member data, and nothing that is not public', function (string $reader) {
    publicPageCommunity('discussion');

    $props = publicPageAs($reader, publicPageUrl())->assertOk()->viewData('page')['props'];

    publicPageAssertNoLeak($props, $reader);
    expect($props['community'])->toBe([
        'cuid' => $this->c['community']->cuid,
        'name' => $this->c['community']->name,
        'slug' => $this->c['community']->slug,
        'description' => $this->c['community']->description ?? '',
        'visibility' => $this->c['community']->visibility->value,
        'join_policy' => 'invitation',
        'is_member' => false,
    ]);
    // Only the props of the page: no permission, no team, no resources, no member list
    foreach (['permissions', 'recentResources', 'team', 'currentRole'] as $prop) {
        expect($props)->not->toHaveKey($prop);
    }
    if ($reader === 'visitor') {
        expect($props['auth']['user'])->toBeNull()->and($props)->not->toHaveKey('userProfile');
    }
})->with('non-members');

test('page: the navigation holds the public spaces that are not a chat space, nothing else', function (string $reader) {
    publicPageCommunity('discussion');

    $tree = publicPageAs($reader, publicPageUrl())->viewData('page')['props']['spaceGroups'];
    $spaces = collect($tree)->flatMap(fn (array $group) => $group['spaces'])->pluck('cuid')->all();

    expect($spaces)->toBe([$this->publicSpace->cuid]);
})->with('non-members');

test('page: a member keeps the full community, their navigation and their rights', function () {
    publicPageCommunity('discussion');

    $props = publicPageAs('member', publicPageUrl())->viewData('page')['props'];
    $spaces = collect($props['spaceGroups'])->flatMap(fn (array $group) => $group['spaces'])->pluck('cuid')->all();

    expect($props['community'])->toHaveKeys(['moderator_permissions', 'direct_messages_enabled', 'member_count'])
        ->and($props['community']['is_member'])->toBeTrue()
        ->and($props)->toHaveKey('permissions')->not->toHaveKey('newestPosts')
        ->and($spaces)->toContain($this->hiddenMembers->cuid)->toContain($this->publicChat->cuid)->not->toContain($this->hiddenGroups->cuid);
});

test('page: the newest posts show the title, an excerpt, the author as name, username and avatar, the space and the date', function (string $reader) {
    publicPageCommunity('discussion');

    $posts = publicPageAs($reader, publicPageUrl())->viewData('page')['props']['newestPosts'];

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBe([
            'cuid' => $this->publicPost->cuid,
            'title' => 'An open post',
            'slug' => $this->publicPost->slug,
            'excerpt' => 'Hello A bold link text',
            'author' => ['cuid' => $this->author->cuid, 'name' => $this->author->name, 'username' => $this->author->username, 'avatar_url' => $this->author->avatar_url],
            'space' => ['cuid' => $this->publicSpace->cuid, 'slug' => $this->publicSpace->slug, 'name' => 'Open talk', 'emoji' => $this->publicSpace->emoji],
            'created_at' => $this->publicPost->created_at?->toJSON(),
        ]);
})->with('non-members');

test('page: the next events are the upcoming events of the public events spaces, without their meeting address and their place (#1514)', function (string $reader) {
    publicPageCommunity('discussion');
    $group = SpaceGroup::query()->where('community_id', $this->c['community']->id)->firstOrFail();
    $public = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'Open events']);
    $members = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members, 'name' => 'SECRET-MEMBERS-SPACE of events']);
    $event = EventModel::factory()->inSpace($public)->hostedBy($this->author)->create(['title' => 'An open event', 'meeting_url' => 'https://meet.example.com/SECRET-MEETING-ADDRESS', 'place' => 'SECRET-PLACE-OF-THE-EVENT']);
    EventModel::factory()->inSpace($public)->hostedBy($this->author)->draft()->create(['title' => 'SECRET-DRAFT-EVENT']);
    EventModel::factory()->inSpace($public)->hostedBy($this->author)->past()->create(['title' => 'An event of last week']);
    EventModel::factory()->inSpace($members)->hostedBy($this->author)->create(['title' => 'SECRET-MEMBERS-EVENT']);

    $props = publicPageAs($reader, publicPageUrl())->assertOk()->viewData('page')['props'];
    $json = (string) json_encode($props);

    publicPageAssertNoLeak($props, $reader);
    expect($props['nextEvents'])->toHaveCount(1)
        ->and($props['nextEvents'][0]['cuid'])->toBe($event->cuid)
        ->and($props['nextEvents'][0]['title'])->toBe('An open event')
        ->and($props['nextEvents'][0]['space'])->toBe(['cuid' => $public->cuid, 'slug' => $public->slug, 'name' => 'Open events'])
        ->and($props['nextEvents'][0]['host'])->toBe(['cuid' => $this->author->cuid, 'name' => $this->author->name, 'username' => $this->author->username, 'avatar_url' => $this->author->avatar_url])
        ->and($props['nextEvents'][0]['can_view_location'])->toBeFalse()
        ->and($props['nextEvents'][0]['meeting_url'])->toBeNull()
        ->and($props['nextEvents'][0]['place'])->toBeNull()
        ->and($props['nextEvents'][0]['body'])->toBeNull();
    foreach (['SECRET-MEETING-ADDRESS', 'SECRET-PLACE-OF-THE-EVENT', 'SECRET-DRAFT-EVENT', 'SECRET-MEMBERS-EVENT', 'An event of last week'] as $secret) {
        expect(str_contains($json, $secret))->toBeFalse("the public page of a {$reader} holds \"{$secret}\"");
    }
})->with('non-members');

test('page: a community without an event gets an empty list of next events', function () {
    publicPageCommunity('discussion');

    expect(publicPageAs('visitor', publicPageUrl())->viewData('page')['props']['nextEvents'])->toBe([]);
});

// ---------------------------------------------------------------------------------------------------------------------
// The newest posts: which posts
// ---------------------------------------------------------------------------------------------------------------------

test('newest posts: only the posts of the public discussion spaces of the community, at most 10, newest first, without the marker of a deleted post', function () {
    publicPageCommunity('discussion');
    $community = $this->c['community'];

    // Another public discussion space, a public link space, and a public space of another community
    $other = Space::factory()->inGroup($community->spaceGroups()->firstOrFail())->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public, 'name' => 'More talk']);
    $elsewhere = communityWithMembers();
    $elsewhereSpace = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $elsewhere['community']->id]))->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public]);
    Post::factory()->inSpace($elsewhereSpace)->create(['author_id' => $elsewhere['member']->id, 'title' => 'POST-OF-ANOTHER-COMMUNITY']);

    // A marker of a deleted post (#1344): it keeps its row and its old title, and is never listed
    Post::factory()->inSpace($this->publicSpace)->create(['author_id' => $this->author->id, 'title' => 'DELETED-POST', 'deleted_at' => now()]);
    // The author deleted their account: the post stays, with no author
    Post::factory()->inSpace($other)->create(['author_id' => null, 'title' => 'Post of a closed account', 'created_at' => now()->addMinute()]);
    // More than the limit
    foreach (range(1, Constants::PUBLIC_NEWEST_POSTS + 3) as $number) {
        Post::factory()->inSpace($other)->create(['author_id' => $this->author->id, 'title' => "Open post {$number}", 'created_at' => now()->addMinutes(10 + $number)]);
    }

    $posts = publicPageAs('visitor', publicPageUrl())->viewData('page')['props']['newestPosts'];
    $titles = collect($posts)->pluck('title')->all();

    expect($posts)->toHaveCount(Constants::PUBLIC_NEWEST_POSTS)
        ->and($titles[0])->toBe('Open post '.(Constants::PUBLIC_NEWEST_POSTS + 3))
        ->and($titles)->not->toContain('DELETED-POST')->not->toContain('POST-OF-ANOTHER-COMMUNITY');
    expect((string) json_encode($posts))->not->toContain('SECRET-POST-OF-')->not->toContain('DELETED-POST');
});

test('newest posts: a post of a closed account has no author, and the old posts are still there when there are few', function () {
    publicPageCommunity('discussion');
    Post::factory()->inSpace($this->publicSpace)->create(['author_id' => null, 'title' => 'Post of a closed account']);

    $posts = publicPageAs('visitor', publicPageUrl())->viewData('page')['props']['newestPosts'];

    expect(collect($posts)->firstWhere('title', 'Post of a closed account')['author'])->toBeNull()
        ->and($posts)->toHaveCount(2);
});

test('newest posts: a space that stops being public leaves the list at once', function () {
    publicPageCommunity('discussion');
    $this->publicSpace->forceFill(['visibility' => KnowiiSpaceVisibility::Members])->save();

    publicPageAs('visitor', publicPageUrl())->assertRedirect(route('login'));
    publicPageAs('member', publicPageUrl())->assertOk();
    expect(publicPageAs('member', publicPageApiUrls()['newest posts'])->assertOk()->json('data'))->toBe([]);
});

// ---------------------------------------------------------------------------------------------------------------------
// API: the matrix
// ---------------------------------------------------------------------------------------------------------------------

test('API: a community with a public discussion space answers a visitor, an outsider and a member', function (string $route, string $reader) {
    publicPageCommunity('discussion');

    publicPageApiAs($reader, publicPageApiUrls()[$route])->assertOk();
})->with(['community', 'space groups', 'newest posts'])->with('readers');

test('API: a community without a public space answers a visitor 404, an outsider 403, a member 200', function (string $route, string $kind) {
    publicPageCommunity($kind);
    $url = publicPageApiUrls()[$route];

    publicPageApiAs('visitor', $url)->assertNotFound();
    publicPageApiAs('outsider', $url)->assertForbidden();
    publicPageApiAs('member', $url)->assertOk();
})->with(['community', 'space groups', 'newest posts'])->with('communities without a public page');

test('API: a community that does not exist answers a visitor and an outsider 404, like a community without a public space does to a visitor', function (string $route) {
    publicPageCommunity('none');
    $url = str_replace($this->c['community']->cuid, 'no-such-community', publicPageApiUrls()[$route]);

    publicPageApiAs('visitor', $url)->assertNotFound();
    publicPageApiAs('outsider', $url)->assertNotFound();
})->with(['community', 'space groups', 'newest posts']);

test('API: a visitor gets the same answer for a community without a public space and for one that does not exist', function () {
    publicPageCommunity('none');
    $url = publicPageApiUrls()['community'];

    $without = publicPageApiAs('visitor', $url)->assertNotFound()->json();
    $missing = publicPageApiAs('visitor', str_replace($this->c['community']->cuid, 'no-such-community', $url))->assertNotFound()->json();

    expect($without)->toBe($missing);
});

test('API: what a visitor and an outsider get holds no member data, nothing of a chat space, nothing that is not public', function (string $route, string $reader) {
    publicPageCommunity('discussion');

    $data = (array) publicPageApiAs($reader, publicPageApiUrls()[$route])->assertOk()->json();

    publicPageAssertNoLeak($data, 'visitor');
    expect((string) json_encode($data))->not->toContain('"reacted":true');
})->with(['community', 'space groups', 'newest posts'])->with('non-members');

test('API: the community of a visitor and of an outsider is the public shape, the one of a member is the full community', function () {
    publicPageCommunity('discussion');
    $community = $this->c['community'];

    foreach (['visitor', 'outsider'] as $reader) {
        expect(publicPageApiAs($reader, publicPageApiUrls()['community'])->json('data'))->toBe([
            'cuid' => $community->cuid,
            'name' => $community->name,
            'slug' => $community->slug,
            'description' => $community->description ?? '',
            'visibility' => $community->visibility->value,
            'join_policy' => 'invitation',
            'is_member' => false,
        ]);
    }
    expect(publicPageApiAs('member', publicPageApiUrls()['community'])->json('data'))->toHaveKeys(['moderator_permissions', 'member_count', 'direct_messages_enabled']);
});

test('API: the space groups of a visitor and of an outsider hold the public spaces that are not a chat space, a member has their own tree', function () {
    publicPageCommunity('discussion');
    $spacesOf = fn (string $reader) => collect(publicPageApiAs($reader, publicPageApiUrls()['space groups'])->json('data'))->flatMap(fn (array $group) => $group['spaces'])->pluck('cuid')->all();

    expect($spacesOf('visitor'))->toBe([$this->publicSpace->cuid])
        ->and($spacesOf('outsider'))->toBe([$this->publicSpace->cuid])
        ->and($spacesOf('member'))->toContain($this->hiddenMembers->cuid)->toContain($this->publicChat->cuid);
});

test('API: the newest posts are the same list as on the page, and a member gets it too', function () {
    publicPageCommunity('discussion');

    $page = publicPageAs('visitor', publicPageUrl())->viewData('page')['props']['newestPosts'];
    foreach (['visitor', 'outsider', 'member'] as $reader) {
        expect(publicPageApiAs($reader, publicPageApiUrls()['newest posts'])->json('data'))->toBe($page);
    }
});

// ---------------------------------------------------------------------------------------------------------------------
// API: tokens, verification, rate limit
// ---------------------------------------------------------------------------------------------------------------------

test('API: a token keeps its abilities on the three reads', function () {
    publicPageCommunity('discussion');
    $urls = publicPageApiUrls();

    Sanctum::actingAs($this->c['outsider'], ['communities:read']);
    $this->getJson($urls['community'])->assertOk();
    $this->getJson($urls['space groups'])->assertOk();
    $this->getJson($urls['newest posts'])->assertForbidden();

    Sanctum::actingAs($this->c['outsider'], ['posts:read']);
    $this->getJson($urls['newest posts'])->assertOk();
    $this->getJson($urls['community'])->assertForbidden();
    $this->getJson($urls['space groups'])->assertForbidden();
});

test('API: a token that is not accepted is a 401, never a visitor', function (string $route) {
    publicPageCommunity('discussion');

    $this->withHeader('Authorization', 'Bearer 123|this-token-does-not-exist')->getJson(publicPageApiUrls()[$route])->assertUnauthorized();
})->with(['community', 'space groups', 'newest posts']);

test('API: an unverified account is refused', function (string $route) {
    publicPageCommunity('discussion');
    $unverified = User::factory()->unverified()->withUserProfile()->create();

    $this->actingAs($unverified)->getJson(publicPageApiUrls()[$route])->assertForbidden();

    Sanctum::actingAs($unverified, ['*']);
    $this->getJson(publicPageApiUrls()[$route])->assertForbidden();
})->with(['community', 'space groups', 'newest posts']);

test('API: the writes of the community stay for signed-in people', function () {
    publicPageCommunity('discussion');
    $url = publicPageApiUrls()['community'];

    $this->patchJson($url, ['name' => 'Changed'])->assertUnauthorized();
    $this->deleteJson($url)->assertUnauthorized();
    $this->postJson(publicPageApiUrls()['space groups'], ['name' => 'Group'])->assertUnauthorized();
    $this->actingAs($this->c['outsider'])->postJson(publicPageApiUrls()['space groups'], ['name' => 'Group'])->assertForbidden();
});

test('API: visitors are limited per IP address on the three reads, signed-in people are not', function () {
    publicPageCommunity('discussion');
    RateLimiter::clear('ip:127.0.0.1');
    $urls = publicPageApiUrls();

    // The three reads count together: they share the limit of the public reads
    for ($i = 0; $i < Constants::PUBLIC_READS_PER_MINUTE; $i++) {
        $this->getJson(array_values($urls)[$i % 3])->assertOk();
    }
    foreach ($urls as $url) {
        $this->getJson($url)->assertStatus(429);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson($urls['newest posts'])->assertOk();
    $this->actingAs($this->c['outsider'])->getJson($urls['community'])->assertOk();
});

test('community: a community that is not shared keeps its page for members only', function () {
    // A personal community without any public space: the owner reads it, nobody else
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $url = route('communities.show', ['communitySlug' => $community->slug]);

    $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
    app('auth')->forgetGuards();
    $this->get($url)->assertRedirect(route('login'));
});

test('community: a personal community has no public page, also with a space that says public', function () {
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal, 'name' => 'Field notes', 'description' => 'The notes of one person']);
    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    // A row that the actions refuse: the page does not depend on them
    $space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Public]);
    Post::factory()->inSpace($space)->create(['author_id' => $owner->id, 'title' => 'A note of the owner']);
    $outsider = User::factory()->withUserProfile()->create();
    $url = route('communities.show', ['communitySlug' => $community->slug]);
    $params = [ApiRoutes::PARAM_COMMUNITY => $community->cuid];

    expect(PublicCommunity::exists($community))->toBeFalse()
        ->and(PublicCommunity::showsPublicPage(null, $community))->toBeFalse()
        ->and(PublicCommunity::showsPublicPage($outsider, $community))->toBeFalse();

    // A visitor: the sign-in page on the web, 404 from the API, as for a community that does not exist
    $this->get($url)->assertRedirect(route('login'));
    foreach ([ApiRoutes::COMMUNITY, ApiRoutes::COMMUNITY_SPACE_GROUPS, ApiRoutes::COMMUNITY_NEWEST_POSTS] as $route) {
        $response = $this->getJson(ApiRoutes::path($route, $params))->assertNotFound();
        expect($response->getContent())->not->toContain('Field notes')->not->toContain('A note of the owner');
    }

    // A signed-in person who is not a member: the page without access, with nothing of the community
    $page = $this->actingAs($outsider)->get($url)->assertForbidden();
    expect($page->getContent())->not->toContain('Field notes')->not->toContain('The notes of one person')->not->toContain('A note of the owner');
    $this->actingAs($outsider)->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY, $params))->assertForbidden();

    // The owner reads their community
    app('auth')->forgetGuards();
    $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Communities/Show'));
});

test('community: a space of a personal community is for its owner only, also when its row says public', function () {
    $owner = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);
    $group = SpaceGroup::factory()->create(['community_id' => $community->id]);
    $discussion = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Discussion, 'visibility' => KnowiiSpaceVisibility::Members]);
    $events = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Events, 'visibility' => KnowiiSpaceVisibility::Members]);
    // A row that the actions refuse, written in the database: the reading rule does not depend on them
    DB::table('spaces')->whereIn('id', [$discussion->id, $events->id])->update(['visibility' => KnowiiSpaceVisibility::Public->value]);
    $post = Post::factory()->inSpace($discussion)->create(['author_id' => $owner->id, 'title' => 'A note of the owner']);
    EventModel::factory()->inSpace($events)->hostedBy($owner)->create(['title' => 'A meeting of the owner']);
    $outsider = User::factory()->withUserProfile()->create();
    $params = [ApiRoutes::PARAM_COMMUNITY => $community->cuid];

    $reads = [
        'web space' => fn () => test()->get(route('spaces.show', ['communitySlug' => $community->slug, 'spaceSlug' => $discussion->slug])),
        'web post' => fn () => test()->get(route('posts.show', ['communitySlug' => $community->slug, 'spaceSlug' => $discussion->slug, 'postSlug' => $post->slug])),
        'api space' => fn () => test()->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [...$params, ApiRoutes::PARAM_SPACE => $discussion->cuid])),
        'api posts' => fn () => test()->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POSTS, [...$params, ApiRoutes::PARAM_SPACE => $discussion->cuid])),
        'api events' => fn () => test()->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_EVENTS, [...$params, ApiRoutes::PARAM_SPACE => $events->cuid])),
    ];

    $statuses = [];
    foreach ([null, $outsider] as $reader) {
        foreach ($reads as $what => $read) {
            app('auth')->forgetGuards();
            test()->flushSession();
            if ($reader !== null) {
                test()->actingAs($reader);
            }
            $response = $read();
            $statuses[($reader === null ? 'visitor ' : 'outsider ').$what] = $response->getStatusCode();
            expect($response->getContent())->not->toContain('A note of the owner')->not->toContain('A meeting of the owner');
        }
        expect(Gate::forUser($reader)->check('view', $discussion->fresh()))->toBeFalse()
            ->and(Gate::forUser($reader)->check('view', $events->fresh()))->toBeFalse();
    }
    // A visitor: the sign-in page on the web, 401 from the API; another account: 403
    expect($statuses)->toBe([
        'visitor web space' => 302, 'visitor web post' => 302, 'visitor api space' => 401, 'visitor api posts' => 401, 'visitor api events' => 401,
        'outsider web space' => 403, 'outsider web post' => 403, 'outsider api space' => 403, 'outsider api posts' => 403, 'outsider api events' => 403,
    ]);

    // The owner reads the spaces, the post and the event
    app('auth')->forgetGuards();
    test()->actingAs($owner);
    foreach ($reads as $read) {
        $read()->assertOk();
    }
    expect($reads['api posts']()->getContent())->toContain('A note of the owner')
        ->and($reads['api events']()->getContent())->toContain('A meeting of the owner');
});
