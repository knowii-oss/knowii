<?php

use App\Enums\KnowiiSpaceType;
use App\Models\CommunityResource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;

beforeEach(function () {
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Resources]);
    $this->resource = CommunityResource::factory()->create(['space_id' => $this->space->id]);
    ResourceCapture::factory()->create(['community_resource_id' => $this->resource->id, 'markdown' => 'Body', 'word_count' => 1200, 'reading_time' => 6]);
});

function resourcePageUrl(Space $space, CommunityResource $resource): string
{
    return route('resources.show', [
        'communitySlug' => $space->community->slug,
        'spaceSlug' => $space->slug,
        'resourceSlug' => $resource->slug,
    ]);
}

test('members see the page of a resource, without the large fields (#1133)', function () {
    $this->actingAs($this->c['member'])
        ->get(resourcePageUrl($this->space, $this->resource))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Resources/Show')
            ->where('resource.cuid', $this->resource->cuid)
            ->where('resource.capture.reading_time', 6)
            ->where('resource.capture.markdown', null)
            ->has('resource.curator', 4)
            ->where('space.cuid', $this->space->cuid)
            ->has('spaceGroups'));
});

test('the page of a resource needs the right to read its space (#1133)', function () {
    $this->actingAs($this->c['outsider'])->get(resourcePageUrl($this->space, $this->resource))->assertForbidden();
});

test('a resource is only found in its own resources space (#1133)', function () {
    $other = Space::factory()->inGroup($this->space->spaceGroup)->create(['type' => KnowiiSpaceType::Resources]);
    $discussion = Space::factory()->inGroup($this->space->spaceGroup)->create(['type' => KnowiiSpaceType::Discussion]);

    $this->actingAs($this->c['member'])->get(resourcePageUrl($other, $this->resource))->assertNotFound();
    $this->actingAs($this->c['member'])->get(resourcePageUrl($discussion, $this->resource))->assertNotFound();
});
