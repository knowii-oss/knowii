<?php

use App\ApiRoutes;
use App\Enums\KnowiiApiResponseType;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;

beforeEach(function () {
    $this->c = communityWithMembers();
});

function communityPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

test('the owner renames a community and the slug stays the same (#1129, #508)', function () {
    $community = $this->c['community'];
    $slug = $community->slug;

    $this->actingAs($this->c['owner'])
        ->patchJson(communityPath($community), ['name' => 'A brand new name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'A brand new name')
        ->assertJsonPath('data.slug', $slug)
        ->assertJsonPath('data.member_count', 4);

    expect($community->fresh()?->slug)->toBe($slug);
});

test('the owner changes the description and the visibility; the other fields keep their value (#1129)', function () {
    $community = $this->c['community'];
    $name = $community->name;

    $this->actingAs($this->c['owner'])
        ->patchJson(communityPath($community), ['description' => 'Updated', 'visibility' => 'public'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Updated')
        ->assertJsonPath('data.visibility', 'public')
        ->assertJsonPath('data.name', $name);

    $this->actingAs($this->c['owner'])->patchJson(communityPath($community), ['description' => null])->assertOk()->assertJsonPath('data.description', '');
});

test('only the owner updates a community (#1129)', function (string $role) {
    $community = $this->c['community'];
    $name = $community->name;

    $this->actingAs($this->c[$role])->patchJson(communityPath($community), ['name' => 'Taken over'])->assertForbidden();

    expect($community->fresh()?->name)->toBe($name);
})->with(['admin', 'moderator', 'member', 'outsider']);

test('the visibility never becomes personal, and a personal community stays personal (#1129)', function () {
    $this->actingAs($this->c['owner'])
        ->patchJson(communityPath($this->c['community']), ['visibility' => 'personal'])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);

    $owner = User::factory()->withUserProfile()->create();
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);
    $this->actingAs($owner)->patchJson(communityPath($personal), ['visibility' => 'public'])->assertStatus(400);
    $this->actingAs($owner)->patchJson(communityPath($personal), ['visibility' => 'personal', 'name' => 'My notes'])->assertOk();

    expect($personal->fresh()?->visibility)->toBe(KnowiiCommunityVisibility::Personal);
});

test('the name follows the creation rules (#1129)', function (mixed $name) {
    $this->actingAs($this->c['owner'])
        ->patchJson(communityPath($this->c['community']), ['name' => $name])
        ->assertStatus(400)
        ->assertJsonPath('type', KnowiiApiResponseType::ValidationIssue->value);
})->with(['', 'ab', str_repeat('a', 300), 'Bad <name>']);
