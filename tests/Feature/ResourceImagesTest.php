<?php

use App\Actions\Resources\CreateTextResource;
use App\Constants;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\ResourceImages;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

function pngBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

beforeEach(function () {
    Storage::fake('public');
});

test('the cover and the icon are re-encoded, scaled down and stored on the uploads disk (#1063)', function () {
    $resource = Resource::factory()->create();

    ResourceImages::store($resource, pngBytes(1600, 900), pngBytes(256, 256));

    $resource->refresh();
    expect($resource->cover_image_path)->toStartWith("resources/{$resource->cuid}/cover.")
        ->and($resource->favicon_path)->toStartWith("resources/{$resource->cuid}/favicon.");
    Storage::disk('public')->assertExists([$resource->cover_image_path, $resource->favicon_path]);

    $cover = getimagesizefromstring(Storage::disk('public')->get($resource->cover_image_path));
    $favicon = getimagesizefromstring(Storage::disk('public')->get($resource->favicon_path));
    expect($cover[0])->toBe(Constants::RESOURCE_COVER_MAX_DIMENSION)
        ->and($favicon[0])->toBe(Constants::RESOURCE_FAVICON_MAX_DIMENSION);
});

test('an image that cannot be read is skipped (#1063)', function () {
    $resource = Resource::factory()->create(['cover_image_path' => null, 'favicon_path' => null]);

    ResourceImages::store($resource, '<svg onload="alert(1)"></svg>', null);

    expect($resource->refresh()->cover_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('a captured page gets its cover and icon, and lists show their URLs (#1063)', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    $html = '<!DOCTYPE html><html lang="en"><head><title>Covered</title>'
        .'<meta property="og:image" content="https://cdn.example.com/cover.png">'
        .'<link rel="icon" type="image/png" href="/icon.png"></head><body><p>Text</p></body></html>';

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        new Response(200, [], ''),               // final URL
        new Response(200, [], ''),               // availability (HEAD)
        new Response(200, [], $html),            // page through Browserless
        new Response(200, ['Content-Type' => 'image/png'], pngBytes(1200, 630)), // cover
        new Response(200, ['Content-Type' => 'image/png'], pngBytes(48, 48)),    // icon
    ])));

    $created = $action->create($owner, $space, [
        'url' => 'https://example.com/covered',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]);

    $resource = $created->resource->refresh();
    expect($resource->cover_image_path)->not->toBeNull()
        ->and($resource->favicon_path)->not->toBeNull()
        ->and($resource->cover_image_base64)->not->toBeNull();

    $item = $this->actingAs($owner)
        ->getJson('api/v1/communities/'.$space->community->cuid.'/spaces/'.$space->cuid.'/resources')
        ->assertOk()
        ->json('data.0.resource');
    expect($item['cover_image_thumbnail_url'])->toBe(Storage::disk('public')->url($resource->cover_image_path))
        ->and($item['favicon_url'])->toBe(Storage::disk('public')->url($resource->favicon_path))
        ->and($item['cover_image_base64'])->toBeNull();
});

test('a page whose images are not images still gets saved (#1063)', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    $html = '<!DOCTYPE html><html><head><title>Broken images</title><meta property="og:image" content="https://cdn.example.com/c.png"></head><body></body></html>';

    $action = new CreateTextResource;
    $action->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        new Response(200, [], ''),
        new Response(200, [], ''),
        new Response(200, [], $html),
        new Response(200, ['Content-Type' => 'text/html'], '<html></html>'), // cover is a page
        new Response(404, ['Content-Type' => 'image/png'], ''),               // no icon
    ])));

    $created = $action->create($owner, $space, ['url' => 'https://example.com/broken', 'level' => KnowiiResourceLevel::Beginner->value]);

    expect($created->resource->refresh()->cover_image_path)->toBeNull()
        ->and($created->resource->favicon_path)->toBeNull();
});
