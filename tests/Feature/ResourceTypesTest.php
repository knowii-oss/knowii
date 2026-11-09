<?php

use App\ApiRoutes;
use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Support\ResourceTypeDetector;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const READABLE_PAGE = '<!DOCTYPE html><html lang="en"><head><title>Readable</title>%s</head><body><article><h1>Readable</h1>'
    .'<p>This is a sufficiently long paragraph of readable content so that the parser has something meaningful to '
    .'extract. It discusses software testing and quality in general terms, with enough words to be an article.</p>'
    .'<p>A second paragraph keeps the parser happy, because one paragraph is sometimes too short for it.</p>'
    .'</article></body></html>';

function pageWithHead(string $head): string
{
    return sprintf(READABLE_PAGE, $head);
}

test('the type comes from the first rule that matches (#1246)', function (?string $html, string $url, string $contentType, bool $readable, KnowiiResourceType $expected) {
    expect((new ResourceTypeDetector)->detect($html, $url, $contentType, $readable))->toBe($expected);
})->with([
    'JSON-LD article' => ['<script type="application/ld+json">{"@type":"BlogPosting"}</script>', 'https://example.com/a', 'text/html', false, KnowiiResourceType::Article],
    'JSON-LD video in a list' => ['<script type="application/ld+json">[{"@type":["Thing","VideoObject"]}]</script>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Video],
    'JSON-LD podcast in @graph' => ['<script type="application/ld+json">{"@graph":[{"@type":"WebPage"},{"@type":"PodcastEpisode"}]}</script>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Audio],
    'JSON-LD book' => ['<script type="application/ld+json">{"@type":"Book"}</script>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Book],
    'JSON-LD paper' => ['<script type="application/ld+json">{"@type":"ScholarlyArticle"}</script>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Paper],
    'JSON-LD tool' => ['<script type="application/ld+json">{"@type":"SoftwareApplication"}</script>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Tool],
    'og:type video' => ['<meta property="og:type" content="video.other">', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Video],
    'og:type music' => ['<meta property="og:type" content="music.song">', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Audio],
    'og:type book' => ['<meta property="og:type" content="book">', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Book],
    'YouTube' => ['<meta property="og:type" content="website">', 'https://www.youtube.com/watch?v=1', 'text/html', true, KnowiiResourceType::Video],
    'arXiv' => [null, 'https://arxiv.org/abs/1234', 'text/html', true, KnowiiResourceType::Paper],
    'DOI' => [null, 'https://doi.org/10.1000/182', 'text/html', false, KnowiiResourceType::Paper],
    'GitHub' => [null, 'https://github.com/knowii-oss/knowii', 'text/html', true, KnowiiResourceType::Tool],
    'PDF' => [null, 'https://example.com/paper.pdf', 'application/pdf', false, KnowiiResourceType::Paper],
    'audio file' => [null, 'https://example.com/episode.mp3', 'audio/mpeg', false, KnowiiResourceType::Audio],
    'readable page' => ['<title>Hello</title>', 'https://example.com/a', 'text/html', true, KnowiiResourceType::Article],
    'nothing readable' => ['<title>Hello</title>', 'https://example.com/a', 'text/html', false, KnowiiResourceType::Other],
]);

/**
 * @return array{0: array<string, mixed>, 1: Space, 2: CommunityResource}
 */
function typedResource(array $attributes = []): array
{
    Storage::fake('public');
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);
    $resource = CommunityResource::factory()->create([
        'space_id' => $space->id,
        'resource_id' => Resource::factory()->create(['url' => 'https://example.com/page', 'type' => KnowiiResourceType::Other])->id,
        'capture_status' => KnowiiCaptureStatus::Pending,
        'type' => KnowiiResourceType::Other,
        'curator_id' => $c['member']->profile->id,
        ...$attributes,
    ]);

    return [$c, $space, $resource];
}

test('the capture gives the detected type to the resource and to the community resource (#1246)', function () {
    [, , $resource] = typedResource();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(pageWithHead('<meta property="og:type" content="article">')));

    expect($resource->refresh()->type)->toBe(KnowiiResourceType::Article)
        ->and($resource->resource->type)->toBe(KnowiiResourceType::Article)
        ->and($resource->capture)->not->toBeNull();
});

test('only articles keep captured content: a video page stays a link (ADR 0018)', function () {
    [, , $resource] = typedResource();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(pageWithHead('<meta property="og:type" content="video.other">')));

    expect($resource->refresh()->type)->toBe(KnowiiResourceType::Video)
        ->and($resource->capture)->toBeNull();
});

test('a type chosen by the curator stays after a new capture (#1246)', function () {
    [, , $resource] = typedResource(['type' => KnowiiResourceType::Book, 'type_chosen' => true]);

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(pageWithHead('<meta property="og:type" content="article">')));

    expect($resource->refresh()->type)->toBe(KnowiiResourceType::Book)
        // The shared resource keeps what the page says
        ->and($resource->resource->type)->toBe(KnowiiResourceType::Article)
        ->and($resource->capture)->toBeNull();
});

test('a PDF is typed by its content type', function () {
    [, , $resource] = typedResource();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer('%PDF-1.7', 'application/pdf'));

    expect($resource->refresh()->type)->toBe(KnowiiResourceType::Paper);
});

function resourceUpdatePath(Space $space, CommunityResource $resource): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);
}

test('the curator and the members who manage the spaces change the type and the level (#1246)', function (string $who) {
    [$c, $space, $resource] = typedResource(['capture_status' => KnowiiCaptureStatus::Captured]);

    $this->actingAs($c[$who])->patchJson(resourceUpdatePath($space, $resource), ['type' => 'video', 'level' => 'expert'])
        ->assertOk()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.type_chosen', true)
        ->assertJsonPath('data.level', 'expert');
})->with(['curator' => ['member'], 'owner' => ['owner'], 'admin' => ['admin']]);

test('other members cannot change a resource (#1246)', function (string $who) {
    [$c, $space, $resource] = typedResource(['curator_id' => null]);

    $this->actingAs($c[$who])->patchJson(resourceUpdatePath($space, $resource), ['type' => 'video'])->assertForbidden();

    expect($resource->refresh()->type)->toBe(KnowiiResourceType::Other);
})->with(['member' => ['member'], 'moderator without the right' => ['moderator'], 'outsider' => ['outsider']]);

test('an unknown type is refused', function () {
    [$c, $space, $resource] = typedResource();

    $this->actingAs($c['owner'])->patchJson(resourceUpdatePath($space, $resource), ['type' => 'textArticle'])
        ->assertStatus(400)
        ->assertJsonPath('type', 'validationIssue');
});

test('a resource that is not an article any more loses its server capture', function () {
    [$c, $space, $resource] = typedResource(['type' => KnowiiResourceType::Article, 'capture_status' => KnowiiCaptureStatus::Captured]);
    ResourceCapture::factory()->create(['community_resource_id' => $resource->id, 'source' => KnowiiCaptureSource::Server]);

    $this->actingAs($c['owner'])->patchJson(resourceUpdatePath($space, $resource), ['type' => 'tool'])->assertOk();

    expect($resource->refresh()->capture)->toBeNull();
});

test('a type given when the link is added stays', function () {
    Queue::fake();
    [$c, $space] = typedResource();

    $this->actingAs($c['owner'])->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]), ['url' => 'https://example.com/book', 'level' => 'beginner', 'type' => 'book'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'book')
        ->assertJsonPath('data.type_chosen', true);
});
