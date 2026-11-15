<?php

use App\ApiRoutes;
use App\Broadcasting\SpacePresenceChannel;
use App\Constants;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Page;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\SpaceTree;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
 * Access groups that open a whole space group (#1422, ADR 0041).
 */

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class, AccessGroupsChanged::class]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->courses = SpaceGroup::factory()->create(['community_id' => $this->community->id, 'name' => 'Courses']);
    $this->elsewhere = SpaceGroup::factory()->create(['community_id' => $this->community->id, 'name' => 'Elsewhere']);
    $this->group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid members']);
    $this->group->spaceGroups()->attach($this->courses);

    $this->insider = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($this->insider, ['role' => 'member']);
    $this->group->members()->attach($this->insider);

    // One "groups" space of each kind of content, none of them has a group of its own
    foreach (['discussion' => KnowiiSpaceType::Discussion, 'chat' => KnowiiSpaceType::Chat, 'pages' => KnowiiSpaceType::Pages, 'course' => KnowiiSpaceType::Course, 'resources' => KnowiiSpaceType::Resources] as $key => $type) {
        $this->{$key} = Space::factory()->inGroup($this->courses)->create(['type' => $type, 'visibility' => KnowiiSpaceVisibility::Groups]);
    }
});

function sgagSpacePath(Space $space, string $route): string
{
    return ApiRoutes::path($route, [ApiRoutes::PARAM_COMMUNITY => $space->community->cuid, ApiRoutes::PARAM_SPACE => $space->cuid]);
}

function sgagCanView(?User $user, Space $space): bool
{
    return Gate::forUser($user)->check('view', $space->fresh());
}

function sgagAccessGroupLinksPath(Community $community, AccessGroup $group): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUP_SPACE_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_ACCESS_GROUP => $group->cuid]);
}

function sgagSpaceGroupLinksPath(Community $community, SpaceGroup $spaceGroup): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP_ACCESS_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid, ApiRoutes::PARAM_SPACE_GROUP => $spaceGroup->cuid]);
}

function sgagSpaceUpdatePath(Space $space): string
{
    return sgagSpacePath($space, ApiRoutes::COMMUNITY_SPACE);
}

/**
 * @return list<string>
 */
function sgagTreeSpaceCuids(Community $community, ?User $user): array
{
    return SpaceTree::for($community, $user)->flatMap(fn (SpaceGroup $group) => $group->spaces->pluck('cuid'))->all();
}

// The reading rule

test('a groups space opens to the members of the access groups of its space group (#1422)', function () {
    foreach (['discussion', 'chat', 'pages', 'course', 'resources'] as $key) {
        expect(sgagCanView($this->insider->fresh(), $this->{$key}))->toBeTrue($key)
            ->and(sgagCanView($this->c['member'], $this->{$key}))->toBeFalse($key)
            ->and(sgagCanView($this->c['moderator'], $this->{$key}))->toBeFalse($key)
            ->and(sgagCanView($this->c['outsider'], $this->{$key}))->toBeFalse($key)
            ->and(sgagCanView(null, $this->{$key}))->toBeFalse($key)
            ->and(sgagCanView($this->c['owner'], $this->{$key}))->toBeTrue($key)
            ->and(sgagCanView($this->c['admin'], $this->{$key}))->toBeTrue($key);
    }
});

test('the group of the space itself and the group of its space group both open it (#1422)', function () {
    $own = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Beta testers']);
    $beta = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($beta, ['role' => 'member']);
    $own->members()->attach($beta);
    $this->discussion->accessGroups()->attach($own);

    expect(sgagCanView($beta->fresh(), $this->discussion))->toBeTrue()
        ->and(sgagCanView($beta->fresh(), $this->chat))->toBeFalse()
        ->and(sgagCanView($this->insider->fresh(), $this->discussion))->toBeTrue();
});

test('the open space groups change nothing for the spaces with another visibility (#1422)', function () {
    $members = Space::factory()->inGroup($this->courses)->create(['visibility' => KnowiiSpaceVisibility::Members]);
    $public = Space::factory()->inGroup($this->courses)->create(['visibility' => KnowiiSpaceVisibility::Public]);

    expect(sgagCanView($this->c['member'], $members))->toBeTrue()
        ->and(sgagCanView($this->c['outsider'], $members))->toBeFalse()
        ->and(sgagCanView($this->c['outsider'], $public))->toBeTrue()
        ->and($members->accessGroups()->count())->toBe(0);
});

