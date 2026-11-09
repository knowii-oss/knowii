<?php

use App\ApiRoutes;
use App\Models\Community;

beforeEach(function () {
    $this->c = communityWithMembers();
});

function aboutUrl(Community $community): string
{
    return route('communities.about', ['communitySlug' => $community->slug]);
}

test('members see the details page with the team, the member count and their role (#1135)', function (string $role) {
    $this->actingAs($this->c[$role])->get(aboutUrl($this->c['community']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/About')
            ->where('community.member_count', 4)
            ->where('currentRole', $role)
            ->has('team', 3)
            ->where('team.0.role', 'owner')
            ->where('team.1.role', 'admin')
            ->where('team.2.role', 'moderator')
            ->where('team.0.cuid', $this->c['owner']->cuid)
            ->has('permissions')
            ->has('spaceGroups'));
})->with(['owner', 'admin', 'moderator', 'member']);

test('the details page gives each role its controls (#1135)', function () {
    $community = $this->c['community'];

    $this->actingAs($this->c['member'])->get(aboutUrl($community))
        ->assertInertia(fn ($page) => $page->where('permissions.canManageCommunity', false)
            ->where('permissions.canAddCommunityMembers', false)
            ->where('permissions.canDeleteCommunity', false));

    $this->actingAs($this->c['admin'])->get(aboutUrl($community))
        ->assertInertia(fn ($page) => $page->where('permissions.canManageCommunity', true)
            ->where('permissions.canAddCommunityMembers', true)
            ->where('permissions.canDeleteCommunity', false));

    $this->actingAs($this->c['owner'])->get(aboutUrl($community))
        ->assertInertia(fn ($page) => $page->where('permissions.canUpdateCommunity', true)
            ->where('permissions.canDeleteCommunity', true));
});

test('outsiders cannot see the details page (#1135)', function () {
    $this->actingAs($this->c['outsider'])->get(aboutUrl($this->c['community']))->assertForbidden();
});

test('the community page counts the members (#1135)', function () {
    $this->actingAs($this->c['member'])->get(route('communities.show', ['communitySlug' => $this->c['community']->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('community.member_count', 4));
});

test('the API returns a community with its member count, to members only (#1135)', function () {
    $path = ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $this->c['community']->cuid]);

    $this->actingAs($this->c['member'])->getJson($path)
        ->assertOk()
        ->assertJsonPath('data.cuid', $this->c['community']->cuid)
        ->assertJsonPath('data.member_count', 4)
        ->assertJsonPath('data.moderator_permissions.pin_posts', true);

    $this->actingAs($this->c['outsider'])->getJson($path)->assertForbidden();
});
