<?php

use App\Actions\Spaces\SpaceInputRules;
use App\ApiRoutes;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id, 'name' => 'Start']);
});

function communityParams(array $extra = []): array
{
    return [ApiRoutes::PARAM_COMMUNITY => test()->community->cuid, ...$extra];
}

test('members list the space tree without the spaces they cannot see', function () {
    Space::factory()->inGroup($this->group)->create(['name' => 'Public', 'visibility' => KnowiiSpaceVisibility::Public]);
    Space::factory()->inGroup($this->group)->create(['name' => 'Members', 'visibility' => KnowiiSpaceVisibility::Members]);
    SpaceGroup::factory()->create(['community_id' => $this->community->id, 'name' => 'Empty', 'position' => 1]);

    $this->actingAs($this->c['member'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, communityParams()))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Start')
        ->assertJsonCount(2, 'data.0.spaces')
        // Members do not see empty groups
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->c['owner'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, communityParams()))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('outsiders cannot list the space tree of a private community', function () {
    $this->actingAs($this->c['outsider'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, communityParams()))
        ->assertForbidden();
});

test('admins create, rename, reorder and delete space groups through the API', function () {
    $this->actingAs($this->c['admin']);

    $created = $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, communityParams()), ['name' => 'Learn'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Learn')
        ->json('data.cuid');

    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, communityParams([ApiRoutes::PARAM_SPACE_GROUP => $created])), ['name' => 'Courses'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Courses');

    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS_REORDER, communityParams()), ['space_group_cuids' => [$created, $this->group->cuid]])
        ->assertOk()
        ->assertJsonPath('data.0.cuid', $created);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, communityParams([ApiRoutes::PARAM_SPACE_GROUP => $created])))
        ->assertNoContent();
});

test('members get a 403 when they manage space groups', function () {
    $this->actingAs($this->c['member'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUPS, communityParams()), ['name' => 'Learn'])
        ->assertForbidden();
});

test('a 422 explains why a space group with spaces cannot be deleted', function () {
    Space::factory()->inGroup($this->group)->create();

    $this->actingAs($this->c['owner'])
        ->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP, communityParams([ApiRoutes::PARAM_SPACE_GROUP => $this->group->cuid])))
        ->assertStatus(422)
        ->assertJsonPath('category', 'business');
});

test('admins create, read, update, reorder and delete spaces through the API', function () {
    $this->actingAs($this->c['admin']);

    $response = $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, communityParams()), [
        'space_group_cuid' => $this->group->cuid,
        'name' => 'General chat',
        'emoji' => '💬',
        'type' => 'chat',
        'visibility' => 'members',
        'posting_policy' => 'members',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'general-chat')
        ->assertJsonPath('data.type', 'chat')
        ->assertJsonPath('data.space_group_cuid', $this->group->cuid)
        ->assertJsonMissingPath('data.id');

    $space = $response->json('data.cuid');
    $other = Space::factory()->inGroup($this->group)->create(['position' => 9]);
    $spaceParams = communityParams([ApiRoutes::PARAM_SPACE => $space]);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, $spaceParams))->assertOk()->assertJsonPath('data.name', 'General chat');

    $this->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, $spaceParams), ['description' => 'Say hi'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Say hi')
        ->assertJsonPath('data.name', 'General chat');

    $this->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, communityParams([ApiRoutes::PARAM_SPACE_GROUP => $this->group->cuid])), [
        'space_cuids' => [$other->cuid, $space],
    ])->assertOk()->assertJsonPath('data.1.cuid', $space);

    $this->deleteJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, $spaceParams))->assertNoContent();
    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, $spaceParams))->assertNotFound();
});

test('validation errors return 400 with the field errors', function () {
    $this->actingAs($this->c['owner'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, communityParams()), ['space_group_cuid' => $this->group->cuid, 'name' => 'Chat', 'type' => 'forum'])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['type', 'visibility', 'posting_policy']]);
});

test('a space of another community gives a 404', function () {
    $foreign = Space::factory()->create();

    $this->actingAs($this->c['owner'])
        ->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, communityParams([ApiRoutes::PARAM_SPACE => $foreign->cuid])))
        ->assertNotFound();
});

test('outsiders read public spaces but not members-only spaces', function () {
    $public = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    $private = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Members]);

    $this->actingAs($this->c['outsider']);

    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, communityParams([ApiRoutes::PARAM_SPACE => $public->cuid])))->assertOk();
    $this->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, communityParams([ApiRoutes::PARAM_SPACE => $private->cuid])))->assertForbidden();
});