test('a group of another space group opens nothing here (#1422)', function () {
    $other = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Elsewhere group']);
    $other->spaceGroups()->attach($this->elsewhere);
    $other->members()->attach($this->c['member']);

    expect(sgagCanView($this->c['member']->fresh(), $this->discussion))->toBeFalse();
});

test('a new space of the space group is open at once, with no step for the manager (#1422)', function () {
    $fresh = Space::factory()->inGroup($this->courses)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    $created = $this->actingAs($this->c['owner'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]), [
            'name' => 'Week one', 'type' => 'discussion', 'posting_policy' => 'members', 'visibility' => 'groups', 'space_group_cuid' => $this->courses->cuid,
        ])
        ->assertCreated()->assertJsonPath('data.access_groups', [])->json('data.cuid');
    $space = Space::query()->where('cuid', $created)->firstOrFail();

    expect(sgagCanView($this->insider->fresh(), $fresh))->toBeTrue()
        ->and(sgagCanView($this->insider->fresh(), $space))->toBeTrue()
        ->and(sgagCanView($this->c['member'], $space))->toBeFalse();
});

test('a space moved out of the space group is closed, a space moved in is open (#1422)', function () {
    $path = sgagSpaceUpdatePath($this->discussion);
    $this->actingAs($this->c['owner']);
    $this->elsewhere->accessGroups()->attach(AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Other readers']));

    $this->patchJson($path, ['space_group_cuid' => $this->elsewhere->cuid])->assertOk();
    expect(sgagCanView($this->insider->fresh(), $this->discussion))->toBeFalse()
        ->and(sgagCanView($this->c['admin'], $this->discussion))->toBeTrue();

    $this->patchJson($path, ['space_group_cuid' => $this->courses->cuid])->assertOk();
    expect(sgagCanView($this->insider->fresh(), $this->discussion))->toBeTrue();

    $inElsewhere = Space::factory()->inGroup($this->elsewhere)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    expect(sgagCanView($this->insider->fresh(), $inElsewhere))->toBeFalse();
    $this->patchJson(sgagSpaceUpdatePath($inElsewhere), ['space_group_cuid' => $this->courses->cuid])->assertOk();
    expect(sgagCanView($this->insider->fresh(), $inElsewhere))->toBeTrue();
});

test('a move that would leave a groups space without any group is refused (#1422)', function () {
    $this->actingAs($this->c['owner'])->patchJson(sgagSpaceUpdatePath($this->discussion), ['space_group_cuid' => $this->elsewhere->cuid])
        ->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    expect($this->discussion->fresh()->space_group_id)->toBe($this->courses->id)
        ->and(sgagCanView($this->insider->fresh(), $this->discussion))->toBeTrue();
});

test('a space moved out keeps the groups of its own (#1422)', function () {
    $own = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Beta testers']);
    $beta = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($beta, ['role' => 'member']);
    $own->members()->attach($beta);
    $this->discussion->accessGroups()->attach($own);

    $this->actingAs($this->c['owner'])->patchJson(sgagSpaceUpdatePath($this->discussion), ['space_group_cuid' => $this->elsewhere->cuid])->assertOk();

    expect(sgagCanView($beta->fresh(), $this->discussion))->toBeTrue()
        ->and(sgagCanView($this->insider->fresh(), $this->discussion))->toBeFalse();
});

test('a member removed from the group, or a link removed, closes the spaces (#1422)', function () {
    $this->group->members()->detach($this->insider);
    expect(sgagCanView($this->insider->fresh(), $this->discussion))->toBeFalse();

    $this->group->members()->attach($this->insider);
    expect(sgagCanView($this->insider->fresh(), $this->discussion))->toBeTrue();
    $this->group->spaceGroups()->detach($this->courses);
    expect(sgagCanView($this->insider->fresh(), $this->discussion))->toBeFalse();
});

test('a member who cannot read a space cannot post in it, a member of the group can (#1422)', function () {
    expect(Gate::forUser($this->c['member'])->check('contribute', $this->discussion->fresh()))->toBeFalse()
        ->and(Gate::forUser($this->insider->fresh())->check('contribute', $this->discussion->fresh()))->toBeTrue();

    $path = sgagSpacePath($this->discussion, ApiRoutes::COMMUNITY_SPACE_POSTS);
    $this->actingAs($this->c['member'])->postJson($path, ['title' => 'Hello there', 'body' => 'Text'])->assertForbidden();
    $this->actingAs($this->insider->fresh())->postJson($path, ['title' => 'Hello there', 'body' => 'Text'])->assertCreated();
});

// Every read path

test('the navigation shows the spaces of the open space group to its members only (#1422)', function () {
    expect(sgagTreeSpaceCuids($this->community, $this->insider->fresh()))->toContain($this->discussion->cuid, $this->chat->cuid, $this->pages->cuid, $this->course->cuid, $this->resources->cuid)
        ->and(sgagTreeSpaceCuids($this->community, $this->c['member']))->toBe([])
        ->and(sgagTreeSpaceCuids($this->community, $this->c['admin']))->toContain($this->discussion->cuid);

    $this->actingAs($this->insider->fresh())->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()->assertJsonCount(5, 'data.0.spaces');
    $this->actingAs($this->c['member'])->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()->assertJsonCount(0, 'data');
});

test('the navigation reads the access groups with a fixed number of queries, for few spaces and for many (#1422)', function () {
    $count = function (): int {
        $user = $this->insider->fresh();
        DB::flushQueryLog();
        DB::enableQueryLog();
        SpaceTree::for($this->community->fresh(), $user);
        $queries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains((string) $query['query'], 'access_group'))->count();
        DB::disableQueryLog();

        return $queries;
    };

    $few = $count();
    Space::factory()->count(6)->inGroup($this->courses)->create(['visibility' => KnowiiSpaceVisibility::Groups]);
    Space::factory()->count(3)->inGroup($this->elsewhere)->create(['visibility' => KnowiiSpaceVisibility::Groups]);

    // The groups of the space groups, the groups of the spaces and the groups of the member: one query each
    expect($few)->toBe(3)->and($count())->toBe(3);
});

