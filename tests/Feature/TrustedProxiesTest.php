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