test('the space page renders for members and respects visibility', function () {
    $space = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Members]);
    $url = route('spaces.show', ['communitySlug' => $this->community->slug, 'spaceSlug' => $space->slug]);

    $this->actingAs($this->c['member'])->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Spaces/Show')
            ->where('space.cuid', $space->cuid)
            ->where('permissions.canManageSpaces', false)
            ->where('permissions.canContribute', true)
            ->has('spaceGroups', 1));

    $this->actingAs($this->c['outsider'])->get($url)->assertForbidden();
});

test('the space policy follows the posting policy', function () {
    $announcements = Space::factory()->inGroup($this->group)->create(['posting_policy' => 'moderators']);

    expect($this->c['member']->can('contribute', $announcements))->toBeFalse()
        ->and($this->c['moderator']->can('contribute', $announcements))->toBeTrue()
        ->and($this->c['admin']->can('contribute', $announcements))->toBeTrue()
        ->and($this->c['outsider']->can('contribute', $announcements))->toBeFalse()
        ->and($this->c['moderator']->can('moderate', $announcements))->toBeTrue()
        ->and($this->c['member']->can('moderate', $announcements))->toBeFalse();
});

test('a space of a personal community cannot be public, at the creation and at a change', function () {
    $owner = User::factory()->withUserProfile()->create();
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);
    $group = SpaceGroup::factory()->create(['community_id' => $personal->id]);
    $input = ['space_group_cuid' => $group->cuid, 'name' => 'Notes', 'type' => 'discussion', 'visibility' => 'public', 'posting_policy' => 'members'];
    $params = [ApiRoutes::PARAM_COMMUNITY => $personal->cuid];

    $this->actingAs($owner)->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, $params), $input)
        ->assertStatus(400)
        ->assertJsonPath('errors.visibility.0', SpaceInputRules::PERSONAL_COMMUNITY_MESSAGE);
    expect($personal->spaces()->count())->toBe(0);

    $cuid = $this->actingAs($owner)->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, $params), [...$input, 'visibility' => 'members'])
        ->assertCreated()
        ->json('data.cuid');
    $spacePath = ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE, [...$params, ApiRoutes::PARAM_SPACE => $cuid]);

    $this->actingAs($owner)->patchJson($spacePath, ['visibility' => 'public'])
        ->assertStatus(400)
        ->assertJsonPath('errors.visibility.0', SpaceInputRules::PERSONAL_COMMUNITY_MESSAGE);
    $this->actingAs($owner)->patchJson($spacePath, ['name' => 'Renamed', 'visibility' => 'members'])->assertOk();
    expect($personal->spaces()->firstOrFail()->visibility)->toBe(KnowiiSpaceVisibility::Members);

    // A shared community keeps its public spaces
    $this->actingAs($this->c['owner'])
        ->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACES, communityParams()), [...$input, 'space_group_cuid' => $this->group->cuid])
        ->assertCreated()
        ->assertJsonPath('data.visibility', 'public');
});

test('the migration turns the public spaces of the personal communities into spaces for members, and leaves the others', function () {
    $owner = User::factory()->withUserProfile()->create();
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);
    $personalGroup = SpaceGroup::factory()->create(['community_id' => $personal->id]);
    $wasPublic = Space::factory()->inGroup($personalGroup)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    $wasMembers = Space::factory()->inGroup($personalGroup)->create(['visibility' => KnowiiSpaceVisibility::Members]);
    $sharedPublic = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Public]);
    $sharedGroups = Space::factory()->inGroup($this->group)->create(['visibility' => KnowiiSpaceVisibility::Groups]);

    $migration = require database_path('migrations/2026_11_24_120000_close_the_public_spaces_of_personal_communities.php');
    $migration->up();
    $migration->up();

    expect($wasPublic->fresh()->visibility)->toBe(KnowiiSpaceVisibility::Members)
        ->and($wasMembers->fresh()->visibility)->toBe(KnowiiSpaceVisibility::Members)
        ->and($sharedPublic->fresh()->visibility)->toBe(KnowiiSpaceVisibility::Public)
        ->and($sharedGroups->fresh()->visibility)->toBe(KnowiiSpaceVisibility::Groups);
});
