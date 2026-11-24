<?php

use App\Actions\Resources\CaptureResourcePage;
use App\Actions\Resources\CreateCommunityResource;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Jobs\CaptureCommunityResource;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\SafeUrl;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;

test('public http and https addresses can be fetched (#1231)', function (string $url) {
    expect(SafeUrl::assertPublic($url))->toBe('93.184.215.14');
})->with([
    'https' => 'https://example.com/article',
    'http on port 80' => 'http://example.com:80/article',
    'public IPv4' => 'https://93.184.215.14/',
]);

test('other schemes and ports are refused (#1231)', function (string $url) {
    SafeUrl::assertPublic($url);
})->throws(BusinessException::class)->with([
    'file' => 'file:///etc/passwd',
    'ftp' => 'ftp://example.com/file',
    'gopher' => 'gopher://example.com/',
    'no host' => 'https:///path',
    'port 6379' => 'http://example.com:6379/',
]);

test('private, loopback, link-local and reserved addresses are refused (#1231)', function (string $url) {
    SafeUrl::assertPublic($url);
})->throws(BusinessException::class, 'This address cannot be fetched.')->with([
    'loopback' => 'http://127.0.0.1/',
    'loopback IPv6' => 'http://[::1]/',
    'private 10/8' => 'http://10.0.0.5/',
    'private 172.16/12' => 'http://172.17.0.2/',
    'private 192.168/16' => 'http://192.168.1.1/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'shared 100.64/10' => 'http://100.64.0.1/',
    'unspecified' => 'http://0.0.0.0/',
    'unique local IPv6' => 'http://[fd00::1]/',
    'IPv4-mapped loopback' => 'http://[::ffff:127.0.0.1]/',
    'NAT64 of a loopback address' => 'http://[64:ff9b::7f00:1]/',
    'NAT64 of the cloud metadata' => 'http://[64:ff9b::a9fe:a9fe]/',
    'local NAT64' => 'http://[64:ff9b:1::1]/',
    'IPv4-compatible loopback' => 'http://[::7f00:1]/',
    'site-local IPv6' => 'http://[fec0::1]/',
    'multicast IPv6' => 'http://[ff02::1]/',
    'multicast IPv4' => 'http://224.0.0.1/',
    'multicast IPv4, last range' => 'http://239.255.255.250/',
]);

test('the special ranges are refused, the public addresses around them still pass', function (string $address) {
    expect(SafeUrl::isPublicAddress($address))->toBeTrue();
})->with([
    'public IPv6' => '2606:4700::1',
    'public IPv6 next to NAT64' => '64:ff9c::1',
    'public IPv4 below multicast' => '223.255.255.1',
    'public IPv4' => '93.184.215.14',
]);

test('a host that does not resolve and a host with a private address get the same answer', function () {
    SafeUrl::resolveUsing(fn (string $host) => $host === 'db' ? ['172.18.0.3'] : []);

    $messages = [];
    foreach (['http://db/', 'http://nothing.internal/'] as $url) {
        try {
            SafeUrl::assertPublic($url);
        } catch (BusinessException $e) {
            $messages[] = $e->getMessage();
        }
    }

    expect($messages)->toBe(['This address cannot be fetched.', 'This address cannot be fetched.']);
});

test('a host name that resolves to an internal address is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => $host === 'pgsql' ? ['172.18.0.3'] : ['93.184.215.14']);

    SafeUrl::assertPublic('http://pgsql/');
})->throws(BusinessException::class, 'This address cannot be fetched.');

test('a host name with one internal address among public ones is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => ['93.184.215.14', '127.0.0.1']);

    SafeUrl::assertPublic('https://rebinding.example/');
})->throws(BusinessException::class);

test('a host name that does not resolve is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => []);

    SafeUrl::assertPublic('https://does-not-exist.example/');
})->throws(BusinessException::class, 'This address cannot be fetched.');

test('the host is pinned to the checked address (#1231)', function () {
    expect(SafeUrl::pinnedResolveOption('https://example.com/a', '93.184.215.14'))->toBe([CURLOPT_RESOLVE => ['example.com:443:93.184.215.14']])
        ->and(SafeUrl::pinnedResolveOption('http://example.com/a', '2606:4700::1'))->toBe([CURLOPT_RESOLVE => ['example.com:80:[2606:4700::1]']]);
});

test('a capture that redirects to an internal address fails without reading it (#1231)', function () {
    $resource = CommunityResource::factory()->create([
        'resource_id' => Resource::factory()->create(['url' => 'https://example.com/redirect'])->id,
    ]);
    $mock = new MockHandler([
        new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        new Response(200, [], 'secret'),
    ]);
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack(HandlerStack::create($mock));

    (new CaptureCommunityResource($resource))->handle($capturer);

    expect($resource->refresh()->capture_status)->toBe(KnowiiCaptureStatus::Failed)
        ->and($resource->capture_failure_reason)->toBe('This address cannot be fetched.')
        // The internal answer was never read
        ->and($mock->count())->toBe(1);
});

