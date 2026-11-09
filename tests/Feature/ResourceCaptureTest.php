<?php

use App\Actions\Resources\CreateTextResource;
use App\ApiRoutes;
use App\Contracts\Spaces\DeletesSpaces;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Models\CommunityResource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CommunityResourceFiles;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function captureResourcesSpace(): array
{
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    return [$c, $space];
}

function singleCaptureResourcePath(Space $space, CommunityResource $resource): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);
}

test('a capture belongs to one community resource: another community with the same link never sees it (#1244)', function () {
    [$a, $spaceA] = captureResourcesSpace();
    [$b, $spaceB] = captureResourcesSpace();
    $inA = CommunityResource::factory()->create(['space_id' => $spaceA->id]);
    // The same global resource (one per URL), curated by the second community
    $inB = CommunityResource::factory()->create(['space_id' => $spaceB->id, 'resource_id' => $inA->resource_id]);
    ResourceCapture::factory()->create(['community_resource_id' => $inA->id, 'markdown' => 'Only for community A']);

    $this->actingAs($a['member'])->getJson(singleCaptureResourcePath($spaceA, $inA))
        ->assertOk()
        ->assertJsonPath('data.capture.markdown', 'Only for community A');

    $responseB = $this->actingAs($b['member'])->getJson(singleCaptureResourcePath($spaceB, $inB))->assertOk();
    expect($responseB->json('data.capture'))->toBeNull()
        ->and(json_encode($responseB->json()))->not->toContain('Only for community A');
});

test('the level and the type belong to the community resource (#1244)', function () {
    [$c, $space] = captureResourcesSpace();
    $inA = CommunityResource::factory()->create(['space_id' => $space->id, 'level' => KnowiiResourceLevel::Expert]);

    $this->actingAs($c['member'])->getJson(singleCaptureResourcePath($space, $inA))
        ->assertOk()
        ->assertJsonPath('data.level', 'expert')
        ->assertJsonPath('data.type', 'textArticle')
        ->assertJsonPath('data.capture_status', 'captured')
        ->assertJsonMissingPath('data.resource.level');
});

test('deleting a community resource deletes its capture and its files (#1244)', function () {
    [, $space] = captureResourcesSpace();
    $resource = CommunityResource::factory()->create(['space_id' => $space->id]);
    $capture = ResourceCapture::factory()->create(['community_resource_id' => $resource->id]);
    $file = CommunityResourceFiles::folder($resource->cuid).'/images/one.webp';
    Storage::disk('public')->put($file, 'image');

    $resource->delete();

    expect(ResourceCapture::query()->whereKey($capture->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($file);
});

test('deleting a resources space deletes the files of its community resources (#1244)', function () {
    [$c, $space] = captureResourcesSpace();
    $resource = CommunityResource::factory()->create(['space_id' => $space->id]);
    $file = CommunityResourceFiles::folder($resource->cuid).'/images/one.webp';
    Storage::disk('public')->put($file, 'image');

    app(DeletesSpaces::class)->delete($c['owner'], $space);

    Storage::disk('public')->assertMissing($file);
});

test('adding a link stores its Markdown on the community resource, not the HTML (#1244)', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);
    $paragraph = str_repeat('Smart notes help you think and write better every single day. ', 20);
    $html = '<!DOCTYPE html><html lang="en"><head><title>Captured</title></head><body><article><h1>Captured</h1>'
        ."<p>{$paragraph}</p><p>{$paragraph}</p></article></body></html>";

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        new Response(200, [], ''),     // final URL
        new Response(200, [], ''),     // availability (HEAD)
        new Response(200, [], $html),  // page
        new Response(404, [], ''),     // icon
    ])));

    $created = $action->create($owner, $space, ['url' => 'https://example.com/captured', 'level' => KnowiiResourceLevel::Advanced->value]);

    $created->refresh();
    expect($created->level)->toBe(KnowiiResourceLevel::Advanced)
        ->and($created->capture_status)->toBe(KnowiiCaptureStatus::Captured)
        ->and($created->capture)->not->toBeNull()
        ->and($created->capture->markdown)->toContain('Smart notes help you think')
        ->and($created->capture->markdown)->not->toContain('<p>')
        ->and($created->capture->reading_time)->toBeGreaterThanOrEqual(1);
});
