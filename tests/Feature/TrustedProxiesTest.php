<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The reverse proxy in front of a Docker deployment (#1172): TRUSTED_PROXIES lets Laravel see the real scheme.
 */

afterEach(function () {
    TrustProxies::flushState();
});

test('no proxy is trusted by default', function () {
    config(['app.trusted_proxies' => '']);
    (new AppServiceProvider($this->app))->boot();
    Route::get('/proxy-check', fn (Request $request) => response()->json(['secure' => $request->isSecure()]));

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/proxy-check')->assertJson(['secure' => false]);
});

test('a trusted proxy gives the scheme of the original request', function () {
    config(['app.trusted_proxies' => '*']);
    (new AppServiceProvider($this->app))->boot();
    Route::get('/proxy-check', fn (Request $request) => response()->json(['secure' => $request->isSecure()]));

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/proxy-check')->assertJson(['secure' => true]);
});

test('a trusted proxy gives the address of the client', function () {
    config(['app.trusted_proxies' => '10.0.0.1']);
    (new AppServiceProvider($this->app))->boot();
    Route::get('/proxy-check', fn (Request $request) => response()->json(['ip' => $request->ip()]));

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/proxy-check')->assertExactJson(['ip' => '203.0.113.9']);

    // Another machine is not a proxy of the list: its own address counts
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/proxy-check')->assertExactJson(['ip' => '10.0.0.2']);
});

test('a path prefix that a proxy gives is not read', function (string $trustedProxies) {
    config(['app.trusted_proxies' => $trustedProxies, 'app.url' => 'https://app.example.com']);
    (new AppServiceProvider($this->app))->boot();
    // The list of the values that the application reads holds for every way to trust a proxy
    TrustProxies::at('*');
    Route::get('/proxy-check', fn (Request $request) => response()->json([
        'secure' => $request->isSecure(),
        'base' => $request->getBaseUrl(),
        'root' => $request->root(),
        'login' => route('login'),
    ]));

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Prefix' => '/other'])->get('http://app.example.com/proxy-check')
        ->assertExactJson(['secure' => true, 'base' => '', 'root' => 'https://app.example.com', 'login' => 'https://app.example.com/login']);
})->with([
    'TRUSTED_PROXIES=*' => ['*'],
    'TRUSTED_PROXIES is empty' => [''],
]);
