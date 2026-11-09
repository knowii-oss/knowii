<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\ArticleImages;
use App\Support\CommunityResourceFiles;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function articleImagePng(): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @return array{0: array<string, mixed>, 1: Space, 2: CommunityResource}
 */
function articleResource(KnowiiSpaceVisibility $visibility = KnowiiSpaceVisibility::Members): array
{
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources, 'visibility' => $visibility]);
    $resource = CommunityResource::factory()->create([
        'space_id' => $space->id,
        'type' => KnowiiResourceType::Article,
        'resource_id' => Resource::factory()->create(['url' => 'https://example.com/post'])->id,
    ]);

    return [$c, $space, $resource];
}

test('the images of an article are stored by Knowii and the Markdown points to the copies (#1247)', function () {
    [, , $resource] = articleResource();
    $markdown = "Intro\n\n![A chart](https://cdn.example.com/chart.png \"Chart\")\n\nAgain ![A chart](https://cdn.example.com/chart.png)";

    $result = ArticleImages::store($resource, $markdown, fn (string $url) => articleImagePng());

    $folder = CommunityResourceFiles::folder($resource->cuid).'/images';
    expect(Storage::disk('public')->allFiles($folder))->toHaveCount(1)
        ->and($result)->not->toContain('cdn.example.com')
        ->and(substr_count($result, Storage::disk('public')->url($folder.'/1.')))->toBe(2)
        ->and($result)->toContain('![A chart](');
});

test('an image that cannot be stored becomes its alternative text', function () {
    [, , $resource] = articleResource();

    $result = ArticleImages::store($resource, '![Broken](https://cdn.example.com/a.png) and ![Inline](data:image/png;base64,AAAA) and ![](https://cdn.example.com/b.png)', fn (string $url) => null);

    expect($result)->toBe('Broken and Inline and ');
});

test('at most 20 images are stored; the others become their alternative text', function () {
    [, , $resource] = articleResource();
    $markdown = implode("\n", array_map(fn (int $i) => "![Image {$i}](https://cdn.example.com/{$i}.png)", range(1, Constants::CAPTURE_MAX_IMAGES + 2)));

    $result = ArticleImages::store($resource, $markdown, fn (string $url) => articleImagePng());

    expect(Storage::disk('public')->allFiles(CommunityResourceFiles::folder($resource->cuid).'/images'))->toHaveCount(Constants::CAPTURE_MAX_IMAGES)
        ->and($result)->toContain("\nImage 21\nImage 22");
});

test('the capture stores the images of the article through the capture fetch (#1247)', function () {
    [, , $resource] = articleResource();
    $resource->update(['capture_status' => KnowiiCaptureStatus::Pending, 'type_chosen' => true]);
    $paragraph = str_repeat('Smart notes help you think and write better every single day. ', 20);
    $html = '<!DOCTYPE html><html><head><title>With images</title></head><body><article><h1>With images</h1>'
        ."<p>{$paragraph}</p><p><img src=\"/img/chart.png\" alt=\"A chart\"></p><p>{$paragraph}</p></article></body></html>";

    (new CaptureCommunityResource($resource))->handle(fakeCapturer($html, more: [
        new Response(404),                                                    // the icon of the site
        new Response(200, ['Content-Type' => 'image/png'], articleImagePng()), // the image of the article
    ]));

    $markdown = $resource->refresh()->capture->markdown;
    expect($markdown)->toContain(Storage::disk('public')->url(CommunityResourceFiles::folder($resource->cuid).'/images/1.'))
        ->and($markdown)->not->toContain('/img/chart.png');
});

test('members read the captured article on the resource page, rendered with raw HTML escaped (#1247)', function () {
    [$c, $space, $resource] = articleResource();
    ResourceCapture::factory()->create([
        'community_resource_id' => $resource->id,
        'markdown' => "# Title\n\nHello <script>alert(1)</script> **world**",
        'word_count' => 4,
        'reading_time' => 1,
        'source' => KnowiiCaptureSource::Server,
    ]);

    $this->actingAs($c['member'])->get(route('resources.show', [
        'communitySlug' => $space->community->slug, 'spaceSlug' => $space->slug, 'resourceSlug' => $resource->slug,
    ]))->assertInertia(fn ($page) => $page
        ->where('article.reading_time', 1)
        ->where('article.source', 'server')
        ->where('article.html', fn (string $html) => str_contains($html, '<strong>world</strong>')
            && ! str_contains($html, '<script>')
            && str_contains($html, '&lt;script&gt;')));
});

test('a signed-in visitor of a public space never gets the captured content (ADR 0018)', function () {
    [$c, $space, $resource] = articleResource(KnowiiSpaceVisibility::Public);
    ResourceCapture::factory()->create(['community_resource_id' => $resource->id, 'markdown' => 'Members only text']);

    $this->actingAs($c['outsider'])->get(route('resources.show', [
        'communitySlug' => $space->community->slug, 'spaceSlug' => $space->slug, 'resourceSlug' => $resource->slug,
    ]))->assertOk()->assertInertia(fn ($page) => $page->where('article', null));

    $api = $this->actingAs($c['outsider'])->getJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]))->assertOk();
    expect(json_encode($api->json()))->not->toContain('Members only text');
});

test('a resource that is not an article shows no article', function () {
    [$c, $space, $resource] = articleResource();
    $resource->update(['type' => KnowiiResourceType::Video]);
    ResourceCapture::factory()->create(['community_resource_id' => $resource->id]);

    $this->actingAs($c['member'])->get(route('resources.show', [
        'communitySlug' => $space->community->slug, 'spaceSlug' => $space->slug, 'resourceSlug' => $resource->slug,
    ]))->assertInertia(fn ($page) => $page->where('article', null));
});

test('a deleted capture takes the stored images of its article', function () {
    [, , $resource] = articleResource();
    $capture = ResourceCapture::factory()->create(['community_resource_id' => $resource->id]);
    $file = CommunityResourceFiles::folder($resource->cuid).'/images/1.webp';
    Storage::disk('public')->put($file, 'image');

    $capture->delete();

    Storage::disk('public')->assertMissing($file);
});
