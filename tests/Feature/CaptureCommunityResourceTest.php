<?php

use App\ApiRoutes;
use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiSpaceType;
use App\Events\CommunityResources\CommunityResourceUpdated;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const CAPTURED_ARTICLE = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    .'<title>Some Article Title</title><meta name="description" content="A great read about testing">'
    .'<meta name="keywords" content="testing, quality">'
    .'</head><body><article><h1>Some Article Title</h1>'
    .'<p>This is a sufficiently long paragraph of readable content so that the parser has '
    .'something meaningful to extract. It discusses software testing and quality in general terms, '
    .'with enough words to be considered an article rather than boilerplate.</p>'
    .'<p>A second paragraph keeps the parser happy, because one paragraph is sometimes too short for it to '
    .'consider the page an article. Testing is a discipline, quality is a habit.</p>'
    .'</article></body></html>';

beforeEach(function () {
    Storage::fake('public');
});

/**
 * A pending community resource of a resources space, with a URL
 *
 * @return array{0: array<string, mixed>, 1: Space, 2: CommunityResource}
 */
function pendingCapture(string $url = 'https://example.com/some-article', string $name = 'Some Article'): array
{
    $c = communityWithMembers();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $c['community']->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);
    $resource = CommunityResource::factory()->create([
        'space_id' => $space->id,
        'name' => $name,
        'resource_id' => Resource::factory()->create(['url' => $url, 'name' => 'Some Article', 'keywords' => []])->id,
        'capture_status' => KnowiiCaptureStatus::Pending,
        'curator_id' => $c['member']->profile->id,
    ]);

    return [$c, $space, $resource];
}

test('the job captures the page: metadata on the resource, Markdown on the community resource, status captured', function () {
    [, , $resource] = pendingCapture(name: 'Some Article');

    (new CaptureCommunityResource($resource, nameFromPage: true))->handle(fakeCapturer(CAPTURED_ARTICLE));

    $resource->refresh();
    expect($resource->capture_status)->toBe(KnowiiCaptureStatus::Captured)
        ->and($resource->capture_failure_reason)->toBeNull()
        // No name from the member: the title of the page (#1062)
        ->and($resource->name)->toBe('Some Article Title')
        ->and($resource->resource->description)->toBe('A great read about testing')
        ->and($resource->resource->keywords)->toBe(['testing', 'quality'])
        ->and($resource->resource->last_captured_at)->not->toBeNull()
        ->and($resource->capture->source)->toBe(KnowiiCaptureSource::Server)
        ->and($resource->capture->markdown)->toContain('Testing is a discipline');
});

test('the name of the member stays after the capture', function () {
    [, , $resource] = pendingCapture(name: 'My own name');

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(CAPTURED_ARTICLE));

    expect($resource->refresh()->name)->toBe('My own name');
});

test('a page without readable content stays a link with its metadata', function () {
    [, , $resource] = pendingCapture();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer('<html><head><title>Empty</title></head><body></body></html>'));

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Captured)
        ->and($resource->capture)->toBeNull()
        ->and($resource->resource->name)->toBe('Empty');
});

test('a file that is not a page (a PDF) stays a link', function () {
    [, , $resource] = pendingCapture('https://example.com/paper.pdf');

    (new CaptureCommunityResource($resource))->handle(fakeCapturer('%PDF-1.7', 'application/pdf', more: [new Response(500)]));

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Captured)
        ->and($resource->capture)->toBeNull();
});

test('a page that does not exist fails at once, with a reason for the curator', function () {
    [, , $resource] = pendingCapture();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer('Not found', status: 404));

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('The page does not exist any more');
});

test('a server error is tried again, and the last failure marks the capture as failed', function () {
    [, , $resource] = pendingCapture();
    $job = new CaptureCommunityResource($resource);

    expect(fn () => $job->handle(fakeCapturer('Oops', status: 503)))->toThrow(RuntimeException::class);
    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Capturing);

    $job->failed(new RuntimeException('The site answered with the HTTP status 503'));

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('The page could not be loaded. Try again later.');
});

test('the job retries three times with a backoff and runs once per resource', function () {
    [, , $resource] = pendingCapture();
    $job = new CaptureCommunityResource($resource);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60, 300])
        ->and($job->uniqueId())->toBe((string) $resource->id);
});

test('each status change is broadcast on the channel of the space', function () {
    [, , $resource] = pendingCapture();
    Event::fake([CommunityResourceUpdated::class]);

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(CAPTURED_ARTICLE));

    // capturing, then captured
    Event::assertDispatchedTimes(CommunityResourceUpdated::class, 2);
});

test('a capture from the browser extension is better than a plain fetch: it stays', function () {
    [, , $resource] = pendingCapture();
    ResourceCapture::factory()->create(['community_resource_id' => $resource->id, 'markdown' => 'From the extension', 'source' => KnowiiCaptureSource::Extension]);

    (new CaptureCommunityResource($resource))->handle(fakeCapturer(CAPTURED_ARTICLE));

    expect($resource->refresh()->capture->markdown)->toBe('From the extension');
});

function captureAgainPath(Space $space, CommunityResource $resource): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCE_CAPTURE, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
        ApiRoutes::PARAM_RESOURCE => $resource->cuid,
    ]);
}

test('the curator and the members who manage the spaces capture a resource again', function (string $who) {
    Queue::fake();
    [$c, $space, $resource] = pendingCapture();
    $resource->update(['capture_status' => KnowiiCaptureStatus::Failed, 'capture_failure_reason' => 'Down']);

    $this->actingAs($c[$who])->postJson(captureAgainPath($space, $resource))
        ->assertOk()
        ->assertJsonPath('data.capture_status', 'pending')
        ->assertJsonPath('data.capture_failure_reason', null);

    Queue::assertPushed(CaptureCommunityResource::class, fn (CaptureCommunityResource $job) => ! $job->nameFromPage);
})->with(['member' => ['member'], 'owner' => ['owner'], 'admin' => ['admin']]);

test('other members cannot capture a resource again', function (string $who, int $status) {
    Queue::fake();
    [$c, $space, $resource] = pendingCapture();
    $resource->update(['capture_status' => KnowiiCaptureStatus::Failed, 'curator_id' => $c['owner']->profile->id]);

    $this->actingAs($c[$who])->postJson(captureAgainPath($space, $resource))->assertStatus($status);

    Queue::assertNotPushed(CaptureCommunityResource::class);
})->with(['member' => ['member', 403], 'moderator without the right' => ['moderator', 403], 'outsider' => ['outsider', 403]]);

test('a capture that runs cannot start again', function () {
    Queue::fake();
    [$c, $space, $resource] = pendingCapture();

    $this->actingAs($c['member'])->postJson(captureAgainPath($space, $resource))
        ->assertStatus(422)
        ->assertJsonPath('type', 'businessIssue');

    Queue::assertNotPushed(CaptureCommunityResource::class);
});
