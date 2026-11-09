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
})->throws(BusinessException::class, 'This address points to a private or local network')->with([
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
]);

test('a host name that resolves to an internal address is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => $host === 'pgsql' ? ['172.18.0.3'] : ['93.184.215.14']);

    SafeUrl::assertPublic('http://pgsql/');
})->throws(BusinessException::class, 'This address points to a private or local network');

test('a host name with one internal address among public ones is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => ['93.184.215.14', '127.0.0.1']);

    SafeUrl::assertPublic('https://rebinding.example/');
})->throws(BusinessException::class);

test('a host name that does not resolve is refused (#1231)', function () {
    SafeUrl::resolveUsing(fn (string $host) => []);

    SafeUrl::assertPublic('https://does-not-exist.example/');
})->throws(BusinessException::class, 'The address of this site cannot be found');

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
        ->and($resource->capture_failure_reason)->toBe('This address points to a private or local network')
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