test('the space pages, the posts and the comments follow the rule (#1422)', function () {
    $post = Post::factory()->inSpace($this->discussion)->create(['author_id' => $this->c['owner']->id]);
    Comment::factory()->onPost($post)->create(['author_id' => $this->c['owner']->id]);
    $postsPath = sgagSpacePath($this->discussion, ApiRoutes::COMMUNITY_SPACE_POSTS);
    $commentsPath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE => $this->discussion->cuid, ApiRoutes::PARAM_POST => $post->cuid]);
    $postPage = route('posts.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->discussion->slug, 'postSlug' => $post->slug]);
    $spacePage = route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->discussion->slug]);

    $this->actingAs($this->c['member']);
    $this->getJson($postsPath)->assertForbidden();
    $this->getJson($commentsPath)->assertForbidden();
    $this->get($postPage)->assertForbidden();
    $this->get($spacePage)->assertForbidden();

    $this->actingAs($this->insider->fresh());
    $this->getJson($postsPath)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson($commentsPath)->assertOk()->assertJsonCount(1, 'data');
    $this->get($postPage)->assertOk();
    $this->get($spacePage)->assertOk();
});

test('chat, its read marker, its unread count and its online members follow the rule (#1422)', function () {
    $message = ChatMessage::factory()->inSpace($this->chat)->create(['author_id' => $this->c['owner']->id]);
    $messages = sgagSpacePath($this->chat, ApiRoutes::COMMUNITY_SPACE_MESSAGES);
    $read = sgagSpacePath($this->chat, ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ);
    $online = sgagSpacePath($this->chat, ApiRoutes::COMMUNITY_SPACE_ONLINE_MEMBERS);

    $this->actingAs($this->c['member']);
    $this->getJson($messages)->assertForbidden();
    $this->postJson($read, ['message' => $message->cuid])->assertForbidden();
    $this->getJson($online)->assertForbidden();

    $this->actingAs($this->insider->fresh());
    $this->getJson($messages)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson($online)->assertOk();
    $tree = SpaceTree::for($this->community, $this->insider->fresh());
    $chat = $tree->flatMap(fn (SpaceGroup $group) => $group->spaces)->firstWhere('cuid', $this->chat->cuid);
    expect($chat?->getAttribute('unread_count'))->toBe(1);
    expect(sgagTreeSpaceCuids($this->community, $this->c['member']))->not->toContain($this->chat->cuid);
});

