<?php

use App\Enums\KnowiiSpaceType;
use App\Models\CommunityResource;
use App\Models\ResourceTextArticle;
use App\Models\Space;
use App\Models\SpaceGroup;

beforeEach(function () {
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Resources]);
    $this->resource = CommunityResource::factory()->create(['space_id' => $this->space->id]);
    $article = ResourceTextArticle::create([
        'resource_id' => $this->resource->resource_id,
        'html' => '<p>Body</p>',
        'markdown' => 'Body',
        'word_count' => 1200,
        'reading_time' => 6,
    ]);
    $this->resource->update(['resource_text_article_id' => $article->id]);
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
            ->where('resource.resource_text_article.reading_time', 6)
            ->where('resource.resource_text_article.markdown', null)
            ->where('resource.resource.cover_image_base64', null)
            ->has('resource.curator', 3)
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
