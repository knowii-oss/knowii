<?php

use App\ApiRoutes;
use App\Contracts\Comments\CreatesComments;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Events\CommunityResources\CommunityResourceDeleted;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Comment;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityResourceFiles;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Delete a resource of a resources space (#324).
 */
beforeEach(function () {
    Event::fake([SpaceTreeChanged::class, CommunityResourceDeleted::class]);
    Storage::fake('public');
    $this->c = communityWithMembers();
    $group = SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]);
    $this->space = Space::factory()->inGroup($group)->create(['type' => KnowiiSpaceType::Resources]);
    $this->resource = CommunityResource::factory()->create(['space_id' => $this->space->id, 'curator_id' => $this->c['member']->profile->id]);
});

function deletableResourcePath(CommunityResource $resource): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [
        ApiRoutes::PARAM_COMMUNITY => $resource->community->cuid,
        ApiRoutes::PARAM_SPACE => $resource->space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);
}

test('the curator deletes a resource with its comments and captured files; the space hears it', function () {
    app(CreatesComments::class)->create($this->c['admin'], $this->resource, ['body' => 'Useful']);
    $folder = CommunityResourceFiles::folder($this->resource->cuid);
    Storage::disk('public')->put($folder.'/images/a.webp', 'x');

    $this->actingAs($this->c['member'])->deleteJson(deletableResourcePath($this->resource))->assertNoContent();

    expect(CommunityResource::query()->whereKey($this->resource->id)->exists())->toBeFalse()
        ->and(Comment::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($folder.'/images/a.webp');
    Event::assertDispatched(CommunityResourceDeleted::class, fn (CommunityResourceDeleted $event) => $event->broadcastWith() === ['cuid' => $this->resource->cuid]);
});

test('owners, admins and moderators of the space delete any resource; other members and outsiders cannot', function () {
    $this->actingAs($this->c['admin'])->deleteJson(deletableResourcePath(CommunityResource::factory()->create(['space_id' => $this->space->id])))->assertNoContent();
    $this->actingAs($this->c['moderator'])->deleteJson(deletableResourcePath(CommunityResource::factory()->create(['space_id' => $this->space->id])))->assertNoContent();

    $other = CommunityResource::factory()->create(['space_id' => $this->space->id, 'curator_id' => $this->c['admin']->profile->id]);
    $this->actingAs($this->c['member'])->deleteJson(deletableResourcePath($other))->assertForbidden();
    $this->actingAs($this->c['outsider'])->deleteJson(deletableResourcePath($other))->assertForbidden();

    expect(CommunityResource::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('the shared page stays while another community uses it, and goes with the last one (#1265)', function () {
    $page = $this->resource->resource;
    $elsewhere = CommunityResource::factory()->create(['resource_id' => $page->id]);

    $this->actingAs($this->c['member'])->deleteJson(deletableResourcePath($this->resource))->assertNoContent();
    expect(Resource::query()->whereKey($page->id)->exists())->toBeTrue();

    $elsewhere->delete();
    $last = CommunityResource::factory()->create(['space_id' => $this->space->id, 'resource_id' => $page->id, 'curator_id' => $this->c['member']->profile->id]);
    $this->actingAs($this->c['member'])->deleteJson(deletableResourcePath($last))->assertNoContent();

    expect(Resource::query()->whereKey($page->id)->exists())->toBeFalse();
});

test('an API token needs resources:write to delete a resource', function () {
    $token = $this->c['member']->createToken('read only', ['resources:read'])->plainTextToken;

    $this->withToken($token)->deleteJson(deletableResourcePath($this->resource))->assertForbidden();
    expect(CommunityResource::query()->whereKey($this->resource->id)->exists())->toBeTrue();
});

test('the resource page tells who can delete it', function () {
    $url = route('resources.show', ['communitySlug' => $this->c['community']->slug, 'spaceSlug' => $this->space->slug, 'resourceSlug' => $this->resource->slug]);

    $this->actingAs($this->c['member'])->get($url)->assertInertia(fn ($page) => $page->where('permissions.canDelete', true));
    $this->actingAs($this->c['admin'])->get($url)->assertInertia(fn ($page) => $page->where('permissions.canDelete', true));
    $other = User::factory()->withUserProfile()->create();
    $this->c['community']->users()->attach($other, ['role' => KnowiiCommunityMemberRole::Member]);
    $this->actingAs($other)->get($url)->assertInertia(fn ($page) => $page->where('permissions.canDelete', false));
});

test('a curator who left the community can no longer change, capture or delete their resource, even in a public space (#1324)', function () {
    $this->space->update(['visibility' => KnowiiSpaceVisibility::Public]);
    $this->c['community']->users()->detach($this->c['member']);
    $former = $this->c['member']->fresh();

    expect(Gate::forUser($former)->check('update', $this->resource))->toBeFalse()
        ->and(Gate::forUser($former)->check('capture', $this->resource))->toBeFalse()
        ->and(Gate::forUser($former)->check('delete', $this->resource))->toBeFalse();

    $this->actingAs($former)->deleteJson(deletableResourcePath($this->resource))->assertForbidden();
    $this->actingAs($former)->patchJson(deletableResourcePath($this->resource), ['level' => 'expert'])->assertForbidden();
    expect(CommunityResource::query()->whereKey($this->resource->id)->exists())->toBeTrue();
});