test('pages and lessons follow the rule (#1422)', function () {
    $page = Page::factory()->inSpace($this->pages)->create(['title' => 'Welcome']);
    $lesson = Page::factory()->inSpace($this->course)->create(['title' => 'First lesson']);
    $pagesPath = sgagSpacePath($this->pages, ApiRoutes::COMMUNITY_SPACE_PAGES);
    $lessonsPath = sgagSpacePath($this->course, ApiRoutes::COMMUNITY_SPACE_PAGES);

    $this->actingAs($this->c['member']);
    $this->getJson($pagesPath)->assertForbidden();
    $this->getJson($lessonsPath)->assertForbidden();
    $this->get(route('pages.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->pages->slug, 'pageSlug' => $page->slug]))->assertForbidden();
    $this->get(route('pages.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $lesson->slug]))->assertForbidden();

    $this->actingAs($this->insider->fresh());
    $this->getJson($pagesPath)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson($lessonsPath)->assertOk()->assertJsonCount(1, 'data');
    $this->get(route('pages.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->pages->slug, 'pageSlug' => $page->slug]))->assertOk();
    $this->get(route('pages.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->course->slug, 'pageSlug' => $lesson->slug]))->assertOk();
});

test('resources and course materials follow the rule (#1422)', function () {
    $resources = sgagSpacePath($this->resources, ApiRoutes::COMMUNITY_SPACE_RESOURCES);
    $materials = sgagSpacePath($this->course, ApiRoutes::COMMUNITY_SPACE_MATERIALS);

    $this->actingAs($this->c['member']);
    $this->getJson($resources)->assertForbidden();
    $this->getJson($materials)->assertForbidden();
    $this->get(route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->resources->slug]))->assertForbidden();

    $this->actingAs($this->insider->fresh());
    $this->getJson($resources)->assertOk();
    $this->getJson($materials)->assertOk();
    $this->get(route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $this->resources->slug]))->assertOk();
});

test('the channels of a space follow the rule (#1422)', function () {
    $channel = Broadcast::driver()->getChannels()->get(Constants::WS_CHANNEL_COMMUNITY_SPACE);
    $presence = new SpacePresenceChannel;
    $communityCuid = $this->community->cuid;

    expect($channel($this->insider->fresh(), $communityCuid, $this->discussion->cuid))->toBeTrue()
        ->and($channel($this->c['member'], $communityCuid, $this->discussion->cuid))->toBeFalse()
        ->and($channel($this->c['admin'], $communityCuid, $this->discussion->cuid))->toBeTrue()
        ->and($presence->join($this->insider->fresh(), $communityCuid, $this->chat->cuid))->toBeArray()
        ->and($presence->join($this->c['member'], $communityCuid, $this->chat->cuid))->toBeFalse();

    $this->group->spaceGroups()->detach($this->courses);
    expect($channel($this->insider->fresh(), $communityCuid, $this->discussion->cuid))->toBeFalse();
});

// Spaces with this rule

test('a groups space needs a group of its own or a group of its space group (#1422)', function () {
    $spacesPath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]);
    $base = ['name' => 'Lounge', 'type' => 'discussion', 'posting_policy' => 'members', 'visibility' => 'groups'];
    $this->actingAs($this->c['owner']);

    // In a space group with no access group, a groups space still needs one
    $this->postJson($spacesPath, [...$base, 'space_group_cuid' => $this->elsewhere->cuid])->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->postJson($spacesPath, [...$base, 'space_group_cuid' => $this->elsewhere->cuid, 'access_groups' => []])->assertStatus(400);
    $this->postJson($spacesPath, [...$base, 'space_group_cuid' => $this->elsewhere->cuid, 'access_groups' => [$this->group->cuid]])->assertCreated();
    // In the space group that a group opens, it does not
    $this->postJson($spacesPath, [...$base, 'space_group_cuid' => $this->courses->cuid])->assertCreated();
    $this->postJson($spacesPath, [...$base, 'name' => 'Lounge two', 'space_group_cuid' => $this->courses->cuid, 'access_groups' => []])->assertCreated();
    // A group that is not of this community is still refused
    $foreign = AccessGroup::query()->create(['community_id' => Community::factory()->create()->id, 'name' => 'Foreign']);
    $this->postJson($spacesPath, [...$base, 'name' => 'Lounge three', 'space_group_cuid' => $this->courses->cuid, 'access_groups' => [$foreign->cuid]])->assertStatus(400);
});

test('a members space can become a groups space when its space group has a group, and not otherwise (#1422)', function () {
    $inCourses = Space::factory()->inGroup($this->courses)->create(['visibility' => KnowiiSpaceVisibility::Members]);
    $inElsewhere = Space::factory()->inGroup($this->elsewhere)->create(['visibility' => KnowiiSpaceVisibility::Members]);
    $this->actingAs($this->c['owner']);

    $this->patchJson(sgagSpaceUpdatePath($inCourses), ['visibility' => 'groups'])->assertOk();
    $this->patchJson(sgagSpaceUpdatePath($inElsewhere), ['visibility' => 'groups'])->assertStatus(400);
    // Moving it in the same request counts: the space group of after the move decides
    $this->patchJson(sgagSpaceUpdatePath($inElsewhere), ['visibility' => 'groups', 'space_group_cuid' => $this->courses->cuid])->assertOk();
    expect(sgagCanView($this->c['member'], $inElsewhere))->toBeFalse()
        ->and(sgagCanView($this->insider->fresh(), $inElsewhere))->toBeTrue();
});

test('the space shows the groups of its own, not the ones of its space group (#1422)', function () {
    $own = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Beta testers']);
    $this->discussion->accessGroups()->attach($own);

    $this->actingAs($this->c['owner'])->getJson(sgagSpaceUpdatePath($this->discussion))
        ->assertOk()->assertJsonCount(1, 'data.access_groups')->assertJsonPath('data.access_groups.0.cuid', $own->cuid);
});

// The API

test('the access groups of a space group are listed, and set from either side (#1422)', function () {
    $second = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Alumni']);
    $this->actingAs($this->c['admin']);

    $this->getJson(sgagSpaceGroupLinksPath($this->community, $this->courses))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Paid members');
    $this->getJson(sgagAccessGroupLinksPath($this->community, $this->group))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cuid', $this->courses->cuid);

    $this->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$this->group->cuid, $second->cuid, $second->cuid]])
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Alumni');
    expect($this->courses->accessGroups()->count())->toBe(2);

    $this->putJson(sgagAccessGroupLinksPath($this->community, $second), ['space_groups' => [$this->courses->cuid, $this->elsewhere->cuid]])
        ->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(sgagSpaceGroupLinksPath($this->community, $this->elsewhere))->assertOk()->assertJsonPath('data.0.cuid', $second->cuid);

    // Setting the same list again changes nothing
    $this->putJson(sgagAccessGroupLinksPath($this->community, $second), ['space_groups' => [$this->courses->cuid, $this->elsewhere->cuid]])->assertOk();
    expect(DB::table('access_group_space_group')->where('access_group_id', $second->id)->count())->toBe(2);

    // An empty list removes every link
    $this->putJson(sgagAccessGroupLinksPath($this->community, $second), ['space_groups' => []])->assertOk()->assertJsonCount(0, 'data');
    expect($second->spaceGroups()->count())->toBe(0)->and($this->group->spaceGroups()->count())->toBe(1);
    Event::assertDispatched(AccessGroupsChanged::class);
    Event::assertDispatched(SpaceTreeChanged::class);
});