test('a resource with an internal address is refused before any request (#1231)', function () {
    $owner = User::factory()->withUserProfile()->withPersonalCommunity()->create();
    $space = Space::factory()
        ->inGroup(SpaceGroup::factory()->create(['community_id' => $owner->ownedCommunities()->first()->id]))
        ->create(['type' => KnowiiSpaceType::Resources]);

    Queue::fake();

    expect(fn () => (new CreateCommunityResource)->create($owner, $space, [
        'name' => 'Redis',
        'url' => 'http://127.0.0.1/',
        'level' => KnowiiResourceLevel::Beginner->value,
    ]))->toThrow(BusinessException::class);

    expect(CommunityResource::query()->count())->toBe(0);
    Queue::assertNotPushed(CaptureCommunityResource::class);
});

/**
 * A capturer whose requests are answered in order, with the options of each request kept in $history
 *
 * @param  list<Response>  $answers
 * @param  array<int, array<string, mixed>>  $history
 */
function recordingCapturer(array $answers, array &$history): CaptureResourcePage
{
    $stack = HandlerStack::create(new MockHandler($answers));
    $stack->push(Middleware::history($history));
    $capturer = new CaptureResourcePage;
    $capturer->setHttpHandlerStack($stack);

    return $capturer;
}

test('each redirect connects to the address that was checked for its host', function () {
    SafeUrl::resolveUsing(fn (string $host): array => $host === 'other.example' ? ['93.184.216.34'] : ['93.184.215.14']);
    $history = [];
    $capturer = recordingCapturer([
        new Response(302, ['Location' => 'http://other.example/next']),
        new Response(200, ['Content-Type' => 'text/html'], '<html><title>Next</title></html>'),
    ], $history);

    $page = $capturer->fetchPage('https://first.example/start');

    expect($page['status'])->toBe(200)
        ->and($history)->toHaveCount(2)
        ->and((string) $history[1]['request']->getUri())->toBe('http://other.example/next')
        ->and($history[0]['options']['curl'][CURLOPT_RESOLVE])->toBe(['first.example:443:93.184.215.14'])
        ->and($history[1]['options']['curl'][CURLOPT_RESOLVE])->toBe(['other.example:80:93.184.216.34']);
});

test('a redirect from http to https on the same host gets its own pin', function () {
    $history = [];
    $capturer = recordingCapturer([
        new Response(301, ['Location' => 'https://example.com/page']),
        new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
    ], $history);

    $capturer->fetchPage('http://example.com/page');

    expect($history[0]['options']['curl'][CURLOPT_RESOLVE])->toBe(['example.com:80:93.184.215.14'])
        ->and($history[1]['options']['curl'][CURLOPT_RESOLVE])->toBe(['example.com:443:93.184.215.14']);
});

test('a relative redirect is followed on the host of the page', function () {
    $history = [];
    $capturer = recordingCapturer([
        new Response(302, ['Location' => '/moved']),
        new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
    ], $history);

    $capturer->fetchPage('https://example.com/page');

    expect((string) $history[1]['request']->getUri())->toBe('https://example.com/moved');
});

test('a redirect to a host with a private address is refused before it is requested', function () {
    SafeUrl::resolveUsing(fn (string $host): array => $host === 'inside.example' ? ['10.0.0.5'] : ['93.184.215.14']);
    $history = [];
    $capturer = recordingCapturer([
        new Response(302, ['Location' => 'http://inside.example/admin']),
        new Response(200, [], 'inside'),
    ], $history);

    expect(fn () => $capturer->fetchPage('https://example.com/page'))->toThrow(BusinessException::class)
        ->and($history)->toHaveCount(1);
});

test('five redirects are followed, a sixth one is refused', function () {
    $hops = fn (int $count) => array_map(fn (int $i) => new Response(302, ['Location' => "https://example.com/{$i}"]), range(1, $count));

    $history = [];
    $page = recordingCapturer([...$hops(5), new Response(200, ['Content-Type' => 'text/html'], 'end')], $history)->fetchPage('https://example.com/0');
    expect($page['body'])->toBe('end')->and($history)->toHaveCount(6);

    $history = [];
    $capturer = recordingCapturer([...$hops(6), new Response(200, [], 'end')], $history);
    expect(fn () => $capturer->fetchPage('https://example.com/0'))->toThrow(BusinessException::class, 'The page redirects too many times')
        ->and($history)->toHaveCount(6);
});

test('a connection to an address that is not public is refused by curl too', function () {
    $history = [];
    recordingCapturer([new Response(200, ['Content-Type' => 'text/html'], '<html></html>')], $history)->fetchPage('https://example.com/page');
    $check = $history[0]['options']['curl'][CURLOPT_PREREQFUNCTION];
    $handle = curl_init();

    expect($check($handle, '93.184.215.14', '172.18.0.4', 443, 50000))->toBe(CURL_PREREQFUNC_OK)
        ->and($check($handle, '10.0.0.5', '172.18.0.4', 443, 50000))->toBe(CURL_PREREQFUNC_ABORT)
        ->and($check($handle, '127.0.0.1', '127.0.0.1', 80, 50000))->toBe(CURL_PREREQFUNC_ABORT);
});
