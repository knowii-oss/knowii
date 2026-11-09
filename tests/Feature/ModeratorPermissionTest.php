<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiModeratorPermission;
use App\Models\Community;

beforeEach(function () {
    $this->c = communityWithMembers();
});

function moderatorPermissionsPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MODERATOR_PERMISSIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

test('a new community gives moderators the default rights (#1240)', function () {
    expect($this->c['community']->fresh()->moderatorPermissions())->toBe(KnowiiModeratorPermission::defaults());
});

test('owners and admins switch some moderator rights, the others keep their value (#1240)', function (string $role) {
    $this->actingAs($this->c[$role])
        ->patchJson(moderatorPermissionsPath($this->c['community']), ['manage_spaces' => true, 'pin_posts' => false])
        ->assertOk()
        ->assertJsonPath('data.moderator_permissions', [
            'manage_spaces' => true,
            'manage_members' => false,
            'pin_posts' => false,
            'delete_content' => true,
        ]);

    expect($this->c['community']->fresh()->moderatorsCan(KnowiiModeratorPermission::ManageSpaces))->toBeTrue();
})->with(['owner', 'admin']);

test('moderators, members and outsiders cannot switch the moderator rights (#1240)', function (string $role) {
    $this->actingAs($this->c[$role])
        ->patchJson(moderatorPermissionsPath($this->c['community']), ['manage_spaces' => true])
        ->assertForbidden();

    expect($this->c['community']->fresh()->moderatorsCan(KnowiiModeratorPermission::ManageSpaces))->toBeFalse();
})->with(['moderator', 'member', 'outsider']);

test('the moderator rights are booleans and unknown rights are ignored (#1240)', function () {
    $this->actingAs($this->c['owner']);

    $this->patchJson(moderatorPermissionsPath($this->c['community']), ['pin_posts' => 'sometimes'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    $this->patchJson(moderatorPermissionsPath($this->c['community']), ['become_owner' => true])->assertOk();
    expect($this->c['community']->fresh()->moderator_permissions)->not->toHaveKey('become_owner');
});

test('the settings page is open to moderators with the "manage spaces" right only (#1240)', function () {
    $url = route('communities.settings', ['communitySlug' => $this->c['community']->slug]);

    $this->actingAs($this->c['moderator'])->get($url)->assertForbidden();

    $this->c['community']->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_spaces' => true]]);

    $this->actingAs($this->c['moderator'])->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.canManageSpaces', true)
            ->where('permissions.canManageModeratorPermissions', false)
            ->where('permissions.canDeleteCommunity', false));
    $this->actingAs($this->c['member'])->get($url)->assertForbidden();
});