test('giving a space group to a group opens its spaces to the members at once, through the API (#1422)', function () {
    $second = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Alumni']);
    $second->members()->attach($this->c['member']);
    expect(sgagCanView($this->c['member']->fresh(), $this->discussion))->toBeFalse();

    $this->actingAs($this->c['owner'])->putJson(sgagAccessGroupLinksPath($this->community, $second), ['space_groups' => [$this->courses->cuid]])->assertOk();
    expect(sgagCanView($this->c['member']->fresh(), $this->discussion))->toBeTrue();

    $this->actingAs($this->c['owner'])->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$this->group->cuid]])->assertOk();
    expect(sgagCanView($this->c['member']->fresh(), $this->discussion))->toBeFalse()
        ->and(sgagCanView($this->insider->fresh(), $this->discussion))->toBeTrue();
});

test('the same people who give a space its groups give a space group its groups (#1422)', function () {
    $body = ['space_groups' => [$this->courses->cuid]];
    $reverse = ['access_groups' => [$this->group->cuid]];

    foreach (['owner', 'admin'] as $who) {
        $this->actingAs($this->c[$who])->putJson(sgagAccessGroupLinksPath($this->community, $this->group), $body)->assertOk();
        $this->actingAs($this->c[$who])->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), $reverse)->assertOk();
    }

    foreach (['moderator', 'member', 'outsider'] as $who) {
        $this->actingAs($this->c[$who])->putJson(sgagAccessGroupLinksPath($this->community, $this->group), $body)->assertForbidden();
        $this->actingAs($this->c[$who])->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), $reverse)->assertForbidden();
        $this->actingAs($this->c[$who])->getJson(sgagAccessGroupLinksPath($this->community, $this->group))->assertForbidden();
        $this->actingAs($this->c[$who])->getJson(sgagSpaceGroupLinksPath($this->community, $this->courses))->assertForbidden();
    }
    expect($this->group->spaceGroups()->count())->toBe(1);

    // A moderator with the "manage spaces" right is one of them; "manage members" reads but does not write
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_members' => true]]);
    $this->actingAs($this->c['moderator']->fresh())->getJson(sgagAccessGroupLinksPath($this->community, $this->group))->assertOk();
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => []])->assertForbidden();

    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_spaces' => true]]);
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => [$this->courses->cuid, $this->elsewhere->cuid]])->assertOk();
    expect($this->group->spaceGroups()->pluck('space_groups.cuid')->sort()->values()->all())->toBe(collect([$this->courses->cuid, $this->elsewhere->cuid])->sort()->values()->all());
});

