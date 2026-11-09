<?php

use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class]);
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Resources]);
});

function resourceWithImages(Space $space): Resource
{
    $item = CommunityResource::factory()->create(['space_id' => $space->id]);
    $resource = $item->resource;
    Storage::disk('public')->put("resources/{$resource->cuid}/cover.webp", 'image');

    return $resource;
}

function shareIn(Resource $resource, Community $community): void
{
    $space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))->create(['type' => KnowiiSpaceType::Resources]);
    CommunityResource::factory()->create(['space_id' => $space->id, 'resource_id' => $resource->id]);
}

test('deleting a community deletes the files of its uploads and of the resources only it shares (#1265)', function () {
    $upload = app(UploadsImages::class)->upload($this->c['member'], $this->c['community'], ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => 'A']);
    $own = resourceWithImages($this->space);
    $shared = resourceWithImages($this->space);
    shareIn($shared, Community::factory()->create());

    app(DeletesCommunities::class)->delete($this->c['owner'], $this->c['community']);

    Storage::disk('public')->assertMissing($upload->path);
    Storage::disk('public')->assertMissing("resources/{$own->cuid}/cover.webp");
    expect(Resource::query()->whereKey($own->id)->exists())->toBeFalse();
    Storage::disk('public')->assertExists("resources/{$shared->cuid}/cover.webp");
    expect(Resource::query()->whereKey($shared->id)->exists())->toBeTrue();
});

test('deleting a resources space deletes the images of the resources no community uses any more (#1265)', function () {
    $own = resourceWithImages($this->space);
    $shared = resourceWithImages($this->space);
    shareIn($shared, Community::factory()->create());

    app(DeletesSpaces::class)->delete($this->c['owner'], $this->space);

    Storage::disk('public')->assertMissing("resources/{$own->cuid}/cover.webp");
    Storage::disk('public')->assertExists("resources/{$shared->cuid}/cover.webp");
    expect(Resource::query()->whereKey($own->id)->exists())->toBeFalse()
        ->and(Resource::query()->whereKey($shared->id)->exists())->toBeTrue();
});

test('deleting an account deletes the files of the communities it owns (#1265)', function () {
    $user = User::factory()->withUserProfile()->create();
    $community = Community::factory()->create(['owner_id' => $user->id]);
    $upload = app(UploadsImages::class)->upload($user, $community, ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => 'A']);

    $this->actingAs($user)->delete('/user', ['password' => 'password']);

    expect(Community::query()->whereKey($community->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($upload->path);
});
