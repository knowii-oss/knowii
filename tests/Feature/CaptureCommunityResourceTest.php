<?php

use App\Actions\Resources\CaptureResourcePage;
use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCaptureSource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Events\CommunityResources\CommunityResourceUpdated;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\ResourceCapture;
use App\Models\Space;
use App\Models\SpaceGroup;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;

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
    // An outsider: the members space answers as a space that does not exist
})->with(['member' => ['member', 403], 'moderator without the right' => ['moderator', 403], 'outsider' => ['outsider', 404]]);

test('a capture that runs cannot start again', function () {
    Queue::fake();
    [$c, $space, $resource] = pendingCapture();

    $this->actingAs($c['member'])->postJson(captureAgainPath($space, $resource))
        ->assertStatus(422)
        ->assertJsonPath('type', 'businessIssue');

    Queue::assertNotPushed(CaptureCommunityResource::class);
});

/**
 * A handler that answers like curl: it writes the body, decoded when `decode_content` is on, into the sink of the
 * request in chunks, and stops with a write error when the sink takes less than a chunk
 */
function curlLikeHandler(string $body, array $headers): Closure
{
    return function (RequestInterface $request, array $options) use ($body, $headers) {
        $sink = Utils::streamFor($options['sink'] ?? Utils::tryFopen('php://temp', 'w+'));
        $gzip = ($headers['Content-Encoding'] ?? '') === 'gzip' && ($options['decode_content'] ?? true) !== false;
        $inflate = $gzip ? inflate_init(ZLIB_ENCODING_GZIP) : null;
        foreach (str_split($body, 8192) as $chunk) {
            $data = $inflate !== null ? inflate_add($inflate, $chunk) : $chunk;
            if ($data !== '' && $sink->write($data) < strlen($data)) {
                return Create::rejectionFor(new RequestException('cURL error 23: Failure writing output to destination', $request));
            }
        }
        $sink->rewind();

        return Create::promiseFor(new Response(200, $headers, $sink));
    };
}

test('a page larger than the size limit fails at once and is not tried again', function () {
    [, , $resource] = pendingCapture();

    (new CaptureCommunityResource($resource))->handle(fakeCapturer('<html>'.str_repeat('a', Constants::MAX_FETCH_SIZE_BYTES).'</html>'));

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('The page or the image is too large');
});

test('the size limit applies to the decoded content of a compressed page', function () {
    [, , $resource] = pendingCapture();
    $gzip = (string) gzencode('<html><body>'.str_repeat('0', Constants::MAX_FETCH_SIZE_BYTES + 1).'</body></html>');
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create(curlLikeHandler($gzip, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'])));

    expect(strlen($gzip))->toBeLessThan(Constants::MAX_FETCH_SIZE_BYTES / 100);

    (new CaptureCommunityResource($resource))->handle($capturer);

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('The page or the image is too large');
});

test('a compressed image larger than the size limit is not stored', function () {
    $gzip = (string) gzencode(str_repeat('0', Constants::MAX_FETCH_SIZE_BYTES + 1));
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create(curlLikeHandler($gzip, ['Content-Type' => 'image/png', 'Content-Encoding' => 'gzip'])));

    expect($capturer->downloadImage('https://example.com/image.png'))->toBeNull();
});

test('a page that takes too long fails at once and is not tried again', function () {
    [, , $resource] = pendingCapture();
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create(new MockHandler([
        fn (RequestInterface $request) => throw new ConnectException('cURL error 28: Operation timed out', $request, null, ['errno' => CURLE_OPERATION_TIMEDOUT]),
    ])));

    (new CaptureCommunityResource($resource))->handle($capturer);

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('The page took too long to load');
});

test('the images of one capture share one time budget', function () {
    [, , $resource] = pendingCapture();
    $resource->update(['type' => KnowiiResourceType::Article, 'type_chosen' => true]);
    $paragraph = str_repeat('Smart notes help you think and write better every single day. ', 20);
    $images = implode('', array_map(fn (int $i) => "<p><img src=\"/img/{$i}.png\" alt=\"Image {$i}\"></p>", range(1, 10)));
    $html = "<!DOCTYPE html><html><head><title>Slow images</title></head><body><article><h1>Slow images</h1><p>{$paragraph}</p>{$images}<p>{$paragraph}</p></article></body></html>";
    $png = imagecreatetruecolor(40, 30);
    ob_start();
    imagepng($png);
    $pngBytes = (string) ob_get_clean();

    $requests = 0;
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create(function (RequestInterface $request, array $options) use ($html, $pngBytes, &$requests) {
        $requests++;
        if ($requests > 1) {
            // Each image takes 10 seconds
            $this->travel(10)->seconds();
        }

        return curlLikeHandler($requests === 1 ? $html : $pngBytes, ['Content-Type' => $requests === 1 ? 'text/html' : 'image/png'])($request, $options);
    }));

    (new CaptureCommunityResource($resource))->handle($capturer);

    // The page, the icon of the site, then the images until 45 seconds are used
    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Captured)
        ->and($requests)->toBe(1 + 5)
        ->and($resource->capture->markdown)->toContain('Image 10');
});

test('a capture has a time limit below the time after which the queue gives the job to another worker', function () {
    [, , $resource] = pendingCapture();
    $job = new CaptureCommunityResource($resource);

    expect($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->failOnTimeout)->toBeTrue();
});

test('a captured article longer than the limit is cut at a paragraph', function () {
    [, , $resource] = pendingCapture();
    $paragraph = str_repeat('Smart notes help you think and write better every single day. ', 20);
    $html = '<!DOCTYPE html><html><head><title>Long</title></head><body><article><h1>Long</h1>'
        .str_repeat("<p>{$paragraph}</p>", (int) ceil(Constants::MAX_CAPTURE_MARKDOWN_LENGTH / strlen($paragraph)) + 10)
        .'</article></body></html>';

    (new CaptureCommunityResource($resource))->handle(fakeCapturer($html));

    $markdown = $resource->refresh()->capture->markdown;
    expect(mb_strlen($markdown))->toBeLessThanOrEqual(Constants::MAX_CAPTURE_MARKDOWN_LENGTH)
        ->and($markdown)->toEndWith('day.');
});

test('captures run on their own queue, apart from the live updates, the notifications and the account mails', function () {
    Queue::fake();
    [$c, $space, $resource] = pendingCapture();
    $resource->update(['capture_status' => KnowiiCaptureStatus::Failed]);

    $this->actingAs($c['member'])->postJson(captureAgainPath($space, $resource))->assertOk();
    $this->actingAs($c['member'])->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [
        ApiRoutes::PARAM_COMMUNITY => $space->community->cuid,
        ApiRoutes::PARAM_SPACE => $space->cuid,
    ]), ['url' => 'https://example.com/another', 'level' => 'beginner'])->assertCreated();

    Queue::assertPushedOn('captures', CaptureCommunityResource::class);
    expect(Queue::pushed(CaptureCommunityResource::class)->pluck('queue')->all())->toBe(['captures', 'captures']);
});