test('a moderator cannot open a closed space group to a group of their own, nor close it (#1422, #1325)', function () {
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_spaces' => true]]);
    $own = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Free members']);
    $own->members()->attach($this->c['moderator']);
    expect(sgagCanView($this->c['moderator']->fresh(), $this->discussion))->toBeFalse();

    // From both sides: the moderator cannot read the "groups" spaces of Courses, so its links are out of reach
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagAccessGroupLinksPath($this->community, $own), ['space_groups' => [$this->courses->cuid]])->assertForbidden();
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$this->group->cuid, $own->cuid]])->assertForbidden();
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => []])->assertForbidden();
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => []])->assertForbidden();
    expect(sgagCanView($this->c['moderator']->fresh(), $this->discussion))->toBeFalse();
    expect($this->courses->accessGroups()->pluck('access_groups.id')->all())->toBe([$this->group->id]);

    // The same list again changes nothing, so it passes; a space group without a closed space is free
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$this->group->cuid]])->assertOk();
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagAccessGroupLinksPath($this->community, $own), ['space_groups' => [$this->elsewhere->cuid]])->assertOk();

    // A moderator who reads every "groups" space of the space group manages its links
    $this->group->members()->attach($this->c['moderator']);
    $this->actingAs($this->c['moderator']->fresh())->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$this->group->cuid, $own->cuid]])->assertOk();
});

test('the links refuse what is not of the community, and a bad body (#1422)', function () {
    $other = communityWithMembers();
    $foreignSpaceGroup = SpaceGroup::factory()->create(['community_id' => $other['community']->id]);
    $foreignAccessGroup = AccessGroup::query()->create(['community_id' => $other['community']->id, 'name' => 'Foreign']);
    $this->actingAs($this->c['owner']);

    $this->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => [$foreignSpaceGroup->cuid]])
        ->assertStatus(400)->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
    $this->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => [$this->courses->cuid, 'nope']])->assertStatus(400);
    $this->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [$foreignAccessGroup->cuid]])->assertStatus(400);
    $this->putJson(sgagAccessGroupLinksPath($this->community, $this->group), [])->assertStatus(400);
    $this->putJson(sgagAccessGroupLinksPath($this->community, $this->group), ['space_groups' => 'x'])->assertStatus(400);
    $this->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), ['access_groups' => [[]]])->assertStatus(400);
    $this->putJson(sgagSpaceGroupLinksPath($this->community, $this->courses), [])->assertStatus(400);
    expect($this->group->spaceGroups()->count())->toBe(1);

    // A group or a space group of another community gives a 404 (scoped binding)
    $this->getJson(sgagAccessGroupLinksPath($this->community, $foreignAccessGroup))->assertNotFound();
    $this->putJson(sgagSpaceGroupLinksPath($this->community, $foreignSpaceGroup), ['access_groups' => []])->assertNotFound();
    expect(DB::table('access_group_space_group')->count())->toBe(1);
});

test('the links need the token abilities groups:read and groups:write (#1422)', function () {
    $accessSide = sgagAccessGroupLinksPath($this->community, $this->group);
    $spaceSide = sgagSpaceGroupLinksPath($this->community, $this->courses);

    Sanctum::actingAs($this->c['owner'], ['communities:read', 'communities:write']);
    $this->getJson($accessSide)->assertForbidden();
    $this->getJson($spaceSide)->assertForbidden();
    $this->putJson($accessSide, ['space_groups' => []])->assertForbidden();
    $this->putJson($spaceSide, ['access_groups' => []])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['groups:read']);
    $this->getJson($accessSide)->assertOk();
    $this->getJson($spaceSide)->assertOk();
    $this->putJson($accessSide, ['space_groups' => []])->assertForbidden();
    $this->putJson($spaceSide, ['access_groups' => []])->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['groups:write']);
    $this->putJson($accessSide, ['space_groups' => [$this->courses->cuid]])->assertOk();
    $this->putJson($spaceSide, ['access_groups' => [$this->group->cuid]])->assertOk();
});

test('the list of access groups shows the space groups that each opens (#1422)', function () {
    $this->actingAs($this->c['owner'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]))
        ->assertOk()
        ->assertJsonPath('data.0.space_groups_count', 1)
        ->assertJsonPath('data.0.space_groups.0.cuid', $this->courses->cuid)
        ->assertJsonPath('data.0.space_groups.0.name', 'Courses');
});

// Deletions and the table

test('deleting an access group deletes its links to space groups (#1422)', function () {
    $this->actingAs($this->c['admin'])
        ->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_ACCESS_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_ACCESS_GROUP => $this->group->cuid]))
        ->assertNoContent();

    expect(DB::table('access_group_space_group')->count())->toBe(0)
        ->and(SpaceGroup::query()->whereKey($this->courses->id)->exists())->toBeTrue()
        ->and(sgagCanView($this->insider->fresh(), $this->discussion))->toBeFalse()
        ->and(sgagCanView($this->c['admin'], $this->discussion))->toBeTrue();
});

test('deleting a space group deletes its links to access groups (#1422)', function () {
    $empty = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
    $this->group->spaceGroups()->attach($empty);
    expect(DB::table('access_group_space_group')->count())->toBe(2);

    $this->actingAs($this->c['owner'])
        ->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_SPACE_GROUP => $empty->cuid]))
        ->assertNoContent();

    expect(DB::table('access_group_space_group')->count())->toBe(1)
        ->and(AccessGroup::query()->whereKey($this->group->id)->exists())->toBeTrue();
});

test('a pair of access group and space group is stored once (#1422)', function () {
    expect(fn () => $this->group->spaceGroups()->attach($this->courses))->toThrow(QueryException::class);
});

test('the community export lists the space groups of each access group (#1422)', function () {
    Storage::fake('local');

    $files = [];
    expect(Artisan::call('communities:export', ['slug' => $this->community->slug]))->toBe(0);
    $zip = new ZipArchive;
    expect($zip->open(trim(Artisan::output())))->toBeTrue();
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $files[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
    }
    $zip->close();
    $groups = json_decode($files['community.json'], true)['access_groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['space_groups'])->toBe([$this->courses->cuid]);
});
